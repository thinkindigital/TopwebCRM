#!/usr/bin/env bash
#
# SetupThinkin.sh — Instalador guiado do TopwebCRM (Epic E11).
#
# Uso:
#   bash SetupThinkin.sh            # interativo
#   PORTAINER_API_KEY=... bash SetupThinkin.sh install
#   DRY_RUN=1 bash SetupThinkin.sh  # lista ações sem executar
#
# O bootstrap instala Docker em Ubuntu/Debian quando necessário, inicializa o
# Swarm, descobre a rede usada pelo Traefik e instala a base ausente. Uma API
# key do Portainer pode ser fornecida por ambiente; sem ela o script usa login
# e senha somente em memória. Nunca registre a linha de comando com secrets.
# Sem credenciais GitHub: SHA resolvido via API anônima, imagem GHCR pública.
# Segredos nunca vão para logs, YAML, resumo ou histórico (ver I11.3, issue #52).
#
# Slices: I11.1 esqueleto (#50) | I11.2 pré-requisitos (#51) |
#         I11.3 secrets (#52) | I11.4 deploy (#53) | I11.5 update/rollback (#54)

set -euo pipefail

# ---------------------------------------------------------------------------
# Apresentação Thinkin Digital (identidade própria, inspirada no ritual do
# SetupOrion sem copiá-lo): banner + aviso de SO + aceite. Nunca trava por
# causa do aviso: apenas informa e segue.
# ---------------------------------------------------------------------------

thinkin_banner() {
    cat <<'EOF'

  ████████╗██╗  ██╗██╗███╗   ██╗██╗  ██╗██╗███╗   ██╗
  ╚══██╔══╝██║  ██║██║████╗  ██║██║ ██╔╝██║████╗  ██║
     ██║   ███████║██║██╔██╗ ██║█████╔╝ ██║██╔██╗ ██║
     ██║   ██╔══██║██║██║╚██╗██║██╔═██╗ ██║██║╚██╗██║
     ██║   ██║  ██║██║██║ ╚████║██║  ██╗██║██║ ╚████║
     ╚═╝   ╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝╚═╝  ╚═╝╚═╝╚═╝  ╚═══╝
              D I G I T A L
  ─────────────────────────────────────────────
   Desenvolva Sistemas Personalizados ou
   Ferramentas de Automação Comercial.
   Leonardo Nascimento · (11) 99319-3118
  ─────────────────────────────────────────────
EOF
}

thinkin_os_advice() {
    local id="?" codename="?"
    if [ -r /etc/os-release ]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        id="${ID:-?}"; codename="${VERSION_CODENAME:-?}"
    fi
    if [ "$id" = "ubuntu" ]; then
        case "$codename" in
            jammy|noble)
                log "SO suportado: Ubuntu $codename"
                return 0 ;;
        esac
    fi
    warn "SO detectado: $id/$codename — recomendado Ubuntu 22.04 (jammy) ou 24.04 (noble) LTS."
    warn "Motivo: repositório Docker ativo, versões travadas do engine e imagens/Traefik atuais"
    warn "assumem kernel e libs das LTS suportadas (20.04 atingiu EOL). Seguindo mesmo assim."
}

thinkin_accept() {
    cat <<'EOF'
  Este instalador automatiza TopwebCRM e ferramentas em Docker Swarm
  (Portainer + Traefik). Ele cria redes, volumes, secrets e stacks, e
  NUNCA grava senhas em logs, resumos ou histórico. Ao continuar, você
  assume a operação no servidor atual.
EOF
    confirm "Deseja continuar?" || die "instalação abortada pelo usuário"
}

INSTALLER_VERSION="0.2.0-bootstrap"
GITHUB_REPO="thinkindigital/TopwebCRM"
RAW_BASE="https://raw.githubusercontent.com/$GITHUB_REPO"
SUMMARY_DIR="${SUMMARY_DIR:-$HOME/dados_vps}"
SUMMARY_FILE="$SUMMARY_DIR/dados_topwebcrm"
DRY_RUN="${DRY_RUN:-0}"

log()  { printf '[topwebcrm-install] %s\n' "$*"; }
warn() { printf '[topwebcrm-install][WARN] %s\n' "$*" >&2; }
die()  { printf '[topwebcrm-install][ERRO] %s\n' "$*" >&2; exit 1; }

run() {
    if [ "$DRY_RUN" = "1" ]; then
        printf '[dry-run] %s\n' "$*"
    else
        "$@"
    fi
}

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || die "comando obrigatório ausente: $1"
}

normalize_url() {
    local value="${1%/}"
    case "$value" in
        http://*|https://*) printf '%s' "$value" ;;
        *) printf 'https://%s' "$value" ;;
    esac
}

install_docker_engine() {
    if command -v docker >/dev/null 2>&1; then
        if docker info >/dev/null 2>&1; then
            return 0
        fi
        if command -v systemctl >/dev/null 2>&1 && [ "$(id -u)" -eq 0 ]; then
            systemctl enable --now docker >/dev/null 2>&1 || true
            docker info >/dev/null 2>&1 && return 0
        fi
    fi

    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] instalaria/iniciaria Docker Engine"
        return 0
    fi

    [ "$(id -u)" -eq 0 ] || die "Docker ausente/inativo: execute o SetupThinkin como root para instalar o engine"
    command -v apt-get >/dev/null 2>&1 || die "Docker ausente e sistema sem apt-get suportado"

    local os_id="" codename="" arch=""
    if [ -r /etc/os-release ]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        os_id="${ID:-}"
        codename="${VERSION_CODENAME:-}"
    fi
    case "$os_id" in
        ubuntu|debian) ;;
        *) die "instalação automática do Docker suporta Ubuntu/Debian; detectado: ${os_id:-desconhecido}" ;;
    esac
    [ -n "$codename" ] || codename="$(lsb_release -cs 2>/dev/null || true)"
    [ -n "$codename" ] || die "não foi possível detectar o codename da distribuição"

    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl gnupg
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL "https://download.docker.com/linux/$os_id/gpg" \
        | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
    printf 'deb [arch=%s signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/%s %s stable\n' \
        "$(dpkg --print-architecture)" "$os_id" "$codename" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    systemctl enable --now docker
    docker info >/dev/null 2>&1 || die "Docker foi instalado, mas o daemon não respondeu"
    log "Docker Engine instalado e ativo"
}

ensure_swarm() {
    local state=""
    state="$(docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null || true)"
    [ "$state" = "active" ] && return 0
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] inicializaria Docker Swarm"
        return 0
    fi

    local advertise="${DOCKER_ADVERTISE_ADDR:-}"
    if [ -z "$advertise" ] && command -v ip >/dev/null 2>&1; then
        advertise="$(ip route get 1.1.1.1 2>/dev/null | awk '{for (i=1; i<=NF; i++) if ($i == "src") {print $(i+1); exit}}')"
    fi
    if [ -n "$advertise" ]; then
        docker swarm init --advertise-addr "$advertise" >/dev/null
    else
        docker swarm init >/dev/null
    fi
    log "Docker Swarm inicializado"
}

step_bootstrap_host() {
    log "Bootstrap do host — Docker Engine e Swarm"
    install_docker_engine
    if ! command -v curl >/dev/null 2>&1 || ! command -v openssl >/dev/null 2>&1 || ! command -v jq >/dev/null 2>&1; then
        [ "$DRY_RUN" = "1" ] || {
            [ "$(id -u)" -eq 0 ] || die "curl, openssl e jq são necessários; execute como root"
            apt-get update -qq
            DEBIAN_FRONTEND=noninteractive apt-get install -y -qq curl openssl jq
        }
    fi
    require_cmd docker
    require_cmd curl
    require_cmd openssl
    require_cmd jq
    ensure_swarm
}

load_secret_file() {
    local variable="$1" file_variable="$2" path="" permissions="" value=""
    path="$(printenv "$file_variable" 2>/dev/null || true)"
    [ -n "$path" ] || return 0
    [ -r "$path" ] || die "arquivo de secret não pode ser lido: $path"
    permissions="$(stat -c '%a' "$path" 2>/dev/null || true)"
    [ -n "$permissions" ] || die "não foi possível verificar permissões do arquivo de secret: $path"
    if [ $((8#$permissions & 077)) -ne 0 ]; then
        die "arquivo de secret deve ser privado (sem permissões de grupo/outros): $path"
    fi
    value="$(<"$path")"
    [ -n "$value" ] || die "arquivo de secret vazio: $path"
    printf -v "$variable" '%s' "$value"
    export "$variable"
    unset value
    log "$variable carregado de arquivo protegido (valor oculto)"
}

load_external_secrets() {
    load_secret_file PORTAINER_API_KEY PORTAINER_API_KEY_FILE
    load_secret_file PORTAINER_PASS PORTAINER_PASS_FILE
    load_secret_file OPENWA_API_KEY OPENWA_API_KEY_FILE
    load_secret_file TOPWEBCRM_MAIL_PASSWORD TOPWEBCRM_MAIL_PASSWORD_FILE
    load_secret_file TOPWEBCRM_ADMIN_PASSWORD TOPWEBCRM_ADMIN_PASSWORD_FILE
}

# Lê entrada com default; permite override via variável de ambiente.
ask() {
    local var_name="$1" prompt="$2" default="${3:-}" secret="${4:-0}" current="" answer=""
    current="$(printenv "$var_name" 2>/dev/null || true)"
    if [ -n "$current" ]; then
        log "$var_name (via ambiente, oculto se segredo)"
        return 0
    fi
    if [ "$secret" = "1" ]; then
        read -rsp "$prompt: " answer || true
        echo
    elif [ -n "$default" ]; then
        read -rp "$prompt [$default]: " answer || true
        answer="${answer:-$default}"
    else
        read -rp "$prompt: " answer || true
    fi
    [ -n "$answer" ] || die "$var_name é obrigatório"
    printf -v "$var_name" '%s' "$answer"
    export "$var_name"
}

# Variante opcional: vazio é permitido (sem default e sem die).
ask_opt() {
    local var_name="$1" prompt="$2" current="" answer=""
    current="$(printenv "$var_name" 2>/dev/null || true)"
    if [ -n "$current" ]; then
        log "$var_name (via ambiente)"
        return 0
    fi
    read -rp "$prompt (vazio = não): " answer || true
    printf -v "$var_name" '%s' "$answer"
    export "$var_name"
}

# Variante opcional para tokens: vazio é permitido e nunca é ecoado.
ask_secret_opt() {
    local var_name="$1" prompt="$2" current="" answer=""
    current="$(printenv "$var_name" 2>/dev/null || true)"
    if [ -n "$current" ]; then
        log "$var_name (via ambiente, oculto)"
        return 0
    fi
    read -rsp "$prompt (vazio = configurar depois): " answer || true
    echo
    printf -v "$var_name" '%s' "$answer"
    export "$var_name"
}

confirm() {
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] confirmação automática: $1 (Y)"
        return 0
    fi
    local answer=""
    while true; do
        read -rp "$1 (Y/N): " answer || die "entrada encerrada (stdin fechado)"
        case "$answer" in
            [Yy]) return 0 ;;
            [Nn]) return 1 ;;
            *) echo "Responda Y ou N." ;;
        esac
    done
}

# Idempotência: 0 se o recurso já existe (pular), 1 se precisa criar.
stack_exists()      { docker stack ls --format '{{.Name}}' 2>/dev/null | grep -qx "$1"; }
network_exists()    { docker network ls --format '{{.Name}}' 2>/dev/null | grep -qx "$1"; }
volume_exists()     { docker volume ls --format '{{.Name}}' 2>/dev/null | grep -qx "$1"; }
secret_exists()     { docker secret ls --format '{{.Name}}' 2>/dev/null | grep -qx "$1"; }
node_name()         { docker info --format '{{.Name}}' 2>/dev/null; }
node_label_present(){ docker node inspect "$(node_name)" --format '{{.Spec.Labels}}' 2>/dev/null | grep -q "$1"; }

discover_proxy_network() {
    local service target network candidate
    if [ -n "${TOPWEBCRM_PROXY_NETWORK:-}" ] && network_exists "$TOPWEBCRM_PROXY_NETWORK"; then
        printf '%s' "$TOPWEBCRM_PROXY_NETWORK"
        return 0
    fi

    for service in $(docker service ls --format '{{.Name}}' 2>/dev/null | grep -E '(^|_)traefik(_|$)' || true); do
        while IFS= read -r target; do
            [ -n "$target" ] || continue
            network="$(docker network inspect "$target" --format '{{.Name}}' 2>/dev/null || true)"
            if [ -n "$network" ]; then
                printf '%s' "$network"
                return 0
            fi
        done < <(docker service inspect "$service" --format '{{json .Spec.TaskTemplate.Networks}}' 2>/dev/null \
            | jq -r '.[].Target // empty' 2>/dev/null || true)
    done

    for candidate in TopwebNet renacesso traefik proxy; do
        if network_exists "$candidate"; then
            printf '%s' "$candidate"
            return 0
        fi
    done
    return 1
}

