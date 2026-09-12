# Produção no SetupOrion, Portainer e Docker Swarm

Este é o runbook canônico para instalar, atualizar, verificar e recuperar TopwebCRM e OpenWA. Ele assume um servidor já preparado pelo SetupOrion com Docker Swarm, Portainer e Traefik.

Os exemplos usam placeholders. Substitua-os por valores da instalação e nunca reutilize domínios, senhas ou volumes de outro cliente.

## Topologia

O deploy é dividido em duas stacks:

| Stack | Manifest | Serviços | Persistência |
|---|---|---|---|
| `topwebcrm` | `compose.production.yaml` | app, queue, scheduler, Percona e Redis | `topwebcrm_storage`, `topwebcrm_db`, `topwebcrm_redis` |
| `openwa` | `compose.openwa.production.yaml` | API OpenWA, PostgreSQL e Redis | `openwa_data`, `openwa_db`, `openwa_redis` |

Cada stack possui uma rede interna para seus bancos. A rede overlay externa `topweb_integrations` permite a comunicação CRM → OpenWA; a rede externa do Traefik, normalmente `renacesso` em instalações SetupOrion, publica somente os serviços HTTP.

TopwebCRM não depende da stack Krayin original. Ela pode ser removida depois de confirmar que não compartilha volumes, secrets, banco, nomes DNS ou domínio com o TopwebCRM.

## Modelo de endereços

Defina no Portainer:

```dotenv
TOPWEBCRM_DOMAIN=crm.<dominio-do-cliente>
OPENWA_DOMAIN=openwa.<dominio-do-cliente>
TOPWEBCRM_PROXY_NETWORK=renacesso
TOPWEBCRM_INTEGRATIONS_NETWORK=topweb_integrations
```

Os dois hosts públicos devem apontar para o IP do Traefik antes da emissão dos certificados. O endereço público do OpenWA serve ao dashboard e à administração da API; restrinja seu acesso conforme a política do cliente.

Dentro do TopwebChat, cadastre a base do provedor como:

```text
http://openwa_openwa_api:2785
```

Esse DNS resulta de `<nome-da-stack>_<nome-do-serviço>`. Se a stack tiver outro nome, ajuste o primeiro segmento. Não use a URL pública para o tráfego entre stacks e não acrescente `/api`: o adapter do CRM já inclui esse prefixo nos endpoints.

O webhook segue o caminho inverso e usa o endereço HTTPS do CRM:

```text
https://crm.<dominio-do-cliente>/api/topweb-chat/webhooks/openwa/<instance>
```

O sistema gera essa URL a partir de `TOPWEB_CHAT_PUBLIC_URL`, definido pelo manifest com o mesmo domínio do CRM.

## Pré-requisitos

Antes do primeiro deploy, confirme:

- Swarm ativo e acesso administrativo ao Portainer;
- Traefik operacional e sua rede overlay externa conhecida;
- DNS público apontando para o servidor;
- nó persistente identificado para hospedar os volumes locais;
- registry `ghcr.io` cadastrado no Portainer quando o pacote exigir autenticação;
- backup válido antes de reutilizar qualquer volume existente.

O manifest fixa os serviços persistentes no nó com o label `topwebcrm=true`. Em um Swarm de nó único:

```bash
docker node update --label-add topwebcrm=true self
```

Em cluster, aplique o label ao hostname escolhido e mantenha app, bancos e volumes nesse nó até existir uma estratégia de storage distribuído.

## Recursos externos

Crie uma única vez:

```bash
docker network create --driver overlay --attachable topweb_integrations

docker volume create topwebcrm_storage
docker volume create topwebcrm_db
docker volume create topwebcrm_redis
docker volume create openwa_data
docker volume create openwa_db
docker volume create openwa_redis

docker config create openwa_swarm_entrypoint_v1 docker/openwa-swarm-entrypoint.sh
```

O comando do Docker Config deve ser executado a partir de um checkout da mesma revisão usada no deploy.

Os nomes acima são os padrões dos manifests. Para nomes diferentes, defina as variáveis `*_VOLUME`, `TOPWEBCRM_INTEGRATIONS_NETWORK` e `OPENWA_ENTRYPOINT_CONFIG` no Portainer.

