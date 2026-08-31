# Publicacao no Docker Swarm com Portainer

## Modelo recomendado

O GitHub Actions constroi a imagem de producao e publica no GitHub Container Registry (GHCR). O Portainer nao compila o repositorio: ele apenas aplica `compose.production.yaml` usando uma tag imutavel.

Imagem: `ghcr.io/thinkindigital/topwebcrm:sha-<commit>`.

## Gerar a imagem

1. No GitHub, abra `Actions` e selecione `Publish production image`.
2. Escolha `Run workflow` na branch `main`.
3. Aguarde o job `image` concluir.
4. Copie a tag `sha-<commit>` publicada em `Packages`.
5. Se o pacote for privado, configure no Portainer um Registry GHCR com usuario GitHub e Personal Access Token com permissao `read:packages`.

Tags `v*`, por exemplo `v1.0.0`, tambem publicam uma imagem com a mesma versao. Nao use `latest` em producao.

## Preparar o Swarm

Antes do primeiro deploy, crie no Swarm:

- secrets `topwebcrm_app_key`, `topwebcrm_db_password`, `topwebcrm_db_root_password` e `topwebcrm_mail_password`;
- volumes externos `topwebcrm_storage`, `topwebcrm_db` e `topwebcrm_redis`;
- rede overlay externa usada pelo Traefik, por padrao `renacesso`;
- label `topwebcrm=true` no node que recebera os volumes persistentes.

O valor de `topwebcrm_app_key` deve permanecer o mesmo entre deploys. Troca-lo invalida dados criptografados, sessoes e credenciais do OpenWA.

## Stack no Portainer

1. Crie uma Stack a partir do `compose.production.yaml` da branch `main`.
2. Defina `TOPWEBCRM_IMAGE=ghcr.io/thinkindigital/topwebcrm:sha-<commit>`.
3. Defina `TOPWEBCRM_DOMAIN`, `TOPWEBCRM_NETWORK` e os nomes de volumes se forem diferentes dos defaults.
4. No primeiro deploy, use `TOPWEBCRM_RUN_MIGRATIONS=true` com uma replica do app.
5. Depois da migracao bem-sucedida, volte `TOPWEBCRM_RUN_MIGRATIONS=false` e atualize a stack.

App, worker e scheduler usam exatamente a mesma imagem. O Traefik encaminha trafego para a porta interna `8080`.

## Atualizacao e rollback

Para atualizar, gere outra imagem e troque somente `TOPWEBCRM_IMAGE` pela nova tag SHA. Confirme healthcheck, login, fila, scheduler e TopwebChat.

Para rollback, restaure a tag SHA anterior e reaplique a stack. Migracoes destrutivas exigem plano de rollback de banco separado; nao presuma que trocar apenas a imagem desfaz schema.

## OpenWA

OpenWA continua sendo servico externo ao stack do TopwebCRM. A URL configurada na Instance precisa ser alcancavel pelos containers do CRM, e o callback `TOPWEB_CHAT_PUBLIC_URL` precisa ser alcancavel pelo OpenWA via HTTPS.