discover_portainer_url() {
    local service rule host
    for service in $(docker service ls --format '{{.Name}}' 2>/dev/null | grep -E '(^|_)portainer(_|$)' || true); do
        rule="$(docker service inspect "$service" --format '{{index .Spec.Labels "traefik.http.routers.portainer.rule"}}' 2>/dev/null || true)"
        host="$(printf '%s' "$rule" | sed -n 's/.*Host(`\([^`]*\)`).*/\1/p')"
        [ -n "$host" ] && { printf 'https://%s' "$host"; return 0; }
    done
    return 1
}

discover_install_defaults() {
    local detected="" portainer_url=""
    if [ -z "${TOPWEBCRM_PROXY_NETWORK:-}" ]; then
        detected="$(discover_proxy_network || true)"
        [ -n "$detected" ] && TOPWEBCRM_PROXY_NETWORK="$detected"
    fi
    TOPWEBCRM_PROXY_NETWORK="${TOPWEBCRM_PROXY_NETWORK:-topweb_proxy}"
    TOPWEBCRM_INTEGRATIONS_NETWORK="${TOPWEBCRM_INTEGRATIONS_NETWORK:-topweb_integrations}"
    TRAEFIK_NETWORK="${TRAEFIK_NETWORK:-$TOPWEBCRM_PROXY_NETWORK}"
    if [ -z "${PORTAINER_URL:-}" ]; then
        portainer_url="$(discover_portainer_url || true)"
        [ -n "$portainer_url" ] && PORTAINER_URL="$portainer_url"
    fi
    export TOPWEBCRM_PROXY_NETWORK TOPWEBCRM_INTEGRATIONS_NETWORK TRAEFIK_NETWORK PORTAINER_URL
}

step_preflight() {
    log "Etapa 0/5 — pré-voo (issue #50)"
    require_cmd docker
    require_cmd curl
    require_cmd openssl
    require_cmd jq
    if ! docker info --format '{{.Swarm.LocalNodeState}}' 2>/dev/null | grep -qx 'active'; then
        [ "$DRY_RUN" = "1" ] || die "Docker Swarm não está ativo neste nó"
        log "[dry-run] Docker Swarm seria validado como ativo"
    fi
    log "pré-voo OK"
}

step_collect() {
    log "Etapa 1/5 — coleta (tudo sobrescrevível; Enter aceita o default)"
    discover_install_defaults
    ask TOPWEBCRM_DOMAIN        "Domínio do CRM (https://...)" "" 0
    TOPWEBCRM_DOMAIN="${TOPWEBCRM_DOMAIN#https://}"
    TOPWEBCRM_DOMAIN="${TOPWEBCRM_DOMAIN#http://}"
    TOPWEBCRM_DOMAIN="${TOPWEBCRM_DOMAIN%/}"
    export TOPWEBCRM_DOMAIN
    ask OPENWA_MODE "OpenWA: stack local ou API remota?" "local" 0
    case "$OPENWA_MODE" in
        local|remoto) ;;
        *) die "OPENWA_MODE deve ser local ou remoto" ;;
    esac
    if [ "$OPENWA_MODE" = "remoto" ]; then
        ask OPENWA_REMOTE_URL "URL base do OpenWA remoto (http://host:2785)" "" 0
        OPENWA_REMOTE_URL="$(normalize_url "$OPENWA_REMOTE_URL")"
        ask_secret_opt OPENWA_API_KEY "Chave da API OpenWA"
        ask_opt OPENWA_SESSION_NAME "Nome da sessão OpenWA para vincular"
        ask_opt OPENWA_SESSION_UUID "UUID da sessão OpenWA (vazio = usar o nome)"
        OPENWA_DOMAIN="(remoto)"
    else
        ask OPENWA_DOMAIN           "Domínio do OpenWA (https://...)" "" 0
    fi
    ask TOPWEBCRM_APP_NAME      "Nome do CRM" "TopwebCRM" 0
    ask TOPWEBCRM_ADMIN_NAME    "Nome do administrador" "TopwebCRM Admin" 0
    ask TOPWEBCRM_ADMIN_EMAIL   "E-mail do administrador" "admin@$TOPWEBCRM_DOMAIN" 0
    ask TOPWEBCRM_MAIL_HOST     "Host SMTP" "smtp.zoho.com.br" 0
    ask TOPWEBCRM_MAIL_PORT     "Porta SMTP" "465" 0
    ask TOPWEBCRM_MAIL_ENCRYPTION "Criptografia SMTP (ssl/tls)" "ssl" 0
    ask TOPWEBCRM_MAIL_USERNAME "Usuário SMTP" "contato@agenciarenascimento.com.br" 0
    ask TOPWEBCRM_MAIL_FROM_ADDRESS "Remetente" "$TOPWEBCRM_MAIL_USERNAME" 0
    ask TOPWEBCRM_DATA_MODE "Banco/dados: embutido ou compartilhado?" "embutido" 0
    case "$TOPWEBCRM_DATA_MODE" in
        embutido|compartilhado) ;;
        *) die "TOPWEBCRM_DATA_MODE deve ser embutido ou compartilhado" ;;
    esac
    if [ "$TOPWEBCRM_DATA_MODE" = "compartilhado" ]; then
        MYSQL_IMAGE="${MYSQL_IMAGE:-mysql:8.0}"
        MYSQL_SHARED_NETWORK="${MYSQL_SHARED_NETWORK:-$TOPWEBCRM_INTEGRATIONS_NETWORK}"
        REDIS_IMAGE="${REDIS_IMAGE:-redis:7.4-alpine}"
        REDIS_SHARED_NETWORK="${REDIS_SHARED_NETWORK:-$TOPWEBCRM_INTEGRATIONS_NETWORK}"
        REDIS_PASSWORD="${REDIS_PASSWORD:-}"
        export MYSQL_IMAGE MYSQL_SHARED_NETWORK REDIS_IMAGE REDIS_SHARED_NETWORK REDIS_PASSWORD
    fi
    ask FRESH_INSTALL "Instalação nova com banco vazio? (s/n)" "s" 0
    case "$FRESH_INSTALL" in
        s|S|y|Y) INITIAL_INSTALL="true" ;;
        n|N)     INITIAL_INSTALL="false" ;;
        *) die "responda s ou n" ;;
    esac
    export INITIAL_INSTALL
    ask PORTAINER_URL           "URL base do Portainer (https://...)" "${PORTAINER_URL:-https://painel.${TOPWEBCRM_DOMAIN#*.}}" 0
    PORTAINER_URL="$(normalize_url "$PORTAINER_URL")"
    export PORTAINER_URL
    if [ -z "${PORTAINER_API_KEY:-}" ]; then
        ask_secret_opt PORTAINER_API_KEY "Token API do Portainer"
    fi
    if [ -z "${PORTAINER_API_KEY:-}" ] || ! stack_exists portainer; then
        ask PORTAINER_USER "Usuário admin do Portainer" "admin" 0
        ask PORTAINER_PASS "Senha do Portainer" "" 1
    fi
    ask TOPWEBCRM_PROXY_NETWORK "Rede overlay do Traefik" "$TOPWEBCRM_PROXY_NETWORK" 0
    ask TOPWEBCRM_INTEGRATIONS_NETWORK "Rede overlay CRM<->OpenWA" "$TOPWEBCRM_INTEGRATIONS_NETWORK" 0
    ask TOPWEBCRM_NODE_LABEL    "Label do nó persistente (chave)" "topwebcrm" 0
    TRAEFIK_NETWORK="$TOPWEBCRM_PROXY_NETWORK"
    ask TRAEFIK_SSL_EMAIL "E-mail do Let's Encrypt" "$TOPWEBCRM_ADMIN_EMAIL" 0
    ask TRAEFIK_IMAGE "Imagem do Traefik" "traefik:v3.5" 0
    ask PORTAINER_DOMAIN "Domínio do Portainer" "${PORTAINER_URL#https://}" 0
    ask PORTAINER_IMAGE "Imagem do Portainer" "portainer/portainer-ce:latest" 0
    ask PORTAINER_AGENT_IMAGE "Imagem do agent Portainer" "portainer/agent:latest" 0
    if [ "${OPENWA_MODE:-local}" = "local" ]; then
        ask OPENWA_IMAGE_TAG        "Tag da imagem OpenWA" "0.23.3" 0
    fi
    export OPENWA_DOMAIN TRAEFIK_NETWORK TRAEFIK_SSL_EMAIL PORTAINER_DOMAIN \
        PORTAINER_IMAGE PORTAINER_AGENT_IMAGE
}

step_confirm() {
    log "Etapa 2/5 — conferência (sem segredos/senhas)"
    cat <<EOF
  CRM:        https://$TOPWEBCRM_DOMAIN ($TOPWEBCRM_APP_NAME)
  Portainer:  $PORTAINER_URL (${PORTAINER_API_KEY:+API key}${PORTAINER_API_KEY:-${PORTAINER_USER:-credenciais}})
  Redes:      proxy=$TOPWEBCRM_PROXY_NETWORK integrações=$TOPWEBCRM_INTEGRATIONS_NETWORK
  Nó:         label $TOPWEBCRM_NODE_LABEL=true
  Admin:      $TOPWEBCRM_ADMIN_NAME <$TOPWEBCRM_ADMIN_EMAIL>
  SMTP:       $TOPWEBCRM_MAIL_HOST:$TOPWEBCRM_MAIL_PORT/$TOPWEBCRM_MAIL_ENCRYPTION
  Dados:      $TOPWEBCRM_DATA_MODE
  OpenWA:     $OPENWA_MODE${OPENWA_REMOTE_URL:+ ($OPENWA_REMOTE_URL)}${OPENWA_SESSION_NAME:+ sessão=$OPENWA_SESSION_NAME}
  Banco novo: $FRESH_INSTALL
EOF
    confirm "As respostas estão corretas?" || die "instalação abortada pelo usuário"
}

# Resolve o SHA atual do main via API anônima (repo público, sem PAT).
resolve_sha() {
    local sha=""
    sha="$(curl -fsSL "https://api.github.com/repos/$GITHUB_REPO/commits/main" | jq -er '.sha')"
    [ -n "$sha" ] || die "não foi possível resolver o SHA do main"
    printf '%s' "$sha"
}

ensure_node_label() {
    if node_label_present "$TOPWEBCRM_NODE_LABEL=true"; then
        log "label $TOPWEBCRM_NODE_LABEL=true já presente"
        return 0
    fi
    local node="$(node_name)"
    [ -n "$node" ] || die "não foi possível identificar o nó manager do Swarm"
    run docker node update --label-add "$TOPWEBCRM_NODE_LABEL"=true "$node"
}

ensure_network() {
    local name="$1" mode="$2" # mode: create|require
    if network_exists "$name"; then
        local network_scope=""
        network_scope="$(docker network inspect "$name" --format '{{.Driver}} {{.Scope}}' 2>/dev/null || true)"
        [ "$network_scope" = "overlay swarm" ] \
            || die "rede '$name' existe, mas não é overlay Swarm (detectado: ${network_scope:-desconhecido})"
        log "rede $name já existe"
        return 0
    fi
    if [ "$mode" = "require" ]; then
        die "rede '$name' não existe — crie pelo SetupOrion/Traefik antes (é a rede do proxy)"
    fi
    run docker network create --driver overlay --attachable "$name"
}

ensure_volume() {
    if volume_exists "$1"; then
        log "volume $1 já existe"
        return 0
    fi
    run docker volume create "$1"
}

config_exists() { docker config ls --format '{{.Name}}' 2>/dev/null | grep -qx "$1"; }

# Docker Config imutável e versionada: vN+1 quando o conteúdo mudar.
ensure_entrypoint_config() {
    local sha="$1" content="" next=1
    content="$(curl -fsSL "$RAW_BASE/$sha/docker/openwa-swarm-entrypoint.sh")" \
        || die "falha ao baixar o entrypoint no SHA $sha"
    while config_exists "openwa_swarm_entrypoint_v$next"; do
        next=$((next + 1))
    done
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] criaria config openwa_swarm_entrypoint_v$next"
    else
        printf '%s' "$content" | docker config create "openwa_swarm_entrypoint_v$next" - >/dev/null
        log "config openwa_swarm_entrypoint_v$next criada"
    fi
    printf -v OPENWA_ENTRYPOINT_CONFIG '%s' "openwa_swarm_entrypoint_v$next"
    export OPENWA_ENTRYPOINT_CONFIG
}