Docker Configs são imutáveis. Quando `docker/openwa-swarm-entrypoint.sh` mudar, crie uma versão nova, por exemplo `openwa_swarm_entrypoint_v2`, altere `OPENWA_ENTRYPOINT_CONFIG` e faça o redeploy. Só remova a versão antiga quando nenhum serviço a estiver usando.

## Secrets

Crie os secrets como objetos externos pelo Portainer ou por entrada padrão no manager. Os nomes esperados são:

| TopwebCRM | OpenWA |
|---|---|
| `topwebcrm_app_key` | `openwa_api_master_key` |
| `topwebcrm_db_password` | `openwa_api_key_pepper` |
| `topwebcrm_db_root_password` | `openwa_db_password` |
| `topwebcrm_mail_password` | `openwa_redis_password` |
| `topwebcrm_admin_password` | |

`topwebcrm_app_key` deve ser uma chave Laravel no formato `base64:...`. Preserve-a junto dos backups: trocá-la sem um plano de rotação impede a leitura de campos criptografados. Use valores fortes e distintos; a master key do OpenWA deve ter no mínimo 32 caracteres.

Não coloque valores de secrets nas variáveis da stack, no Git, nos logs ou na linha de comando. Para criação pelo terminal, leia o valor sem eco e envie-o por stdin:

```bash
TOPWEBCRM_SECRET_NAME=topwebcrm_app_key
read -rsp 'Secret: ' TOPWEBCRM_SECRET_VALUE
printf '%s' "$TOPWEBCRM_SECRET_VALUE" | docker secret create "$TOPWEBCRM_SECRET_NAME" -
unset TOPWEBCRM_SECRET_VALUE
unset TOPWEBCRM_SECRET_NAME
```

## Registry e imagem do CRM

O build de produção publica no GHCR:

- `sha-<commit>`: tag imutável de release e rollback;
- `main`: ponteiro mutável, útil apenas para diagnóstico ou recuperação manual.

No registry customizado do Portainer use `ghcr.io` como URL, sem protocolo, organização, repositório ou tag. Quando a imagem não for pública, o usuário/token precisa de `read:packages`; associe a credencial à stack para o Swarm propagá-la no pull.

Defina uma tag imutável no deploy normal:

```dotenv
TOPWEBCRM_IMAGE_TAG=sha-<commit>
```

Reiniciar uma task não busca uma imagem nova. A atualização exige mudar a tag ou forçar repull/redeploy da stack.

## Primeiro deploy

### 1. OpenWA

Crie a stack `openwa` no Portainer usando `compose.openwa.production.yaml` e configure pelo menos:

```dotenv
OPENWA_DOMAIN=openwa.<dominio-do-cliente>
OPENWA_IMAGE_TAG=0.23.4
OPENWA_ENTRYPOINT_CONFIG=openwa_swarm_entrypoint_v1
TOPWEBCRM_PROXY_NETWORK=renacesso
TOPWEBCRM_INTEGRATIONS_NETWORK=topweb_integrations
```

Associe os quatro secrets OpenWA e faça o deploy. Engine, Redis e banco são controlados pelo dashboard (Infrastructure) — o compose **não fixa** `ENGINE_TYPE`, `DATABASE_*` (exceto segredos `*_FILE`), `REDIS_*` (exceto segredo), `QUEUE_ENABLED` nem `CACHE_ENABLED`. Os valores de produção vivem em `/app/data/.env.generated` (postgres `openwa_db`, redis `openwa_redis`, filas ativas); env de processo tem precedência, então **não reintroduza essas chaves no compose** ou o aviso "Fixado pela variável de ambiente" volta e o painel perde o controle. O CRM usa a feature flag `TOPWEB_CHAT_ENGINE` (`whatsapp-web.js` | `baileys`) para selecionar o provider (`OpenWaProvider` | `BaileysProvider`); o contrato REST é o mesmo, então trocar o engine no dashboard não exige redeploy do CRM.

Trocar de engine NÃO migra sessão autenticada (cada engine tem suas próprias credenciais): após trocar, a sessão pede novo QR — escaneie com a conta do CRM e valide envio/recebimento. Para teste de envio, use um lead com contato **5511993193118**.

