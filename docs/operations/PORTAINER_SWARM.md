# Portainer e Docker Swarm

Este runbook descreve o ambiente de producao do TopwebCRM no Setup Orion. O
deploy e dividido em duas stacks: `topwebcrm` e `openwa`. O Krayin anterior deve
permanecer ativo ate que os smoke tests do TopwebCRM sejam aprovados.

## Imagens e atualizacoes

O workflow `Publish production image` roda somente depois de um `CI` bem-sucedido
em `main`. Ele publica duas tags no GHCR:

- `sha-<commit>`: tag imutavel usada em releases e rollback;
- `main`: ponteiro mutavel para recuperacao manual.

Reiniciar um container nao baixa uma imagem nova. A atualizacao automatica usa o
webhook da stack do Portainer e passa `TOPWEBCRM_IMAGE_TAG=sha-<commit>`. Assim o
Swarm recebe uma especificacao nova e o release continua rastreavel.

## Registry GHCR no Portainer

Cadastre um registry customizado com:

- Registry URL: `ghcr.io`;
- Username: usuario GitHub dono do token;
- Password: PAT classic com `read:packages`;
- SSO: autorize o token na organizacao, quando exigido.

O campo Registry URL nao deve conter `https://`, nome da organizacao, repositorio
ou tag. Associe esse registry a stack `topwebcrm` para que o Swarm receba a
credencial de pull da imagem privada.

## Recursos externos

Crie uma vez, antes das stacks:

```bash
docker node update --label-add topwebcrm=true self
docker network create --driver overlay topweb_integrations

docker volume create topwebcrm_storage
docker volume create topwebcrm_db
docker volume create topwebcrm_redis
docker volume create openwa_data
docker volume create openwa_db
docker volume create openwa_redis
docker config create openwa_swarm_entrypoint_v1 docker/openwa-swarm-entrypoint.sh
```

Docker Configs sao imutaveis. Se o entrypoint mudar, crie uma nova versao (por
exemplo, `openwa_swarm_entrypoint_v2`) e defina `OPENWA_ENTRYPOINT_CONFIG` antes
do redeploy; nao sobrescreva uma versao que esteja em uso.

Volumes de tentativas anteriores nao devem ser reutilizados sem antes confirmar
que pertencem a esta instalacao e que nao possuem dados que precisem de backup.

Crie secrets fortes e distintos por entrada padrao (`printf`, sem quebra de
linha). Nunca passe o valor na linha de comando nem salve no repositorio:

```bash
printf '%s' 'valor' | docker secret create topwebcrm_app_key -
printf '%s' 'valor' | docker secret create topwebcrm_db_password -
printf '%s' 'valor' | docker secret create topwebcrm_db_root_password -
printf '%s' 'valor' | docker secret create topwebcrm_mail_password -
printf '%s' 'valor' | docker secret create topwebcrm_admin_password -
printf '%s' 'valor' | docker secret create openwa_api_master_key -
printf '%s' 'valor' | docker secret create openwa_api_key_pepper -
printf '%s' 'valor' | docker secret create openwa_db_password -
printf '%s' 'valor' | docker secret create openwa_redis_password -
```

`topwebcrm_app_key` deve ser uma chave Laravel no formato `base64:...` e deve ser
preservada em backups. `openwa_api_master_key` deve ter pelo menos 32 caracteres.

## Stacks Git no Portainer

Use o repositorio privado `https://github.com/thinkindigital/TopwebCRM`, branch
`main`, com credencial Git somente de leitura.

Stack `openwa`:

- Compose path: `compose.openwa.production.yaml`;
- dominio: `OPENWA_DOMAIN=thinkinapi.agenciarenascimento.com.br`;
- habilite webhook ou polling GitOps;
- imagem fixada por `OPENWA_IMAGE_TAG`.

Stack `topwebcrm`:

- Compose path: `compose.production.yaml`;
- `TOPWEBCRM_DOMAIN=crm.scgroup.com.br`;
- `TOPWEBCRM_INITIAL_INSTALL=true` somente no primeiro deploy de banco vazio;
- defina `TOPWEBCRM_ADMIN_NAME` e `TOPWEBCRM_ADMIN_EMAIL`;
- associe o registry GHCR;
- habilite stack webhook e copie a URL para o secret GitHub
  `PORTAINER_TOPWEBCRM_WEBHOOK_URL`.

Depois que `storage/installed` existir, altere
`TOPWEBCRM_INITIAL_INSTALL=false`. O inicializador nunca roda quando o marcador
persistente ja existe, mas manter a variavel desligada reduz risco operacional.

## DNS e conectividade

Os hosts `crm.scgroup.com.br` e `thinkinapi.agenciarenascimento.com.br` devem
apontar para o IP do Traefik antes da emissao dos certificados. O CRM acessa o OpenWA pela rede overlay
compartilhada, usando `http://openwa_openwa_api:2785/api`; a URL publica e usada
para dashboard e operacao administrativa.

## Validacao

1. `docker service ls` mostra app, queue, scheduler, bancos e Redis em `1/1`.
2. `GET /up` do TopwebCRM responde `200`.
3. Login administrativo funciona e nao usa credencial padrao.
4. `php artisan migrate:status` nao mostra migrations pendentes.
5. Queue e scheduler permanecem ativos por pelo menos cinco minutos.
6. `GET /api/health/ready` do OpenWA responde `200`.
7. OpenWA cria/importa sessao, exibe QR e registra webhook HMAC para o CRM.
8. Envio, recebimento e status sao validados antes do corte do Krayin antigo.

## Rollback

No Portainer, altere `TOPWEBCRM_IMAGE_TAG` para a tag `sha-...` anterior e
redeploy. Nao reverta migrations destrutivas automaticamente. Banco, storage e
`APP_KEY` devem ser restaurados como um conjunto consistente.