step_prereqs() {
    log "Etapa 3/5 — pré-requisitos (issue #51)"
    setup_crm_instance
    TOPWEBCRM_IMAGE_SHA="${TOPWEBCRM_IMAGE_SHA:-$(resolve_sha)}"
    export TOPWEBCRM_IMAGE_SHA
    log "SHA do main: $TOPWEBCRM_IMAGE_SHA"
    ensure_node_label
    ensure_network "$TOPWEBCRM_PROXY_NETWORK" require
    ensure_network "$TOPWEBCRM_INTEGRATIONS_NETWORK" create
    ensure_volume "${TOPWEBCRM_STORAGE_VOLUME:-topwebcrm_storage}"
    ensure_volume "${TOPWEBCRM_DB_VOLUME:-topwebcrm_db}"
    ensure_volume "${TOPWEBCRM_REDIS_VOLUME:-topwebcrm_redis}"
    if [ "${OPENWA_MODE:-local}" = "local" ]; then
        ensure_volume "${OPENWA_DATA_VOLUME:-openwa_data}"
        ensure_volume "${OPENWA_DB_VOLUME:-openwa_db}"
        ensure_volume "${OPENWA_REDIS_VOLUME:-openwa_redis}"
        ensure_entrypoint_config "$TOPWEBCRM_IMAGE_SHA"
    else
        log "OpenWA remoto: volumes e config do entrypoint dispensados (sem stack local)"
    fi
}
# Gera valor sem ecoar; o chamador deve dar unset após o uso.
gen_hex() { openssl rand -hex "$1"; }
gen_app_key() { printf 'base64:%s' "$(openssl rand -base64 32)"; }

create_secret_stdin() {
    local name="$1" value="$2"
    if secret_exists "$name"; then
        log "secret $name já existe (pulado)"
        return 0
    fi
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] criaria secret $name (valor oculto)"
        return 0
    fi
    printf '%s' "$value" | docker secret create "$name" - >/dev/null
    log "secret $name criado"
}

step_secrets() {
    log "Etapa 4/5 — secrets (issue #52; valores nunca ecoados nem gravados)"
    ask_secret_opt TOPWEBCRM_MAIL_PASSWORD "Senha SMTP"
    if [ -z "${TOPWEBCRM_MAIL_PASSWORD:-}" ]; then
        TOPWEBCRM_MAIL_PASSWORD="$(gen_hex 24)"
        export TOPWEBCRM_MAIL_PASSWORD
        MAIL_CONFIG_PENDING="true"
        warn "senha SMTP não informada: e-mail transacional ficará pendente até a troca do secret"
    else
        MAIL_CONFIG_PENDING="false"
    fi
    ask_secret_opt TOPWEBCRM_ADMIN_PASSWORD "Senha do administrador"
    if [ -z "${TOPWEBCRM_ADMIN_PASSWORD:-}" ]; then
        TOPWEBCRM_ADMIN_PASSWORD="$(gen_hex 24)"
        export TOPWEBCRM_ADMIN_PASSWORD
        if declare -F record_secret >/dev/null; then
            record_secret TOPWEBCRM_ADMIN_PASSWORD "$TOPWEBCRM_ADMIN_PASSWORD"
        fi
        log "senha administrativa forte gerada e registrada em arquivo local protegido"
    fi
    [ "${#TOPWEBCRM_ADMIN_PASSWORD}" -ge 12 ] || die "senha do administrador muito curta"

    local app_key db_pass db_root openwa_master="" openwa_pepper="" openwa_db="" openwa_redis=""
    app_key="$(gen_app_key)"
    # No modo compartilhado a senha do banco nasce no ensure_mysql_db (deploy).
    if [ "${TOPWEBCRM_DATA_MODE:-embutido}" = "compartilhado" ]; then
        db_pass=""
    else
        db_pass="$(gen_hex 24)"
    fi
    db_root="$(gen_hex 24)"
    if [ "${OPENWA_MODE:-local}" = "local" ]; then
        openwa_master="$(gen_hex 32)"
        openwa_pepper="$(gen_hex 32)"
        openwa_db="$(gen_hex 24)"
        openwa_redis="$(gen_hex 24)"
    fi

    create_secret_stdin "${CRM_SECRET_PREFIX}_app_key" "$app_key"
    if [ -n "$db_pass" ]; then
        create_secret_stdin "${CRM_SECRET_PREFIX}_db_password" "$db_pass"
    else
        log "${CRM_SECRET_PREFIX}_db_password diferido: nasce no ensure_mysql_db (modo compartilhado)"
    fi
    create_secret_stdin "${CRM_SECRET_PREFIX}_db_root_password" "$db_root"
    create_secret_stdin "${CRM_SECRET_PREFIX}_mail_password" "$TOPWEBCRM_MAIL_PASSWORD"
    create_secret_stdin "${CRM_SECRET_PREFIX}_admin_password" "$TOPWEBCRM_ADMIN_PASSWORD"
    if [ "${OPENWA_MODE:-local}" = "local" ]; then
        create_secret_stdin "${OPENWA_SECRET_PREFIX}_api_master_key" "$openwa_master"
        create_secret_stdin "${OPENWA_SECRET_PREFIX}_api_key_pepper" "$openwa_pepper"
        create_secret_stdin "${OPENWA_SECRET_PREFIX}_db_password" "$openwa_db"
        create_secret_stdin "${OPENWA_SECRET_PREFIX}_redis_password" "$openwa_redis"
    fi

    unset app_key db_pass db_root openwa_master openwa_pepper openwa_db openwa_redis
}
# Autentica no Portainer e retorna somente o token/header em memória.
portainer_jwt() {
    [ -n "${PORTAINER_USER:-}" ] && [ -n "${PORTAINER_PASS:-}" ] \
        || die "Portainer exige PORTAINER_API_KEY ou usuário/senha"
    curl -fsSL --header 'Content-Type: application/json' \
        --data "$(jq -n --arg u "$PORTAINER_USER" --arg p "$PORTAINER_PASS" '{username:$u,password:$p}')" \
        "$(normalize_url "$PORTAINER_URL")/api/auth" | jq -er '.jwt'
}

portainer_auth_header() {
    if [ -n "${PORTAINER_API_KEY:-}" ]; then
        printf 'X-API-Key: %s' "$PORTAINER_API_KEY"
    else
        printf 'Authorization: Bearer %s' "$(portainer_jwt)"
    fi
}

portainer_endpoint_id() {
    local auth="$1"
    if [ -n "${PORTAINER_ENDPOINT_ID:-}" ]; then
        printf '%s' "$PORTAINER_ENDPOINT_ID"
        return 0
    fi
    curl -fsSL --header "$auth" \
        "$(normalize_url "$PORTAINER_URL")/api/endpoints" \
        | jq -er 'if length == 1 then .[0].Id else ([.[] | select(.Name=="primary")] | if length == 1 then .[0].Id else error("endpoint Portainer ambíguo") end) end'
}

portainer_swarm_id() {
    local auth="$1" endpoint="$2"
    curl -fsSL --header "$auth" \
        "$(normalize_url "$PORTAINER_URL")/api/endpoints/$endpoint/docker/swarm" | jq -er '.ID'
}

portainer_stack_id() {
    local auth="$1" endpoint="$2" name="$3"
    curl -fsSL --header "$auth" \
        "$(normalize_url "$PORTAINER_URL")/api/stacks" \
        | jq -er --arg n "$name" --argjson e "$endpoint" \
            '[.[] | select(.Name==$n and .EndpointId==$e)] | if length == 1 then .[0].Id elif length == 0 then empty else error("stack Portainer duplicada") end'
}

fetch_manifest() {
    curl -fsSL "$RAW_BASE/$TOPWEBCRM_IMAGE_SHA/$1" \
        || die "falha ao baixar o manifest $1 no SHA $TOPWEBCRM_IMAGE_SHA"
}

# Cria ou atualiza a stack (update faz repull + redeploy). $1=nome $2=manifest $3=json-env
deploy_stack() {
    local name="$1" manifest="$2" env_json="$3"
    local auth endpoint swarm stack_id portainer_url
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] criaria/atualizaria stack $name ($(printf '%s' "$manifest" | wc -l) linhas, env $(printf '%s' "$env_json" | jq 'length') vars, com repull)"
        return 0
    fi
    auth="$(portainer_auth_header)"
    portainer_url="$(normalize_url "$PORTAINER_URL")"
    endpoint="$(portainer_endpoint_id "$auth")"
    swarm="$(portainer_swarm_id "$auth" "$endpoint")"
    stack_id="$(portainer_stack_id "$auth" "$endpoint" "$name" || true)"
    if [ -z "$stack_id" ]; then
        log "criando stack $name"
        stack_id="$(jq -n --arg n "$name" --arg f "$manifest" --arg s "$swarm" --argjson e "$env_json" \
            '{Name:$n,StackFileContent:$f,SwarmID:$s,Env:$e,FromAppTemplate:false}' \
            | curl -fsSL --request POST --header "$auth" --header 'Content-Type: application/json' \
                --data-binary @- "$portainer_url/api/stacks/create/swarm/string?endpointId=$endpoint" \
            | jq -er '.Id')"
    fi
    log "aplicando variáveis + repull na stack $name (id $stack_id)"
    jq -n --arg f "$manifest" --argjson e "$env_json" \
        '{StackFileContent:$f, Env:$e, Prune:false, RepullImageAndRedeploy:true}' \
      | curl -fsSL --request PUT --header "$auth" \
        --header 'Content-Type: application/json' --data-binary @- \
        "$portainer_url/api/stacks/$stack_id?endpointId=$endpoint" \
        | jq -e --argjson id "$stack_id" '.Id == $id and .Status == 1' >/dev/null
}

wait_for_stack() {
    local name="$1" timeout="${2:-180}" elapsed=0 services replicas
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] aguardaria stack $name ficar estável"
        return 0
    fi
    while [ "$elapsed" -lt "$timeout" ]; do
        services="$(docker stack services "$name" --format '{{.Replicas}}' 2>/dev/null || true)"
        if [ -n "$services" ]; then
            replicas="$(printf '%s\n' "$services" | grep -Ev '^(1/1|0/0)$' || true)"
            if [ -z "$replicas" ]; then
                log "stack $name estável ($services)"
                return 0
            fi
        fi
        sleep 5
        elapsed=$((elapsed + 5))
    done
    docker stack services "$name" 2>/dev/null || true
    die "stack $name não ficou saudável em ${timeout}s"
}

wait_for_url() {
    local url="$1" timeout="${2:-180}" elapsed=0
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] validaria URL $url"
        return 0
    fi
    while [ "$elapsed" -lt "$timeout" ]; do
        if curl -kfsS --max-time 10 "$url" >/dev/null 2>&1; then
            return 0
        fi
        sleep 5
        elapsed=$((elapsed + 5))
    done
    die "URL não respondeu dentro de ${timeout}s: $url"
}

