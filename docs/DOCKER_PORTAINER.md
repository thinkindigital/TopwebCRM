# TopwebCRM no Docker Swarm e Portainer

## Plataforma Setup Orion

O servidor é administrado pelo [Setup Orion](https://github.com/oriondesign2015/setuporion), com Docker Swarm, Portainer Business Edition e Traefik. Consulte `docs/SETUP_ORION.md` para o mapa da infraestrutura e as decisões de isolamento.

## Imagem personalizada

A imagem deve conter o código modificado. Não monte o antigo volume `krayin_app` em `/var/www/html`, pois ele ocultaria o TopwebCRM empacotado.

No nó manager:

```bash
docker build -f docker/php/Dockerfile.production -t topwebcrm:2026.07.28 .
```

Para imagem disponível somente no nó do build, aplique nesse mesmo nó a label `topwebcrm=true`. Em múltiplos nós, publique a tag em um registry acessível pelo cluster.

## Segredos

Crie no Portainer, antes do deploy:

- `topwebcrm_app_key`
- `topwebcrm_db_password`
- `topwebcrm_db_root_password`
- `topwebcrm_mail_password`

Gere uma chave nova com:

```bash
docker run --rm topwebcrm:2026.07.28 php artisan key:generate --show
```

As senhas expostas no YAML legado devem ser rotacionadas. Não as reutilize.

## Variáveis da stack

```env
TOPWEBCRM_IMAGE=topwebcrm:2026.07.28
TOPWEBCRM_DOMAIN=kcrm.agenciarenascimento.com.br
TOPWEBCRM_NETWORK=renacesso
TOPWEBCRM_APP_NAME=TopwebCRM
TOPWEBCRM_MAIL_HOST=smtp.zoho.com.br
TOPWEBCRM_MAIL_USERNAME=contato@agenciarenascimento.com.br
TOPWEBCRM_MAIL_FROM_ADDRESS=contato@agenciarenascimento.com.br
TOPWEBCRM_RUN_MIGRATIONS=true
TOPWEBCRM_QUEUE_REPLICAS=0
TOPWEBCRM_SCHEDULER_REPLICAS=0
```

A rede externa `renacesso` deve ser a mesma configurada no Traefik pelo Setup Orion. Confirme o valor em `/root/dados_vps/dados_vps`. Antes de reutilizar o domínio, remova ou renomeie o router Traefik antigo.

## Preparação Orion

```bash
docker node update --label-add topwebcrm=true NOME_DO_NO
docker volume create topwebcrm_storage
docker volume create topwebcrm_db
docker volume create topwebcrm_redis
docker network inspect renacesso
```

Não reutilize `krayin_app`: um volume em `/var/www/html` ocultaria o código da imagem personalizada.

## Deploy

No Portainer EE, crie uma stack Swarm com `compose.production.yaml`, cadastre as variáveis e associe os quatro secrets.

O deploy inicial ocorre em duas fases:

1. use `TOPWEBCRM_RUN_MIGRATIONS=true`, queue `0` e scheduler `0`;
2. aguarde o app ficar saudável e confirme as migrations nos logs;
3. altere migrations para `false`, queue para `1` e scheduler para `1`;
4. atualize a stack e valide os três processos.

Em releases futuras, faça backup e repita a fase controlada quando houver migration. O app usa atualização `stop-first`; rollback de imagem não desfaz schema.

Para imagem local via CLI:

```bash
docker stack deploy --resolve-image never -c compose.production.yaml topwebcrm
```

O entrypoint espera o banco por até 120 segundos. Somente o app executa migrations quando a variável estiver habilitada. Apache usa master root com workers `www-data`; queue e scheduler iniciam como `www-data`.

O worker usa timeout de 120 segundos, `retry_after` padrão de 180 segundos e grace period de 150 segundos. O Swarm reinicia o processo também após a saída limpa causada por `--max-time=3600`.

## Serviços compartilhados do Orion

O TopwebCRM não usa o MySQL e o Redis globais já instalados. A stack mantém serviços dedicados na rede privada para impedir colisão de bancos, `FLUSHALL`, filas compartilhadas e exposição de sessões. O RedisInsight global também não recebe acesso automático ao Redis privado.

## Verificação

```bash
docker service ls
docker service logs -f topwebcrm_topwebcrm_app
docker service logs -f topwebcrm_topwebcrm_queue
curl -I https://kcrm.agenciarenascimento.com.br/up
```

No Topweb Chat, salve uma instância já criada, clique em **Sincronizar estado** e depois em **Configurar webhook**.

## Desenvolvimento local

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan migrate
```

O compose local inclui MySQL, Redis, worker e scheduler. `localhost` não recebe webhooks da RyzeAPI: mensagens novas desconhecidas exigem URL HTTPS pública ou túnel. O catch-up REST recupera histórico somente de conversas já conhecidas.