### Painel BullMQ (filas)

As estatísticas de fila aparecem no dashboard (webhook-queue, ingress-queue). O botão "Ver painel BullMQ" abre a rota sem o header `X-API-Key`, então o board responde 404 em branco — é bug do dashboard upstream, não do Traefik. Workaround operacional (com a master key):

```bash
OPENWA_KEY='<api-master-key>'
# contadores por fila
curl -H "X-API-Key: $OPENWA_KEY" https://openwa.<dominio>/api/admin/queues/api/queues
# entregas de webhook que falharam (últimas 5)
curl -H "X-API-Key: $OPENWA_KEY" "https://openwa.<dominio>/api/webhooks/delivery-failures?limit=5"
```

O board interativo completo vive em `https://openwa.<dominio>/api/admin/queues/` e exige o header `X-API-Key` (use ModHeader ou curl). Falhas recorrentes com `HTTP 404` contra `/api/topweb-chat/webhooks/openwa/<id>` indicam webhook apontando para instância inexistente no CRM — liste com `GET /api/sessions/{sessionId}/webhooks` e remova o obsoleto com `DELETE /api/sessions/{sessionId}/webhooks/{webhookId}`.

### Postgres com TLS/SSL?

Não recomendado nesta topologia. O Postgres do OpenWA roda na rede overlay **interna** (`openwa_data_network`, `internal: true`), sem exposição externa e com senha via secret. TLS adicionaria distribuição de certificados (servidor + `DATABASE_SSL=true` + CA no cliente) para ganho marginal — o tráfego nunca sai da rede isolada do Swarm. Reavalie apenas se o banco for movido para fora do overlay (ex.: gerenciado externo) — aí habilite no dashboard (Infrastructure) com CA válida.

### Reinicialização do OpenWA

Para aplicar mudanças de configuração (engine, proxy, timeout, variáveis de ambiente):

```bash
# Reinicializar apenas o serviço API
docker service update --force openwa_openwa_api

# Ou via Portainer: Services → openwa_openwa_api → Update → Force update
```

O OpenWA reconecta automaticamente a sessão se as credenciais forem válidas. Para forçar nova sessão:
```bash
docker service update --force openwa_openwa_api
# Ou no dashboard OpenWA: Sessions → Stop → Start
```

**Política de restart e réplicas:** o serviço usa `restart_policy: condition: any` (recria a task mesmo após saída limpa, ex.: `docker stop`) e `replicas: 1`. O `1` é intencional e **não deve ser escalado**: o OpenWA é single-process por volume de sessão (doc self-hosting) — duas réplicas escrevendo no mesmo diretório de auth corrompem a sessão e forçam logout. Com `on-failure` (default anterior), uma parada limpa (exit 0) não era reagendada; com `any`, o Swarm sempre mantém 1 task rodando.

### 2. TopwebCRM

Crie a stack `topwebcrm` usando `compose.production.yaml`. Além das variáveis de domínio e redes, informe explicitamente os dados próprios do cliente:

```dotenv
TOPWEBCRM_IMAGE_TAG=sha-<commit>
TOPWEBCRM_APP_NAME=<nome-do-crm>
TOPWEBCRM_ADMIN_NAME=<nome-do-administrador>
TOPWEBCRM_ADMIN_EMAIL=<email-do-administrador>
TOPWEBCRM_MAIL_HOST=<host-smtp>
TOPWEBCRM_MAIL_PORT=<porta-smtp>
TOPWEBCRM_MAIL_ENCRYPTION=<tls-ou-ssl>
TOPWEBCRM_MAIL_USERNAME=<usuario-smtp>
TOPWEBCRM_MAIL_FROM_ADDRESS=<remetente>
TOPWEBCRM_INITIAL_INSTALL=true
```

Associe o registry GHCR e os cinco secrets do CRM. O entrypoint espera o banco, executa migrations e, em banco vazio, cria a instalação inicial. Após confirmar a existência de `storage/installed` e o login administrativo, altere `TOPWEBCRM_INITIAL_INSTALL=false` e faça novo deploy. O marcador persistente impede repetição acidental, mas manter a variável desligada reduz o risco operacional.