configure_remote_openwa() {
    [ "${OPENWA_MODE:-local}" = "remoto" ] || return 0
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] configuraria a instância OpenWA remota e o webhook"
        return 0
    fi
    if [ -z "${OPENWA_API_KEY:-}" ]; then
        warn "OpenWA remoto sem API key: instância e webhook precisam ser cadastrados no painel"
        return 0
    fi

    local api_url="$(normalize_url "$OPENWA_REMOTE_URL")" sessions_json="" session_uuid="" session_name=""
    sessions_json="$(curl -fsSL --max-time 20 --header "X-API-Key: $OPENWA_API_KEY" \
        "$api_url/api/sessions")" \
        || { warn "não foi possível listar sessões do OpenWA remoto; CRM implantado sem novo vínculo"; return 0; }

    session_uuid="$(jq -er --arg uuid "${OPENWA_SESSION_UUID:-}" --arg name "${OPENWA_SESSION_NAME:-}" '
        [ .[] | select(($uuid == "" or .id == $uuid) and ($name == "" or .name == $name))
          | select((.status // "") == "ready" and (.engineLoaded // false) == true) ]
        | if length == 1 then .[0].id
          elif length == 0 then error("nenhuma sessão ready compatível")
          else error("mais de uma sessão ready compatível") end' <<<"$sessions_json" 2>/dev/null || true)"
    [ -n "$session_uuid" ] || {
        warn "OpenWA remoto tem sessões incompatíveis/ambíguas; informe OPENWA_SESSION_UUID ou OPENWA_SESSION_NAME"
        return 0
    }
    session_name="$(jq -er --arg id "$session_uuid" '.[] | select(.id == $id) | .name' <<<"$sessions_json")"

    wait_for_url "https://${TOPWEBCRM_DOMAIN}/up" 180
    local cid key_b64 uuid_b64 name_b64 url_b64 php_code
    cid="$(docker ps -q --filter name="${CRM_INST}_topwebcrm_app")"
    [ -n "$cid" ] || { warn "container do TopwebCRM não encontrado para configurar OpenWA"; return 0; }
    key_b64="$(printf '%s' "$OPENWA_API_KEY" | base64 | tr -d '\n')"
    uuid_b64="$(printf '%s' "$session_uuid" | base64 | tr -d '\n')"
    name_b64="$(printf '%s' "$session_name" | base64 | tr -d '\n')"
    url_b64="$(printf '%s' "$api_url" | base64 | tr -d '\n')"
    php_code="\$uuid=base64_decode('$uuid_b64'); \$name=base64_decode('$name_b64'); \$base=base64_decode('$url_b64'); \$instance=\\Webkul\\TopwebChat\\Models\\Instance::updateOrCreate(['session_uuid'=>\$uuid],['name'=>\$name,'provider'=>'openwa','base_url'=>\$base,'token'=>getenv('OPENWA_API_KEY'),'enabled'=>true]); if(!\$instance->webhook_secret){\$instance->webhook_secret=\\Illuminate\\Support\\Str::random(64);\$instance->save();} \$provider=app(\\Webkul\\TopwebChat\\Providers\\Contracts\\MessagingProvider::class); \$url=app(\\Webkul\\TopwebChat\\Services\\WebhookUrlService::class)->forInstance(\$instance); \$webhooks=\$provider->listWebhooks(\$instance); \$matching=array_values(array_filter(\$webhooks,fn(\$w)=>(\$w['url']??'')===\$url)); if(!\$matching){\$provider->configureWebhook(\$instance,\$url,\$instance->webhook_secret);} elseif(count(\$matching)>1){foreach(array_slice(\$matching,1) as \$duplicate){\$provider->deleteWebhook(\$instance,(string)\$duplicate['id']);}} \$instance->update(['last_synced_at'=>now()]); echo \"openwa-instance=\".\$instance->id.\" session=\".\$instance->name.\" webhook=\".\$url.PHP_EOL;"
    printf '%s\n%s\n' "$key_b64" "$php_code" \
        | docker exec -i "$cid" sh -c 'read -r OPENWA_API_KEY_B64; export OPENWA_API_KEY="$(printf %s "$OPENWA_API_KEY_B64" | base64 -d)"; export APP_KEY="$(cat /run/secrets/topwebcrm_app_key)"; export DB_PASSWORD="$(cat /run/secrets/topwebcrm_db_password)"; php artisan tinker'
    unset key_b64 php_code OPENWA_API_KEY
}

# Modo compartilhado (I12.6): garante mysql+redis, cria banco/usuário do CRM
# e o secret com a senha gerada — só quando o secret ainda não existe.
step_shared_data() {
    local initial="$1"
    ensure_tool mysql
    ensure_tool redis
    export TOPWEBCRM_DB_HOST="${TOPWEBCRM_DB_HOST:-mysql_mysql}"
    export TOPWEBCRM_DB_PORT="${TOPWEBCRM_DB_PORT:-3306}"
    export TOPWEBCRM_DB_NAME="${TOPWEBCRM_DB_NAME:-${CRM_INST:-topwebcrm}}"
    export TOPWEBCRM_DB_USER="${TOPWEBCRM_DB_USER:-${CRM_INST:-topwebcrm}}"
    export TOPWEBCRM_DB_REPLICAS="0"
    export TOPWEBCRM_REDIS_HOST="${TOPWEBCRM_REDIS_HOST:-redis_redis}"
    export TOPWEBCRM_REDIS_PORT="${TOPWEBCRM_REDIS_PORT:-6379}"
    export TOPWEBCRM_REDIS_REPLICAS="0"
    if secret_exists "${CRM_SECRET_PREFIX}_db_password"; then
        log "${CRM_SECRET_PREFIX}_db_password já existe (mantido)"
        return 0
    fi
    [ "$initial" = "true" ] || warn "banco compartilhado novo sem secret existente: criando banco/usuário agora"
    ensure_mysql_db "$TOPWEBCRM_DB_NAME" "$TOPWEBCRM_DB_USER"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] criaria secret ${CRM_SECRET_PREFIX}_db_password (valor oculto)"
    else
        printf '%s' "$MYSQL_TOOL_PASSWORD" | docker secret create "${CRM_SECRET_PREFIX}_db_password" - >/dev/null
        log "secret ${CRM_SECRET_PREFIX}_db_password criado a partir do ensure_mysql_db"
    fi
    unset MYSQL_TOOL_PASSWORD
}

step_deploy() {
    log "Etapa 5/5 — deploy (issue #53)"
    local initial="${INITIAL_INSTALL:-false}"

    if [ "${TOPWEBCRM_DATA_MODE:-embutido}" = "compartilhado" ]; then
        step_shared_data "$initial"
    fi

    local crm_manifest openwa_manifest
    crm_manifest="$(fetch_manifest compose.production.yaml)"
    crm_manifest="$(prefix_manifest "$crm_manifest" topwebcrm "$CRM_SECRET_PREFIX")"
    deploy_stack "$CRM_INST" "$crm_manifest" "$(build_crm_env "$initial")"
    wait_for_stack "$CRM_INST"
    wait_for_url "https://${TOPWEBCRM_DOMAIN}/up"
    if [ "$initial" = "true" ]; then
        log "instalação inicial concluída; desligando RUN_INITIAL_INSTALL"
        deploy_stack "$CRM_INST" "$crm_manifest" "$(build_crm_env false)"
        wait_for_stack "$CRM_INST"
    fi

    if [ "${OPENWA_MODE:-local}" = "local" ]; then
        openwa_manifest="$(fetch_manifest compose.openwa.production.yaml)"
        openwa_manifest="$(prefix_manifest "$openwa_manifest" openwa "$OPENWA_SECRET_PREFIX")"
        deploy_stack "$OPENWA_INST" "$openwa_manifest" "$(build_openwa_env)"
        wait_for_stack "$OPENWA_INST"
    else
        configure_remote_openwa
    fi

    log "após o deploy: valide https://$TOPWEBCRM_DOMAIN/up (200), login admin e TopwebChat"
}
# Update/rollback (issue #54) entra como modo: bash SetupThinkin.sh --update | --rollback <sha>

step_summary() {
    local portainer_auth_mode="user-password"
    [ -n "${PORTAINER_API_KEY:-}" ] && portainer_auth_mode="api-key"
    run mkdir -p "$SUMMARY_DIR"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] gravaria $SUMMARY_FILE (só inputs, sem segredos)"
        return 0
    fi
    {
        printf '# dados_topwebcrm — resumo SEM segredos (gerado %s)\n' "$INSTALLER_VERSION"
        printf '%s=%q\n' TOPWEBCRM_INSTANCE "$CRM_INST"
        printf '%s=%q\n' OPENWA_INSTANCE "$OPENWA_INST"
        printf '%s=%q\n' TOPWEBCRM_DOMAIN "$TOPWEBCRM_DOMAIN"
        printf '%s=%q\n' OPENWA_DOMAIN "$OPENWA_DOMAIN"
        printf '%s=%q\n' TOPWEBCRM_APP_NAME "$TOPWEBCRM_APP_NAME"
        printf '%s=%q\n' TOPWEBCRM_ADMIN_NAME "$TOPWEBCRM_ADMIN_NAME"
        printf '%s=%q\n' TOPWEBCRM_ADMIN_EMAIL "$TOPWEBCRM_ADMIN_EMAIL"
        printf '%s=%q\n' TOPWEBCRM_MAIL_HOST "$TOPWEBCRM_MAIL_HOST"
        printf '%s=%q\n' TOPWEBCRM_MAIL_PORT "${TOPWEBCRM_MAIL_PORT:-465}"
        printf '%s=%q\n' TOPWEBCRM_MAIL_ENCRYPTION "${TOPWEBCRM_MAIL_ENCRYPTION:-ssl}"
        printf '%s=%q\n' TOPWEBCRM_MAIL_USERNAME "$TOPWEBCRM_MAIL_USERNAME"
        printf '%s=%q\n' TOPWEBCRM_MAIL_FROM_ADDRESS "$TOPWEBCRM_MAIL_FROM_ADDRESS"
        printf '%s=%q\n' TOPWEBCRM_DATA_MODE "${TOPWEBCRM_DATA_MODE:-embutido}"
        printf '%s=%q\n' TOPWEBCRM_DB_NAME "${TOPWEBCRM_DB_NAME:-topwebcrm}"
        printf '%s=%q\n' TOPWEBCRM_DB_USER "${TOPWEBCRM_DB_USER:-topwebcrm}"
        printf '%s=%q\n' OPENWA_MODE "${OPENWA_MODE:-local}"
        printf '%s=%q\n' OPENWA_REMOTE_URL "${OPENWA_REMOTE_URL:-}"
        printf '%s=%q\n' OPENWA_SESSION_NAME "${OPENWA_SESSION_NAME:-}"
        printf '%s=%q\n' OPENWA_SESSION_UUID "${OPENWA_SESSION_UUID:-}"
        printf '%s=%q\n' PORTAINER_URL "$PORTAINER_URL"
        printf '%s=%q\n' PORTAINER_USER "${PORTAINER_USER:-}"
        printf '%s=%q\n' PORTAINER_AUTH_MODE "$portainer_auth_mode"
        printf '%s=%q\n' TOPWEBCRM_PROXY_NETWORK "$TOPWEBCRM_PROXY_NETWORK"
        printf '%s=%q\n' TOPWEBCRM_INTEGRATIONS_NETWORK "$TOPWEBCRM_INTEGRATIONS_NETWORK"
        printf '%s=%q\n' TOPWEBCRM_NODE_LABEL "$TOPWEBCRM_NODE_LABEL"
        printf '%s=%q\n' OPENWA_IMAGE_TAG "${OPENWA_IMAGE_TAG:-}"
        printf '%s=%q\n' TOPWEBCRM_IMAGE_SHA "${TOPWEBCRM_IMAGE_SHA:-}"
        printf '%s=%q\n' OPENWA_ENTRYPOINT_CONFIG "${OPENWA_ENTRYPOINT_CONFIG:-}"
        printf '%s=%q\n' MAIL_CONFIG_PENDING "${MAIL_CONFIG_PENDING:-false}"
    } > "$SUMMARY_FILE"
    chmod 600 "$SUMMARY_FILE"
    log "resumo em $SUMMARY_FILE"
}

# Carrega inputs de uma instalação anterior (arquivo só com VAR=valor, 600).
load_summary() {
    [ -f "$SUMMARY_FILE" ] || die "resumo $SUMMARY_FILE ausente — rode install primeiro"
    # shellcheck disable=SC1090
    . "$SUMMARY_FILE"
    export TOPWEBCRM_DOMAIN OPENWA_DOMAIN TOPWEBCRM_APP_NAME TOPWEBCRM_ADMIN_NAME \
        TOPWEBCRM_ADMIN_EMAIL TOPWEBCRM_MAIL_HOST TOPWEBCRM_MAIL_PORT \
        TOPWEBCRM_MAIL_ENCRYPTION TOPWEBCRM_MAIL_USERNAME TOPWEBCRM_MAIL_FROM_ADDRESS \
        TOPWEBCRM_DATA_MODE TOPWEBCRM_DB_NAME TOPWEBCRM_DB_USER \
        OPENWA_MODE OPENWA_REMOTE_URL \
        OPENWA_SESSION_NAME OPENWA_SESSION_UUID \
        CRM_INST OPENWA_INST \
        PORTAINER_URL PORTAINER_USER TOPWEBCRM_PROXY_NETWORK \
        TOPWEBCRM_INTEGRATIONS_NETWORK TOPWEBCRM_NODE_LABEL OPENWA_IMAGE_TAG \
        TOPWEBCRM_IMAGE_SHA OPENWA_ENTRYPOINT_CONFIG
    for v in TOPWEBCRM_DOMAIN OPENWA_DOMAIN PORTAINER_URL; do
        [ -n "$(printenv "$v" 2>/dev/null || true)" ] || die "$v ausente no resumo"
    done
    ask_secret_opt PORTAINER_API_KEY "Token API do Portainer"
    if [ -z "${PORTAINER_API_KEY:-}" ]; then
        ask PORTAINER_USER "Usuário admin do Portainer" "${PORTAINER_USER:-admin}" 0
        ask PORTAINER_PASS "Senha do Portainer" "" 1
    fi
}

step_update() {
    log "Update: resolvendo novo SHA do main"
    TOPWEBCRM_IMAGE_SHA="$(resolve_sha)"
    export TOPWEBCRM_IMAGE_SHA
    log "novo SHA: $TOPWEBCRM_IMAGE_SHA"
    step_prereqs
    step_deploy_update_only
    step_summary
}

