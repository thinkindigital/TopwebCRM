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
OPENWA_IMAGE_TAG=0.23.3
OPENWA_ENTRYPOINT_CONFIG=openwa_swarm_entrypoint_v1
TOPWEBCRM_PROXY_NETWORK=renacesso
TOPWEBCRM_INTEGRATIONS_NETWORK=topweb_integrations
```

Associe os quatro secrets OpenWA e faça o deploy. O engine padrão é `whatsapp-web.js`; altere `OPENWA_ENGINE_TYPE` somente após validar compatibilidade de sessão e contrato.

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

## Release automático

O workflow `.github/workflows/publish-production-image.yml` é executado após o CI bem-sucedido em `main`. Ele constrói a imagem, publica as tags e chama a API autenticada do Portainer para:

1. localizar exatamente a stack `topwebcrm` no endpoint configurado;
2. preservar o manifest e as demais variáveis;
3. trocar apenas `TOPWEBCRM_IMAGE_TAG` pela nova `sha-<commit>`;
4. solicitar repull e redeploy.

O secret de GitHub Actions é `PORTAINER_API_KEY`. A chave deve ser exclusiva para automação, ter somente a permissão necessária e ser rotacionada quando houver suspeita de exposição.

O workflow atual representa o servidor operacional para o qual foi configurado. Antes de reutilizá-lo em outro SetupOrion, parametrizar e conferir URL do Portainer, endpoint ID e nome da stack é obrigatório; copiar apenas o Compose não transfere DNS, secrets, volumes, registry, labels nem a credencial de CI/CD.

## Validação de uma release

Considere o deploy aprovado somente quando:

1. `docker service ls` mostra app, queue, scheduler, bancos e Redis em `1/1`;
2. `https://crm.<dominio-do-cliente>/up` responde `200`;
3. login administrativo funciona com a credencial criada para o cliente;
4. `php artisan migrate:status` não mostra migrations pendentes;
5. queue e scheduler permanecem estáveis;
6. `https://openwa.<dominio-do-cliente>/api/health/ready` responde `200`;
7. a sessão OpenWA está pronta e o CRM exibe seu estado;
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