### 3. Integração

No dashboard do OpenWA, crie ou inicie a sessão WhatsApp e obtenha uma API key autorizada. Em **TopwebChat → Configurações**:

1. cadastre o UUID e o nome da sessão;
2. informe `http://openwa_openwa_api:2785` como URL base;
3. informe a API key, habilite a instância e salve;
4. confirme que saúde e sessões são listadas;
5. configure o webhook;
6. execute a reconciliação e valide envio e recebimento.

A API key e o segredo HMAC do webhook ficam criptografados no banco pelo Laravel; por isso o backup do banco depende da mesma `APP_KEY`.

## Banco e Redis externos (compartilhados)

Por padrão a stack sobe Percona e Redis embutidos. Para usar os serviços
compartilhados do instalador (um MySQL, um Redis — mesmo modelo do SetupOrion),
defina na stack `topwebcrm`:

```dotenv
TOPWEBCRM_DB_HOST=mysql_mysql
TOPWEBCRM_DB_PORT=3306
TOPWEBCRM_DB_NAME=topwebcrm
TOPWEBCRM_DB_USER=topwebcrm
TOPWEBCRM_DB_REPLICAS=0
TOPWEBCRM_REDIS_HOST=redis_redis
TOPWEBCRM_REDIS_PORT=6379
TOPWEBCRM_REDIS_REPLICAS=0
```

Com réplicas `0`, os serviços internos são criados parados e o CRM (app, queue,
scheduler) fala com o compartilhado — inclusive o `WAIT_FOR_DATABASE` do entrypoint.
O banco/usuário no MySQL compartilhado deve existir antes (criado pelo instalador
via `ensure_mysql_db`); a senha continua em `topwebcrm_db_password`.
Validação: `docker compose -f compose.production.yaml config` com as variáveis
acima deve mostrar os hosts externos e `replicas: 0` só em `topwebcrm_db`/`topwebcrm_redis`.

## Stack de homologação (dev)

Para reproduzir a `dev` no navegador sem tocar a produção nem as instalações
futuras (que seguem na `main`), existe a tag mutável `dev` mais a imutável
`sha-<commit>` por push. O workflow `.github/workflows/publish-dev-image.yml`
publica ambas a cada push na `dev` (pula escopo só-docs) e faz repull/redeploy
somente da stack `topwebcrm-dev`.

Crie a stack `topwebcrm-dev` no Portainer com o **mesmo** `compose.production.yaml`
e variáveis próprias — nunca reutilize volumes, banco ou secrets da produção:

```dotenv
TOPWEBCRM_DOMAIN=crmdev.scgroup.com.br
TOPWEBCRM_IMAGE_TAG=dev
TOPWEBCRM_SECRET_PREFIX=topwebcrm_dev
TOPWEBCRM_STORAGE_VOLUME=topwebcrm_dev_storage
TOPWEBCRM_DB_VOLUME=topwebcrm_dev_db
TOPWEBCRM_REDIS_VOLUME=topwebcrm_dev_redis
TOPWEBCRM_INTEGRATIONS_NETWORK=topweb_integrations
```

Secrets dedicados `topwebcrm_dev_*` e volumes `topwebcrm_dev_*` (driver local,
já criados no manager em 2026-09-11).
Banco zerado: primeiro deploy com `TOPWEBCRM_INITIAL_INSTALL=true`, depois `false`.

OpenWA é o **mesmo** gateway (`http://openwa_openwa_api:2785`, rede
`topweb_integrations` compartilhada; `TOPWEB_CHAT_PUBLIC_URL` aponta para o
domínio dev). Disciplina obrigatória: **nunca** configure o webhook da dev em
sessão real (sequestraria os eventos da produção) nem envie para contatos reais —
use o lead de teste com o contato `5511993193118` e, se preciso, uma sessão de
teste dedicada.

## Release automático

O workflow `.github/workflows/publish-production-image.yml` é executado após o CI bem-sucedido em `main`. Ele constrói a imagem, publica as tags e chama a API autenticada do Portainer para:

1. localizar exatamente a stack `topwebcrm` no endpoint configurado;
2. preservar o manifest e as demais variáveis;
3. trocar apenas `TOPWEBCRM_IMAGE_TAG` pela nova `sha-<commit>`;
4. solicitar repull e redeploy.

O secret de GitHub Actions é `PORTAINER_API_KEY`. A chave deve ser exclusiva para automação, ter somente a permissão necessária e ser rotacionada quando houver suspeita de exposição.

O workflow atual representa o servidor operacional para o qual foi configurado. Antes de reutilizá-lo em outro SetupOrion, parametrizar e conferir URL do Portainer, endpoint ID e nome da stack é obrigatório; copiar apenas o Compose não transfere DNS, secrets, volumes, registry, labels nem a credencial de CI/CD.

### Eventos e gates do CI

Em branches com Pull Request aberto, CI, lint e Playwright executam uma única vez pelo evento `pull_request`. Em `main`, os mesmos gates executam pelo evento `push`; somente o CI aprovado em `main` libera a publicação da imagem e o redeploy. Cada workflow usa `concurrency` por branch ou Pull Request para cancelar revisões obsoletas sem interromper uma execução de outra entrega.

O gate Playwright constrói os assets administrativos antes de iniciar o Laravel e só começa os testes quando `/admin/login` renderiza o formulário esperado. Assim, falhas de bootstrap (por exemplo, manifest do Vite ausente) são separadas de falhas funcionais e aparecem antes dos shards.

## Validação de uma release

Considere o deploy aprovado somente quando:

1. `docker service ls` mostra app, queue, scheduler, bancos e Redis em `1/1`;
2. `https://crm.<dominio-do-cliente>/up` responde `200`;
3. login administrativo funciona com a credencial criada para o cliente;
4. `php artisan migrate:status` não mostra migrations pendentes;
5. queue e scheduler permanecem estáveis;
6. `https://openwa.<dominio-do-cliente>/api/health/ready` responde `200`;
7. a sessão OpenWA está pronta (`ready`, `engineLoaded=true`) e o CRM exibe seu estado;
7b. engine OpenWA correto: `GET /api/sessions/{id}` mostra `engine` correspondente (`whatsapp-web.js` ou `baileys`);
8. webhook assinado, envio, recebimento e atualização de status funcionam;
9. anexos privados e dados sensíveis respeitam as regras de autorização;
10. não há loop de restart nem erro recorrente nos logs do Portainer.

## Backup e restauração

O conjunto mínimo de recuperação inclui:

- dump consistente do banco `topwebcrm`;
- conteúdo de `topwebcrm_storage`, sobretudo `storage/app/private` e o marcador de instalação;
- valor protegido de `topwebcrm_app_key`;
- dump do PostgreSQL OpenWA;
- volume `openwa_data`, que contém dados das sessões;
- inventário das tags de imagem, variáveis, configs e nomes dos secrets.

Redis é cache/fila e não substitui o backup dos bancos. Teste restauração periodicamente; possuir arquivos sem validar a recuperação não constitui backup confiável.

## Rollback

Para rollback de aplicação:

1. altere `TOPWEBCRM_IMAGE_TAG` para a tag `sha-...` anterior;
2. faça repull/redeploy no Portainer;
3. verifique tasks, migrations, login, fila e TopwebChat.

Não reverta migrations destrutivas automaticamente. Quando schema, dados e código não forem retrocompatíveis, restaure banco, storage e `APP_KEY` como um conjunto consistente.

Para trocar ou remover uma stack, preserve primeiro volumes, secrets e backups. Excluir a stack não deve excluir volumes externos, mas a existência desses recursos precisa ser confirmada antes da operação.

## Migração para outro servidor

Os manifests são portáveis, a instalação completa não é autossuficiente. Em outro SetupOrion é necessário repetir:

1. label do nó;
2. redes e volumes externos;
3. Docker Config do entrypoint;
4. secrets;
5. registry e autorização de pull;
6. variáveis da stack;
7. DNS e certificados;
8. restauração de banco/storage quando houver dados;
9. configuração do endpoint de CI/CD;
10. checklist funcional.

Esse inventário é a base para um instalador futuro, mas continua válido mesmo quando a criação dos recursos for automatizada.