# Monta o env completo das stacks (update substitui o array inteiro: enviar tudo).
build_crm_env() {
    local init="$1"
    jq -n \
        --arg d "$TOPWEBCRM_DOMAIN" --arg tag "sha-$TOPWEBCRM_IMAGE_SHA" \
        --arg app "${TOPWEBCRM_APP_NAME:-TopwebCRM}" --arg an "${TOPWEBCRM_ADMIN_NAME:-}" --arg ae "${TOPWEBCRM_ADMIN_EMAIL:-}" \
        --arg mh "${TOPWEBCRM_MAIL_HOST:-}" --arg mp "${TOPWEBCRM_MAIL_PORT:-465}" --arg me "${TOPWEBCRM_MAIL_ENCRYPTION:-ssl}" \
        --arg mu "${TOPWEBCRM_MAIL_USERNAME:-}" --arg mf "${TOPWEBCRM_MAIL_FROM_ADDRESS:-}" \
        --arg px "$TOPWEBCRM_PROXY_NETWORK" --arg ix "$TOPWEBCRM_INTEGRATIONS_NETWORK" \
        --arg dbh "${TOPWEBCRM_DB_HOST:-topwebcrm_db}" --arg dbp "${TOPWEBCRM_DB_PORT:-3306}" \
        --arg dbn "${TOPWEBCRM_DB_NAME:-topwebcrm}" --arg dbu "${TOPWEBCRM_DB_USER:-topwebcrm}" \
        --arg dbr "${TOPWEBCRM_DB_REPLICAS:-1}" \
        --arg rh "${TOPWEBCRM_REDIS_HOST:-topwebcrm_redis}" --arg rp "${TOPWEBCRM_REDIS_PORT:-6379}" \
        --arg rr "${TOPWEBCRM_REDIS_REPLICAS:-1}" \
        --arg sv "${TOPWEBCRM_STORAGE_VOLUME:-${CRM_INST:-topwebcrm}_storage}" \
        --arg dv "${TOPWEBCRM_DB_VOLUME:-${CRM_INST:-topwebcrm}_db}" \
        --arg rv "${TOPWEBCRM_REDIS_VOLUME:-${CRM_INST:-topwebcrm}_redis}" \
        --arg init "$init" \
        '[{name:"TOPWEBCRM_DOMAIN",value:$d},{name:"TOPWEBCRM_IMAGE_TAG",value:$tag},
          {name:"TOPWEBCRM_APP_NAME",value:$app},{name:"TOPWEBCRM_ADMIN_NAME",value:$an},
          {name:"TOPWEBCRM_ADMIN_EMAIL",value:$ae},{name:"TOPWEBCRM_MAIL_HOST",value:$mh},
          {name:"TOPWEBCRM_MAIL_PORT",value:$mp},{name:"TOPWEBCRM_MAIL_ENCRYPTION",value:$me},
          {name:"TOPWEBCRM_MAIL_USERNAME",value:$mu},{name:"TOPWEBCRM_MAIL_FROM_ADDRESS",value:$mf},
          {name:"TOPWEBCRM_PROXY_NETWORK",value:$px},{name:"TOPWEBCRM_INTEGRATIONS_NETWORK",value:$ix},
          {name:"TOPWEBCRM_DB_HOST",value:$dbh},{name:"TOPWEBCRM_DB_PORT",value:$dbp},
          {name:"TOPWEBCRM_DB_NAME",value:$dbn},{name:"TOPWEBCRM_DB_USER",value:$dbu},
          {name:"TOPWEBCRM_DB_REPLICAS",value:$dbr},
          {name:"TOPWEBCRM_REDIS_HOST",value:$rh},{name:"TOPWEBCRM_REDIS_PORT",value:$rp},
          {name:"TOPWEBCRM_REDIS_REPLICAS",value:$rr},
          {name:"TOPWEBCRM_STORAGE_VOLUME",value:$sv},{name:"TOPWEBCRM_DB_VOLUME",value:$dv},
          {name:"TOPWEBCRM_REDIS_VOLUME",value:$rv},
          {name:"TOPWEBCRM_INITIAL_INSTALL",value:$init}]'
}

build_openwa_env() {
    jq -n \
        --arg d "$OPENWA_DOMAIN" --arg tag "${OPENWA_IMAGE_TAG:-0.23.3}" \
        --arg cfg "${OPENWA_ENTRYPOINT_CONFIG:-openwa_swarm_entrypoint_v1}" \
        --arg px "$TOPWEBCRM_PROXY_NETWORK" --arg ix "$TOPWEBCRM_INTEGRATIONS_NETWORK" \
        --arg odv "${OPENWA_DATA_VOLUME:-${OPENWA_INST:-openwa}_data}" \
        --arg odb "${OPENWA_DB_VOLUME:-${OPENWA_INST:-openwa}_db}" \
        --arg orv "${OPENWA_REDIS_VOLUME:-${OPENWA_INST:-openwa}_redis}" \
        '[{name:"OPENWA_DOMAIN",value:$d},{name:"OPENWA_IMAGE_TAG",value:$tag},
          {name:"OPENWA_ENTRYPOINT_CONFIG",value:$cfg},
          {name:"TOPWEBCRM_PROXY_NETWORK",value:$px},{name:"TOPWEBCRM_INTEGRATIONS_NETWORK",value:$ix},
          {name:"OPENWA_DATA_VOLUME",value:$odv},{name:"OPENWA_DB_VOLUME",value:$odb},
          {name:"OPENWA_REDIS_VOLUME",value:$orv}]'
}

step_deploy_update_only() {
    log "redeploy das stacks com repull (update)"
    local crm_manifest openwa_manifest
    crm_manifest="$(fetch_manifest compose.production.yaml)"
    crm_manifest="$(prefix_manifest "$crm_manifest" topwebcrm "$CRM_SECRET_PREFIX")"
    deploy_stack "$CRM_INST" "$crm_manifest" "$(build_crm_env false)"
    if [ "${OPENWA_MODE:-local}" = "local" ]; then
        openwa_manifest="$(fetch_manifest compose.openwa.production.yaml)"
        openwa_manifest="$(prefix_manifest "$openwa_manifest" openwa "$OPENWA_SECRET_PREFIX")"
        deploy_stack "$OPENWA_INST" "$openwa_manifest" "$(build_openwa_env)"
    else
        log "OpenWA remoto: stack local ausente, nada a atualizar"
    fi
}

# ---------------------------------------------------------------------------
# Framework de ferramentas — E12/I12.1 (issue #56).
# Modelo SetupOrion: cada ferramenta declara dependências; install resolve
# e instala as deps primeiro. Ferramentas futuras (openwa, minio, ...)
# entram via tool_register + tool_<nome>_install, sem tocar no núcleo.
# ---------------------------------------------------------------------------

declare -A TOOL_DEPS=()      # nome-base -> "dep1 dep2"
declare -A TOOL_DESC=()      # nome-base -> descrição curta
declare -A TOOL_RESOLVING=() # guarda anti-ciclo durante a resolução
declare -A TOOL_DONE=()      # sessão (cobre dry-run, onde o marcador não é gravado)
declare -A TOOL_SUFFIX_OK=() # nome-base -> 1 se aceita sufixo _instância
INSTALLED_MARKER_DIR="${INSTALLED_MARKER_DIR:-$SUMMARY_DIR/.installed}"

tool_register() { # $1=nome $2="deps" $3=descrição [$4=1 aceita sufixo]
    TOOL_DEPS["$1"]="$2"
    TOOL_DESC["$1"]="$3"
    TOOL_SUFFIX_OK["$1"]="${4:-0}"
}

tool_installed() { [ -f "$INSTALLED_MARKER_DIR/$1" ]; }

tool_mark_installed() {
    run mkdir -p "$INSTALLED_MARKER_DIR"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] marcaria ferramenta $1 como instalada"
    else
        : > "$INSTALLED_MARKER_DIR/$1"
    fi
}

# Divide "base_sufixo" (split no ÚLTIMO _). Sem _ = base sem sufixo.
# Saída em BASE/SUFFIX (variáveis globais de resolução).
parse_instance() {
    local inst="$1"
    if [[ "$inst" == *_* ]]; then
        TI_BASE="${inst%_*}"
        TI_SUFFIX="${inst##*_}"
    else
        TI_BASE="$inst"
        TI_SUFFIX=""
    fi
}

# Gate da base (padrão SetupOrion): tudo exceto traefik/portainer exige ambos.
require_base_tools() {
    local missing=""
    tool_installed traefik || stack_exists traefik || [ -n "${TOOL_DONE[traefik]+x}" ] || missing="$missing traefik"
    tool_installed portainer || stack_exists portainer || [ -n "${TOOL_DONE[portainer]+x}" ] || missing="$missing portainer"
    if [ -n "$missing" ]; then
        die "base ausente:$missing — instale primeiro: bash SetupThinkin.sh tool traefik && bash SetupThinkin.sh tool portainer"
    fi
}

# Núcleo da auto-dependência: instala deps primeiro, com guarda anti-ciclo.
# Aceita instância "base" ou "base_sufixo" (multi-instalação estilo SetupOrion);
# o sufixo propaga para deps que o suportam (phpmyadmin_x → mysql_x).
ensure_tool() {
    local inst="$1" base="" suffix="" dep=""
    parse_instance "$inst"
    base="$TI_BASE"
    suffix="$TI_SUFFIX"
    [ -n "${TOOL_DEPS[$base]+x}" ] || die "ferramenta desconhecida: $inst (registradas: ${!TOOL_DEPS[*]})"
    if [ -n "$suffix" ] && [ "${TOOL_SUFFIX_OK[$base]:-0}" != "1" ]; then
        die "ferramenta $base não suporta multi-instância (sufixo _$suffix rejeitado)"
    fi
    if tool_installed "$inst" || [ -n "${TOOL_DONE[$inst]+x}" ]; then
        log "ferramenta $inst já instalada (pulado)"
        return 0
    fi
    [ -z "${TOOL_RESOLVING[$inst]+x}" ] || die "dependência cíclica envolvendo: $inst"
    TOOL_RESOLVING["$inst"]=1
    for dep in ${TOOL_DEPS[$base]}; do
        if [ -n "$suffix" ] && [ "${TOOL_SUFFIX_OK[$dep]:-0}" = "1" ]; then
            ensure_tool "${dep}_${suffix}"
        else
            ensure_tool "$dep"
        fi
    done
    unset 'TOOL_RESOLVING[$inst]'
    if [ "$base" != "traefik" ] && [ "$base" != "portainer" ]; then
        require_base_tools
    fi
    log "instalando ferramenta: $inst (${TOOL_DESC[$base]})"
    TOOL_INSTANCE="$inst" TOOL_SUFFIX="$suffix" TOOL_BASE="$base" TOOL_STACK="$inst" \
    SECRETS_RECORD="$SUMMARY_DIR/.secrets-$inst" \
        "tool_${base}_install"
    TOOL_DONE["$inst"]=1
    tool_mark_installed "$inst"
}

list_tools() {
    local t=""
    log "ferramentas registradas:"
    for t in "${!TOOL_DEPS[@]}"; do
        local multi=""
        [ "${TOOL_SUFFIX_OK[$t]:-0}" = "1" ] && multi=" (multi: ${t}_<nome>)"
        printf '  %-12s deps:[%s]%s %s\n' "$t" "${TOOL_DEPS[$t]}" "$multi" "${TOOL_DESC[$t]}"
    done
}

# Contexto da instância CRM/OpenWA (I12.8, issue #63).
# compose-go NÃO interpola chaves do bloco `secrets:` (validado), por isso os
# manifests mantêm nomes estáticos e o prefixo por instância é aplicado aqui,
# por substituição controlada, antes do upload.
setup_crm_instance() {
    CRM_INST="${TOOL_STACK:-${CRM_INST:-topwebcrm}}"
    OPENWA_INST="${OPENWA_INST:-openwa${TOOL_SUFFIX:+_$TOOL_SUFFIX}}"
    export CRM_INST OPENWA_INST
    export CRM_SECRET_PREFIX="$CRM_INST"
    export OPENWA_SECRET_PREFIX="$OPENWA_INST"
    export TOPWEBCRM_STORAGE_VOLUME="${TOPWEBCRM_STORAGE_VOLUME:-${CRM_INST}_storage}"
    export TOPWEBCRM_DB_VOLUME="${TOPWEBCRM_DB_VOLUME:-${CRM_INST}_db}"
    export TOPWEBCRM_REDIS_VOLUME="${TOPWEBCRM_REDIS_VOLUME:-${CRM_INST}_redis}"
    export OPENWA_DATA_VOLUME="${OPENWA_DATA_VOLUME:-${OPENWA_INST}_data}"
    export OPENWA_DB_VOLUME="${OPENWA_DB_VOLUME:-${OPENWA_INST}_db}"
    export OPENWA_REDIS_VOLUME="${OPENWA_REDIS_VOLUME:-${OPENWA_INST}_redis}"
}

# Aplica prefixo de secrets no manifest (ordem: nomes mais longos primeiro).
prefix_manifest() {
    local content="$1" old="$2" new="$3"
    [ "$old" = "$new" ] && { printf '%s' "$content"; return 0; }
    printf '%s' "$content" \
        | sed -e "s/${old}_db_root_password/${new}_db_root_password/g" \
              -e "s/${old}_db_password/${new}_db_password/g" \
              -e "s/${old}_redis_password/${new}_redis_password/g" \
              -e "s/${old}_api_master_key/${new}_api_master_key/g" \
              -e "s/${old}_api_key_pepper/${new}_api_key_pepper/g" \
              -e "s/${old}_app_key/${new}_app_key/g" \
              -e "s/${old}_mail_password/${new}_mail_password/g" \
              -e "s/${old}_admin_password/${new}_admin_password/g"
}

# TopwebCRM como ferramenta do catálogo (reusa o fluxo E11).
tool_topwebcrm_install() {
    setup_crm_instance
    if stack_exists "$CRM_INST"; then
        log "stack $CRM_INST já existe (mantida; use update para trocar a imagem)"
        return 0
    fi
    step_prereqs
    step_secrets
    step_deploy
}

tool_register topwebcrm "traefik portainer" "CRM + OpenWA (bootstrap, prereqs, secrets, deploy)" 1

# ---------------------------------------------------------------------------
# I12.2 — traefik + portainer (+update) — issue #57.
# Bootstrap via `docker stack deploy` direto (Portainer ainda pode não existir,
# como no SetupOrion [01]). Update = reinstalar (idempotente por construção).
# ---------------------------------------------------------------------------

tool_traefik_collect() {
    discover_install_defaults
    ask TRAEFIK_NETWORK   "Rede overlay externa do proxy" "${TOPWEBCRM_PROXY_NETWORK:-topweb_proxy}" 0
    ask TRAEFIK_SSL_EMAIL "E-mail do Let's Encrypt" "" 0
    ask TRAEFIK_IMAGE     "Imagem do Traefik" "traefik:v3.5" 0
    ask_opt TRAEFIK_DASHBOARD_DOMAIN "Domínio do dashboard"
    if [ -n "${TRAEFIK_DASHBOARD_DOMAIN:-}" ]; then
        ask TRAEFIK_DASHBOARD_USERS "Linha htpasswd do dashboard (gere com: htpasswd -nbB admin)" "" 0
    fi
}

tool_traefik_install() {
    if stack_exists traefik; then
        log "stack traefik já existe (mantida)"
        return 0
    fi
    local users_line="${TRAEFIK_DASHBOARD_USERS:-}"

    local dash_labels=""
    if [ -n "${TRAEFIK_DASHBOARD_DOMAIN:-}" ]; then
        dash_labels=$(cat <<EOF
      - traefik.http.routers.traefik.rule=Host(\`${TRAEFIK_DASHBOARD_DOMAIN:-}\`)
      - traefik.http.routers.traefik.entrypoints=websecure
      - traefik.http.routers.traefik.tls=true
      - traefik.http.routers.traefik.tls.certresolver=letsencryptresolver
      - traefik.http.routers.traefik.service=api@internal
      - traefik.http.routers.traefik.middlewares=traefik-auth
      - traefik.http.middlewares.traefik-auth.basicauth.users=$users_line
EOF
)
    fi
    local manifest=""
    manifest="$(cat <<EOF
services:
  traefik:
    image: $TRAEFIK_IMAGE
    command:
      - --api.dashboard=true
      - --providers.docker=true
      - --providers.docker.swarmMode=true
      - --providers.docker.network=$TRAEFIK_NETWORK
      - --providers.docker.exposedbydefault=false
      - --entrypoints.web.address=:80
      - --entrypoints.web.http.redirections.entrypoint.to=websecure
      - --entrypoints.web.http.redirections.entrypoint.scheme=https
      - --entrypoints.websecure.address=:443
      - --certificatesresolvers.letsencryptresolver.acme.email=$TRAEFIK_SSL_EMAIL
      - --certificatesresolvers.letsencryptresolver.acme.storage=/letsencrypt/acme.json
      - --certificatesresolvers.letsencryptresolver.acme.httpchallenge.entrypoint=web
    ports:
      - target: 80
        published: 80
        mode: host
      - target: 443
        published: 443
        mode: host
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik_letsencrypt:/letsencrypt
    networks:
      - proxy
    deploy:
      mode: global
      placement:
        constraints: [node.role == manager]
      labels:
        - traefik.enable=true
$dash_labels
networks:
  proxy:
    external: true
    name: $TRAEFIK_NETWORK
volumes:
  traefik_letsencrypt:
    external: true
    name: \${TRAEFIK_LETSENCRYPT_VOLUME:-traefik_letsencrypt}
EOF
)"
    ensure_network "$TRAEFIK_NETWORK" create
    if ! volume_exists "${TRAEFIK_LETSENCRYPT_VOLUME:-traefik_letsencrypt}"; then
        run docker volume create "${TRAEFIK_LETSENCRYPT_VOLUME:-traefik_letsencrypt}"
    else
        log "volume letsencrypt já existe"
    fi
    deploy_stack_file traefik "$manifest"
    wait_for_stack traefik
}

# Deploy de manifest local via CLI (bootstrap; Portainer pode não existir ainda).
deploy_stack_file() {
    local name="$1" manifest="$2" tmpfile=""
    tmpfile="$(mktemp)"
    printf '%s' "$manifest" > "$tmpfile"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] docker stack deploy -c <manifest $(printf '%s' "$manifest" | wc -l) linhas> $name"
        rm -f "$tmpfile"
        return 0
    fi
    run docker stack deploy -c "$tmpfile" "$name"
    rm -f "$tmpfile"
}

tool_portainer_collect() {
    ask PORTAINER_DOMAIN "Domínio do Portainer (https://...)" "" 0
    ask PORTAINER_USER   "Usuário admin do Portainer" "admin" 0
    ask PORTAINER_PASS   "Senha do admin (min. 12)" "" 1
    [ "${#PORTAINER_PASS}" -ge 12 ] || die "senha muito curta"
    ask PORTAINER_IMAGE  "Imagem do Portainer" "portainer/portainer-ce:latest" 0
    ask PORTAINER_AGENT_IMAGE "Imagem do agent" "portainer/agent:latest" 0
}

tool_portainer_install() {
    if stack_exists portainer; then
        log "stack portainer já existe (mantida)"
        return 0
    fi

    local manifest=""
    manifest="$(cat <<EOF
services:
  agent:
    image: $PORTAINER_AGENT_IMAGE
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /var/lib/docker/volumes:/var/lib/docker/volumes
    networks:
      - proxy
    deploy:
      mode: global
      placement:
        constraints: [node.platform.os == linux]
  app:
    image: $PORTAINER_IMAGE
    command: -H tcp://tasks.agent:9001 --tlsskipverify
    volumes:
      - portainer_data:/data
    networks:
      - proxy
    deploy:
      mode: replicated
      replicas: 1
      placement:
        constraints: [node.role == manager]
      labels:
        - traefik.enable=true
        - traefik.http.routers.portainer.rule=Host(\`$PORTAINER_DOMAIN\`)
        - traefik.http.routers.portainer.entrypoints=websecure
        - traefik.http.routers.portainer.tls=true
        - traefik.http.routers.portainer.tls.certresolver=letsencryptresolver
        - traefik.http.services.portainer.loadbalancer.server.port=9000
networks:
  proxy:
    external: true
    name: ${TRAEFIK_NETWORK:-renacesso}
volumes:
  portainer_data:
    external: true
    name: \${PORTAINER_DATA_VOLUME:-portainer_data}
EOF
)"
    if ! volume_exists "${PORTAINER_DATA_VOLUME:-portainer_data}"; then
        run docker volume create "${PORTAINER_DATA_VOLUME:-portainer_data}"
    else
        log "volume portainer_data já existe"
    fi
    deploy_stack_file portainer "$manifest"
    wait_for_stack portainer
    wait_for_url "$(normalize_url "$PORTAINER_DOMAIN")/api/status" 180
    portainer_admin_init
}

# Inicializa o admin numa instalação fresca (idempotente: 409 = já existe).
portainer_admin_init() {
    local url="$(normalize_url "$PORTAINER_DOMAIN")"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] POST $url/api/users/admin/init (idempotente)"
        return 0
    fi
    local code="" attempt=0
    while [ "$attempt" -lt 12 ]; do
        code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -X POST \
            -H 'Content-Type: application/json' \
            -d "$(jq -n --arg u "$PORTAINER_USER" --arg p "$PORTAINER_PASS" '{username:$u,password:$p}')" \
            "$url/api/users/admin/init" || true)"
        case "$code" in
            200) log "admin do Portainer criado"; return 0 ;;
            409) log "admin do Portainer já existe (mantido)"; return 0 ;;
        esac
        sleep 5
        attempt=$((attempt + 1))
    done
    die "init do Portainer não concluiu após as tentativas (último HTTP $code)"
}

tool_register traefik "" "Proxy reverso + TLS (bootstrap via CLI)"
tool_register portainer "traefik" "Gestão visual (dashboard atrás do Traefik)"

# ---------------------------------------------------------------------------
# I12.3 — mysql + redis compartilhados — issue #58.
# Modelo SetupOrion: UMA instalação, MESMO root, um banco/usuário por ferramenta.
# O root vive em arquivo 600 só-root (necessário ao ensure_db); nada vai a log.
# ---------------------------------------------------------------------------
SECRETS_RECORD="${SECRETS_RECORD:-$SUMMARY_DIR/.secrets}"

record_secret() { # $1=nome $2=valor — arquivo 600, nunca ecoado
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] registraria segredo $1 (valor oculto)"
        return 0
    fi
    run mkdir -p "$(dirname "$SECRETS_RECORD")"
    touch "$SECRETS_RECORD"
    chmod 600 "$SECRETS_RECORD"
    grep -q "^$1=" "$SECRETS_RECORD" 2>/dev/null \
        && die "segredo $1 já registrado (reuso manual exige rotação explícita)"
    printf '%s=%s\n' "$1" "$2" >> "$SECRETS_RECORD"
}

read_secret() { # $1=nome — ecoa o valor (chamador dá unset)
    grep "^$1=" "$SECRETS_RECORD" 2>/dev/null | cut -d= -f2- || true
}

tool_mysql_collect() {
    # Root SEMPRE gerado (openssl); operador consulta via `show mysql[_sufixo]`.
    ask MYSQL_IMAGE "Imagem do MySQL" "mysql:8.0" 0
    ask MYSQL_SHARED_NETWORK "Rede overlay dos dados compartilhados" "${TOPWEBCRM_INTEGRATIONS_NETWORK:-shared_data}" 0
    export MYSQL_SHARED_NETWORK
}

tool_mysql_install() {
    local stack="${TOOL_STACK:-mysql}"
    local root_secret="mysql_root_password"
    [ "$stack" != "mysql" ] && root_secret="${stack}_root_password"
    if stack_exists "$stack"; then
        log "stack $stack já existe (mantida)"
        return 0
    fi
    export MYSQL_SHARED_NETWORK

    if ! network_exists "$MYSQL_SHARED_NETWORK"; then
        run docker network create --driver overlay --attachable "$MYSQL_SHARED_NETWORK"
    fi
    if ! volume_exists "${MYSQL_DATA_VOLUME:-mysql_data}"; then
        run docker volume create "${MYSQL_DATA_VOLUME:-mysql_data}"
    fi
    local manifest=""
    manifest="$(cat <<EOF
services:
  mysql:
    image: $MYSQL_IMAGE
    command: --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
    environment:
      MYSQL_ROOT_PASSWORD_FILE: /run/secrets/$root_secret
    secrets:
      - $root_secret
    volumes:
      - mysql_data:/var/lib/mysql
    networks:
      - shared
    deploy:
      replicas: 1
      placement:
        constraints: [node.role == manager]
networks:
  shared:
    external: true
    name: $MYSQL_SHARED_NETWORK
volumes:
  mysql_data:
    external: true
    name: ${MYSQL_DATA_VOLUME:-${stack}_data}
secrets:
  $root_secret:
    external: true
EOF
)"
    # Root gerado ANTES de qualquer uso (ordem importa: secret Swarm + registro).
    local mysql_root=""
    mysql_root="$(gen_hex 24)"
    record_secret MYSQL_ROOT_PASSWORD "$mysql_root"
    # Secret do Swarm antes do deploy (stdin, sem eco).
    if ! secret_exists "$root_secret"; then
        if [ "$DRY_RUN" = "1" ]; then
            log "[dry-run] criaria secret $root_secret (valor oculto)"
        else
            printf '%s' "$mysql_root" | docker secret create "$root_secret" - >/dev/null
        fi
    fi
    unset mysql_root
    tool_config_set "${stack}_SHARED_NETWORK" "$MYSQL_SHARED_NETWORK"
    tool_config_set "${stack}_SVC" "${stack}_mysql"
    deploy_stack_file "$stack" "$manifest"
}

# Cria banco+usuário da ferramenta no mysql da instância (idempotente).
# Uso: ensure_mysql_db <banco> <usuário> [instância-mysql, default "mysql"].
# Senha do usuário sai em MYSQL_TOOL_PASSWORD (unset após uso).
ensure_mysql_db() {
    local db="$1" user="$2"
    local mysql_inst="${3:-mysql}"
    local root="" pass="" net="" host=""
    local saved_record="$SECRETS_RECORD"
    SECRETS_RECORD="$SUMMARY_DIR/.secrets-$mysql_inst"
    root="$(read_secret MYSQL_ROOT_PASSWORD)"
    SECRETS_RECORD="$saved_record"
    [ -n "$root" ] || die "root do mysql ($mysql_inst) não registrado — instale a ferramenta mysql${mysql_inst#mysql} primeiro"
    net="$(tool_config_get "${mysql_inst}_SHARED_NETWORK")"
    net="${net:-${MYSQL_SHARED_NETWORK:-shared_data}}"
    host="$(tool_config_get "${mysql_inst}_SVC")"
    host="${host:-${mysql_inst}_mysql}"
    pass="$(gen_hex 24)"
    local sql="CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    sql="$sql CREATE USER IF NOT EXISTS '$user'@'%' IDENTIFIED BY '$pass';"
    sql="$sql GRANT ALL PRIVILEGES ON \`$db\`.* TO '$user'@'%'; FLUSH PRIVILEGES;"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] criaria banco $db + usuário $user em $host (rede $net)"
    else
        docker run --rm --network "$net" \
            "${MYSQL_IMAGE:-mysql:8.0}" \
            mysql -h"$host" -uroot -p"$root" -e "$sql" >/dev/null
        log "banco $db + usuário $user prontos em $host"
    fi
    printf -v MYSQL_TOOL_PASSWORD '%s' "$pass"
    export MYSQL_TOOL_PASSWORD
    unset root pass
}

tool_redis_collect() {
    ask REDIS_IMAGE "Imagem do Redis" "redis:7.4-alpine" 0
    ask REDIS_PASSWORD "Senha do Redis (vazio = sem senha; com senha ela aparece no spec do serviço)" "" 1
    ask REDIS_SHARED_NETWORK "Rede overlay dos dados compartilhados" "${TOPWEBCRM_INTEGRATIONS_NETWORK:-shared_data}" 0
    export REDIS_SHARED_NETWORK
}

tool_redis_install() {
    local stack="${TOOL_STACK:-redis}"
    if stack_exists "$stack"; then
        log "stack $stack já existe (mantida)"
        return 0
    fi
    [ -n "$REDIS_PASSWORD" ] && record_secret REDIS_PASSWORD "$REDIS_PASSWORD"
    local redis_cmd="redis-server --appendonly yes"
    if [ -n "$REDIS_PASSWORD" ]; then
        warn "senha do Redis ficará visível em 'docker service inspect' (limitação do redis-server)"
        redis_cmd="$redis_cmd --requirepass $REDIS_PASSWORD"
    else
        log "Redis sem senha: isolamento = rede overlay interna (sem porta publicada)"
    fi
    unset REDIS_PASSWORD

    if ! network_exists "$REDIS_SHARED_NETWORK"; then
        run docker network create --driver overlay --attachable "$REDIS_SHARED_NETWORK"
    fi
    if ! volume_exists "${REDIS_DATA_VOLUME:-${stack}_data}"; then
        run docker volume create "${REDIS_DATA_VOLUME:-${stack}_data}"
    fi
    local manifest=""
    manifest="$(cat <<EOF
services:
  redis:
    image: $REDIS_IMAGE
    command: $redis_cmd
    volumes:
      - redis_data:/data
    networks:
      - shared
    deploy:
      replicas: 1
      placement:
        constraints: [node.role == manager]
networks:
  shared:
    external: true
    name: $REDIS_SHARED_NETWORK
volumes:
  redis_data:
    external: true
    name: ${REDIS_DATA_VOLUME:-${stack}_data}
EOF
)"
    deploy_stack_file "$stack" "$manifest"
}

tool_register mysql "" "Banco compartilhado (root único, um banco por ferramenta)" 1
tool_register redis "" "Cache/fila compartilhado" 1

# ---------------------------------------------------------------------------
# I12.4 — phpMyAdmin (depende mysql) — issue #59.
# Prova do auto-install: `ensure_tool phpmyadmin` sem mysql instala o mysql
# antes. Config de runtime das ferramentas em $SUMMARY_DIR/.tools (sem segredos).
# ---------------------------------------------------------------------------
TOOLS_RECORD="${TOOLS_RECORD:-$SUMMARY_DIR/.tools}"

tool_config_set() { # $1=CHAVE $2=valor
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] registraria config $1 (ferramenta)"
        return 0
    fi
    run mkdir -p "$(dirname "$TOOLS_RECORD")"
    touch "$TOOLS_RECORD"
    chmod 600 "$TOOLS_RECORD"
    grep -v "^$1=" "$TOOLS_RECORD" 2>/dev/null > "$TOOLS_RECORD.tmp" || true
    mv "$TOOLS_RECORD.tmp" "$TOOLS_RECORD"
    printf '%s=%s\n' "$1" "$2" >> "$TOOLS_RECORD"
}

tool_config_get() { # $1=CHAVE
    grep "^$1=" "$TOOLS_RECORD" 2>/dev/null | cut -d= -f2- || true
}

tool_phpmyadmin_collect() {
    ask PMA_DOMAIN "Domínio do phpMyAdmin (https://...)" "" 0
    ask PMA_IMAGE  "Imagem do phpMyAdmin" "phpmyadmin:5.2" 0
}

tool_phpmyadmin_install() {
    local stack="${TOOL_STACK:-phpmyadmin}"
    local suffix="${TOOL_SUFFIX:-}"
    local mysql_inst="mysql${suffix:+_$suffix}"
    local mysql_net="" mysql_svc=""
    mysql_net="$(tool_config_get "${mysql_inst}_SHARED_NETWORK")"
    mysql_net="${mysql_net:-${MYSQL_SHARED_NETWORK:-shared_data}}"
    mysql_svc="$(tool_config_get "${mysql_inst}_SVC")"
    mysql_svc="${mysql_svc:-${mysql_inst}_mysql}"
    log "phpMyAdmin vai falar com $mysql_svc via rede $mysql_net"

    local manifest=""
    manifest="$(cat <<EOF
services:
  phpmyadmin:
    image: $PMA_IMAGE
    environment:
      PMA_HOST: $mysql_svc
      PMA_PORT: 3306
      PMA_ARBITRARY: 1
      UPLOAD_LIMIT: 512M
    networks:
      - proxy
      - shared
    deploy:
      replicas: 1
      placement:
        constraints: [node.role == manager]
      labels:
        - traefik.enable=true
        - traefik.http.routers.phpmyadmin.rule=Host(\`$PMA_DOMAIN\`)
        - traefik.http.routers.phpmyadmin.entrypoints=websecure
        - traefik.http.routers.phpmyadmin.tls=true
        - traefik.http.routers.phpmyadmin.tls.certresolver=letsencryptresolver
        - traefik.http.services.phpmyadmin.loadbalancer.server.port=80
networks:
  proxy:
    external: true
    name: ${TRAEFIK_NETWORK:-renacesso}
  shared:
    external: true
    name: $mysql_net
EOF
)"
    deploy_stack_file "$stack" "$manifest"
    tool_config_set "${stack}_DOMAIN" "$PMA_DOMAIN"
    log "login com o usuário/senha do banco da ferramenta (criados via ensure_mysql_db)"
}

tool_register phpmyadmin "mysql traefik" "Gestão MySQL (auto-instala mysql + traefik)" 1

# ---------------------------------------------------------------------------
# I12.7 — apps PHP convencionais fora do Swarm — issue #62.
# Um app por sufixo (site, automação, WP...): `tool phpapp_finanbot`.
# Docroot em bind físico permanente (/var/www/<site>: edições manuais e
# .htaccess sobrevivem a restarts); imagem buildada localmente (PHP na versão
# pedida + extensões); redes compartilhadas sob demanda (mysql/redis/minio).
# Restrição honesta: bind + build local = nó único (mesmo modelo dos volumes
# locais do TopwebCRM).
# ---------------------------------------------------------------------------

tool_phpapp_collect() {
    [ -n "${TOOL_SUFFIX:-}" ] || die "nome do app obrigatório: use tool phpapp_<nome> (ex. phpapp_finanbot)"
    local site="$TOOL_SUFFIX"
    ask_opt PHPAPP_DOMAIN "Domínio do site"
    ask PHP_VERSION "Versão do PHP" "8.2" 0
    ask PHP_EXTENSIONS "Extensões (csv; ex. mysqli,pdo_mysql,redis,opcache,zip,gd)" "mysqli,pdo_mysql,opcache" 0
    ask PHPAPP_DIR "Docroot físico no host" "/var/www/$site" 0
    ask PHPAPP_WANT_MYSQL "Precisa de MySQL? (s/n)" "n" 0
    ask PHPAPP_WANT_REDIS "Precisa de Redis? (s/n)" "n" 0
    ask PHPAPP_WANT_S3 "Precisa de S3/MinIO? (s/n)" "n" 0
    if [[ "$PHPAPP_WANT_S3" == [sSyY]* ]]; then
        ask PHPAPP_S3_ENDPOINT "Endpoint S3 (http://minio:9000)" "http://minio:9000" 0
        ask PHPAPP_S3_BUCKET "Bucket" "" 0
        ask PHPAPP_S3_KEY "Access key" "" 0
        ask PHPAPP_S3_SECRET "Secret key" "" 1
    fi
}

tool_phpapp_install() {
    local stack="${TOOL_STACK:-phpapp}"
    local site="${TOOL_SUFFIX:-$stack}"
    [ "$site" != "phpapp" ] || die "nome do app obrigatório: use tool phpapp_<nome>"
    if stack_exists "$stack"; then
        log "stack $stack já existe (mantida)"
        return 0
    fi
    local image="phpapp-$site:local"
    local build_dir="$SUMMARY_DIR/build-$stack"
    local ext_list="$PHP_EXTENSIONS"
    # Base compartilhada por hash (versão+extensões): build uma vez, N sites usam.
    # Layers do Docker já evitam re-download; isto evita recompilar extensões.
    local base_tag="phpapp-base:$(printf '%s|%s' "$PHP_VERSION" "$ext_list" | sha256sum | cut -c1-12)"
    local base_dockerfile="$SUMMARY_DIR/build-base/Dockerfile.$base_tag"

    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] base $base_tag (php:$PHP_VERSION + $ext_list); sites FROM ela"
    else
        run mkdir -p "$SUMMARY_DIR/build-base" "$build_dir" "$PHPAPP_DIR"
        if docker image inspect "$base_tag" >/dev/null 2>&1; then
            log "base $base_tag já existe (reutilizada)"
        else
            {
                echo "FROM php:${PHP_VERSION}-apache"
                echo "RUN apt-get update && apt-get install -y libzip-dev libpng-dev libonig-dev \\"
                echo " && docker-php-ext-install $ext_list \\"
                echo " && a2enmod rewrite headers && apt-get clean && rm -rf /var/lib/apt/lists"
                echo 'RUN sed -i "s/AllowOverride None/AllowOverride All/g" /etc/apache2/apache2.conf'
            } > "$base_dockerfile"
            run docker build -t "$base_tag" -f "$base_dockerfile" "$SUMMARY_DIR/build-base"
        fi
        printf 'FROM %s\n' "$base_tag" > "$build_dir/Dockerfile"
        run docker build -t "$image" "$build_dir"
        [ -f "$PHPAPP_DIR/index.php" ] || [ -f "$PHPAPP_DIR/index.html" ] || \
            printf '<?php echo "OK %s";\n' "$site" > "$PHPAPP_DIR/index.php"
    fi

    local networks="      - proxy"
    local extra_nets=""
    local envs=""
    if [[ "${PHPAPP_WANT_MYSQL:-n}" == [sSyY]* ]]; then
        local db="db_${site}" user="user_${site}"
        ensure_mysql_db "$db" "$user" "mysql"
        warn "DB_PASSWORD ficará visível em 'docker service inspect' (limitação de env Swarm)"
        envs="$envs
      - DB_HOST=mysql_mysql
      - DB_DATABASE=$db
      - DB_USERNAME=$user
      - DB_PASSWORD=$MYSQL_TOOL_PASSWORD"
        unset MYSQL_TOOL_PASSWORD
        networks="$networks
      - shared_mysql"
        extra_nets="$extra_nets
  shared_mysql:
    external: true
    name: ${MYSQL_SHARED_NETWORK:-shared_data}"
    fi
    if [[ "${PHPAPP_WANT_REDIS:-n}" == [sSyY]* ]]; then
        envs="$envs
      - REDIS_HOST=redis_redis"
        networks="$networks
      - shared_redis"
        extra_nets="$extra_nets
  shared_redis:
    external: true
    name: ${REDIS_SHARED_NETWORK:-shared_data}"
    fi
    if [[ "${PHPAPP_WANT_S3:-n}" == [sSyY]* ]]; then
        warn "S3_KEY ficará visível em 'docker service inspect' (limitação de env Swarm)"
        envs="$envs
      - S3_ENDPOINT=$PHPAPP_S3_ENDPOINT
      - S3_BUCKET=$PHPAPP_S3_BUCKET
      - S3_KEY=$PHPAPP_S3_KEY"
    fi

    local traefik_labels="        - traefik.enable=false"
    if [ -n "${PHPAPP_DOMAIN:-}" ]; then
        traefik_labels="        - traefik.enable=true
        - traefik.http.routers.$stack.rule=Host(\`$PHPAPP_DOMAIN\`)
        - traefik.http.routers.$stack.entrypoints=websecure
        - traefik.http.routers.$stack.tls=true
        - traefik.http.routers.$stack.tls.certresolver=letsencryptresolver
        - traefik.http.services.$stack.loadbalancer.server.port=80"
    fi
    local manifest=""
    manifest="$(cat <<EOF
services:
  php:
    image: $image
    environment:$envs
    volumes:
      - $PHPAPP_DIR:/var/www/html
    networks:
      - proxy
$networks
    deploy:
      replicas: 1
      placement:
        constraints: [node.role == manager]
      labels:
$traefik_labels
    healthcheck:
      test: ["CMD-SHELL", "bash -c '</dev/tcp/127.0.0.1/80'"]
      interval: 30s
      timeout: 5s
      retries: 3
networks:
  proxy:
    external: true
    name: ${TRAEFIK_NETWORK:-renacesso}$extra_nets
EOF
)"
    deploy_stack_file "$stack" "$manifest"
    tool_config_set "${stack}_DOCROOT" "$PHPAPP_DIR"
    tool_config_set "${stack}_DOMAIN" "${PHPAPP_DOMAIN:-}"
    log "arquivos em $PHPAPP_DIR (.htaccess gerenciável no disco)"
}

tool_register phpapp "" "App PHP convencional fora do Swarm (multi: phpapp_<nome>)" 1

# Higiene final: segredos só vivem na memória durante a execução.
cleanup_secrets() {
    unset PORTAINER_PASS TOPWEBCRM_MAIL_PASSWORD TOPWEBCRM_ADMIN_PASSWORD \
        PORTAINER_API_KEY OPENWA_API_KEY MYSQL_ROOT_PASSWORD REDIS_PASSWORD \
        MYSQL_TOOL_PASSWORD 2>/dev/null || true
    log "segredos removidos da memória"
}

# Coleta upfront (recursiva nas deps): nenhuma pergunta durante a execução.
# Convenção: tool_<base>_collect existe quando a ferramenta tem inputs.
declare -A TOOL_COLLECTED=()
collect_tool_tree() {
    local inst="$1" base="$1"
    if [[ "$inst" == *_* ]]; then base="${inst%_*}"; fi
    [ -n "${TOOL_DEPS[$base]+x}" ] || die "ferramenta desconhecida: $inst"
    [ -z "${TOOL_COLLECTED[$inst]+x}" ] || return 0
    TOOL_COLLECTED["$inst"]=1
    local dep=""
    for dep in ${TOOL_DEPS[$base]}; do
        [ -n "$dep" ] || continue
        if [[ "$inst" == *_* ]] && [ "${TOOL_SUFFIX_OK[$dep]:-0}" = "1" ]; then
            collect_tool_tree "${dep}_${inst##*_}"
        else
            collect_tool_tree "$dep"
        fi
    done
    if declare -F "tool_${base}_collect" >/dev/null; then
        log "coletando inputs: $inst"
        if [[ "$inst" == *_* ]]; then
            TOOL_SUFFIX="${inst##*_}" TOOL_STACK="$inst" TOOL_BASE="$base" "tool_${base}_collect"
        else
            "tool_${base}_collect"
        fi
    fi
}

# ---------------------------------------------------------------------------
# Comandos operacionais (I12.9) — status, urls, logs, update, versions.
# Espelham os "comandos ocultos" do Orion (menu_comandos), sem telemetria.
# ---------------------------------------------------------------------------

cmd_status() {
    log "stacks:"
    docker stack ls --format '  {{.Name}} ({{.Services}} serviços)' 2>/dev/null || die "sem acesso ao Swarm"
    log "serviços não saudáveis (replicas divergentes):"
    docker service ls --format '{{.Name}} {{.Replicas}}' 2>/dev/null \
        | awk '$2 != "1/1" && $2 != "0/0" {print "  " $0}' || true
}

cmd_urls() {
    log "URLs registradas (resumos locais; podem divergir do Traefik):"
    grep -h -E '^(TOPWEBCRM_DOMAIN|OPENWA_DOMAIN|PORTAINER_DOMAIN|PMA_DOMAIN)=' \
        "$SUMMARY_DIR"/dados_* "$SUMMARY_DIR"/.tools 2>/dev/null \
        | sed -E 's/^([A-Z_]+)=(.*)$/  \1 → https:\/\/\2/' || true
}

cmd_logs() {
    local svc="${1:-}" tail="${2:-100}"
    [ -n "$svc" ] || die "uso: bash SetupThinkin.sh logs <stack_serviço> [linhas]"
    docker service logs --tail "$tail" --no-trunc "$svc" 2>&1 | tail -n "$tail"
}

# Digest remoto (anônimo) para comparar com o local. $1=imagem (com tag).
registry_digest() {
    local image="$1" repo="" tag="" host="" token="" digest=""
    tag="${image##*:}"; repo="${image%:*}"
    case "$repo" in
        ghcr.io/*) host="ghcr.io"; repo="${repo#ghcr.io/}" ;;
        */*)       host="registry-1.docker.io" ;;
        *)         host="registry-1.docker.io"; repo="library/$repo" ;;
    esac
    if [ "$host" = "ghcr.io" ]; then
        token="$(curl -fsSL "https://ghcr.io/token?scope=repository:$repo:pull" | jq -er '.token')"
        digest="$(curl -fsSLI -H "Authorization: Bearer $token" \
            -H 'Accept: application/vnd.oci.image.index.v1+json' \
            "https://ghcr.io/v2/$repo/manifests/$tag" | tr -d '\r' | grep -i '^docker-content-digest:' | awk '{print $2}')"
    else
        token="$(curl -fsSL "https://auth.docker.io/token?service=registry.docker.io&scope=repository:$repo:pull" | jq -er '.token')"
        digest="$(curl -fsSLI -H "Authorization: Bearer $token" \
            -H 'Accept: application/vnd.docker.distribution.manifest.list.v2+json' \
            "https://registry-1.docker.io/v2/$repo/manifests/$tag" | tr -d '\r' | grep -i '^docker-content-digest:' | awk '{print $2}')"
    fi
    printf '%s' "$digest"
}

cmd_versions() {
    local only="${1:-}"
    local stack=""
    log "legenda: ok = digest local igual ao registry; ATUALIZAR = divergente; ? = sem imagem local para comparar"
    for stack in $(docker stack ls --format '{{.Name}}' 2>/dev/null); do
        [ -n "$only" ] && [ "$stack" != "$only" ] && continue
        printf 'stack %s\n' "$stack"
        docker stack services "$stack" --format '{{.Name}} {{.Image}}' 2>/dev/null | while read -r svc image; do
            local local_digest="" remote=""
            local_digest="$(docker image inspect --format '{{index .RepoDigests 0}}' "$image" 2>/dev/null || true)"
            remote="$(registry_digest "$image" 2>/dev/null || true)"
            local state="?"
            if [ -n "$local_digest" ] && [ -n "$remote" ]; then
                if [ "${local_digest##*@}" = "$remote" ]; then state="ok"; else state="ATUALIZAR"; fi
            fi
            printf '  %-40s %-50s %s\n' "$svc" "$image" "$state"
        done
    done
}

# Update com repull via API do Portainer, preservando manifest+env (I12.9/#54).
cmd_update_stack() {
    local name="${1:-}"
    [ -n "$name" ] || die "uso: bash SetupThinkin.sh update <stack>"
    ask PORTAINER_URL "URL base do Portainer (https://...)" "" 0
    PORTAINER_URL="$(normalize_url "$PORTAINER_URL")"
    ask_secret_opt PORTAINER_API_KEY "Token API do Portainer"
    if [ -z "${PORTAINER_API_KEY:-}" ]; then
        ask PORTAINER_USER "Usuário admin do Portainer" "admin" 0
        ask PORTAINER_PASS "Senha do Portainer" "" 1
    fi
    local auth endpoint stack_id file env_json
    auth="$(portainer_auth_header)"
    endpoint="$(portainer_endpoint_id "$auth")"
    stack_id="$(portainer_stack_id "$auth" "$endpoint" "$name" || true)"
    [ -n "$stack_id" ] || die "stack $name não encontrada no endpoint $endpoint"
    file="$(curl -fsSL --header "$auth" \
        "$(normalize_url "$PORTAINER_URL")/api/stacks/$stack_id/file" | jq -er '.StackFileContent')"
    env_json="$(curl -fsSL --header "$auth" \
        "$(normalize_url "$PORTAINER_URL")/api/stacks/$stack_id" | jq -c '.Env')"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] atualizaria stack $name (id $stack_id) com repull, preservando manifest+env"
        cleanup_secrets
        return 0
    fi
    confirm "Atualizar $name com repull (pode reiniciar serviços)?" || die "abortado"
    jq -n --arg f "$file" --argjson e "$env_json" \
        '{StackFileContent:$f, Env:$e, Prune:false, RepullImageAndRedeploy:true}' \
      | curl -fsSL --request PUT --header "$auth" \
        --header 'Content-Type: application/json' --data-binary @- \
        "$(normalize_url "$PORTAINER_URL")/api/stacks/$stack_id?endpointId=$endpoint" >/dev/null
    log "stack $name atualizada com repull"
    cleanup_secrets
}

# Limpeza segura: só imagens sem uso + cache de build. NUNCA volumes
# (dado não se poda sozinho) e nunca containers/stacks em execução.
cmd_clean() {
    local older_than="${1:-168h}"
    log "removeria imagens sem uso há +$older_than + cache de build (volumes e stacks intactos)"
    if [ "$DRY_RUN" = "1" ]; then
        log "[dry-run] docker image prune -a --filter until=$older_than + builder prune"
        return 0
    fi
    confirm "Executar limpeza de imagens? (volumes e stacks NÃO são tocados)" || die "abortado"
    docker image prune -a --force --filter "until=$older_than" 2>&1 | tail -2
    docker builder prune --force --filter "until=$older_than" 2>&1 | tail -2
    log "limpeza concluída (verifique com: docker images | head)"
}

# Exibe onde cada credencial é usada (padrão SetupOrion: visível sob demanda,
# nunca em resumo). Uso: show mysql[_sufixo]. Saída contém SEGREDOS: não cole.
cmd_show() {
    local inst="${1:-}"
    [ -n "$inst" ] || die "uso: bash SetupThinkin.sh show <ferramenta[_sufixo]>"
    local rec="$SUMMARY_DIR/.secrets-$inst"
    [ -f "$rec" ] || die "sem registro para $inst (instale primeiro ou verifique o sufixo)"
    warn "SAÍDA COM SEGREDOS — não cole em chat/ticket"
    echo "--- config (sem segredos) ---"
    grep "^${inst}_" "$TOOLS_RECORD" 2>/dev/null || true
    echo "--- segredos de $inst (usar onde indicado) ---"
    while IFS='=' read -r k v; do
        [ -n "$k" ] || continue
        printf '  %s=%s\n' "$k" "$v"
    done < "$rec"
    chmod 600 "$rec"
}

main() {
    log "TopwebCRM installer $INSTALLER_VERSION (E11)"
    load_external_secrets
    local mode="${1:-install}"
    case "$mode" in
        tools)
            list_tools
            return 0
            ;;
        status)  cmd_status;  return 0 ;;
        urls)    cmd_urls;    return 0 ;;
        logs)    shift; cmd_logs "$@"; return 0 ;;
        versions) shift; cmd_versions "$@"; return 0 ;;
        update)  shift; cmd_update_stack "$@"; return 0 ;;
        show)    shift; cmd_show "$@"; return 0 ;;
        clean)   shift; cmd_clean "$@"; return 0 ;;
    esac
    thinkin_banner
    thinkin_os_advice
    thinkin_accept
    case "$mode" in
        install)
            step_bootstrap_host
            step_preflight
            step_collect
            step_confirm
            ensure_tool topwebcrm
            step_summary
            cleanup_secrets
            ;;
        tool)
            step_bootstrap_host
            step_preflight
            local tool="${2:-}"
            [ -n "$tool" ] || { list_tools; die "uso: bash SetupThinkin.sh tool <nome>"; }
            if [[ "$tool" == topwebcrm* ]]; then
                step_collect
            else
                collect_tool_tree "$tool"
            fi
            confirm "Resumo acima. Executar a instalação sem mais perguntas?" || die "abortado"
            ensure_tool "$tool"
            if [[ "$tool" == topwebcrm* ]]; then
                step_summary
            fi
            cleanup_secrets
            ;;
        --update)
            step_bootstrap_host
            step_preflight
            load_summary
            step_update
            ;;
        --rollback)
            step_bootstrap_host
            step_preflight
            load_summary
            TOPWEBCRM_IMAGE_SHA="${2:-}"
            [[ "$TOPWEBCRM_IMAGE_SHA" =~ ^[0-9a-f]{40}$ ]] \
                || die "uso: bash SetupThinkin.sh --rollback <sha-40-hex>"
            export TOPWEBCRM_IMAGE_SHA
            warn "rollback NÃO reverte migrations destrutivas: restaure banco+storage+APP_KEY como conjunto (DEPLOYMENT.md)"
            step_prereqs
            step_deploy_update_only
            step_summary
            ;;
        *) die "uso: bash SetupThinkin.sh [install|tools|tool <nome>|status|urls|logs <svc>|versions [stack]|update <stack>|clean [idade]|--update|--rollback <sha>]" ;;
    esac
    log "concluído."
}

if [[ "${BASH_SOURCE[0]:-}" == "${0}" ]]; then
    main "$@"
fi
