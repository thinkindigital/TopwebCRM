# Desenvolvimento local

O `compose.yaml` inicia somente TopwebCRM, MySQL e Redis. O OpenWA é um projeto independente e precisa ser iniciado separadamente quando o fluxo WhatsApp estiver em teste.

## Serviços

| Serviço | Do host | Entre containers do Compose |
|---|---|---|
| TopwebCRM | `http://localhost:8000` | `http://app:8000` |
| MySQL | `localhost:3307` | `mysql:3306` |
| Redis | `localhost:6380` | `redis:6379` |
| OpenWA | `http://localhost:2785` | depende da rede/nome usados ao iniciá-lo |

`localhost` dentro do container `app` é o próprio container. Para manter `TOPWEB_CHAT_BASE_URL=http://openwa:2785`, conecte o container OpenWA à rede do projeto e disponibilize o alias `openwa`. Se ele estiver fora dessa rede, use um endereço que seja realmente resolvível a partir do app.

## Instalação

1. Copie `.env.example` para `.env` e ajuste `APP_URL`, banco, Redis e TopwebChat.
2. Instale as dependências Composer e Node exigidas pelos manifests.
3. Inicie `docker compose up -d --build`.
4. Execute `php artisan krayin-crm:install` no container `app` em banco vazio.
5. Confirme migrations, assets, login e `/up`.

Não pule a criação administrativa sem que outro procedimento documentado crie essa conta. Queue e scheduler já são serviços do Compose e precisam permanecer ativos durante os testes do TopwebChat.

## Webhook local

`TOPWEB_CHAT_PUBLIC_URL` precisa ser alcançável pelo processo OpenWA. Quando os projetos estão em redes distintas, um endereço publicado no host ou um túnel HTTPS pode ser necessário. A URL resultante é:

```text
<TOPWEB_CHAT_PUBLIC_URL>/api/topweb-chat/webhooks/openwa/<instance>
```

Nunca exponha um ambiente de desenvolvimento com credenciais reais sem autenticação, TLS e controle de acesso.

## Verificação

```bash
docker compose ps
docker compose exec app php artisan about
docker compose exec app php artisan migrate:status
docker compose exec app php artisan route:list --name=topweb_chat
docker compose exec app php artisan schedule:list
docker compose exec app php artisan test
```

Para o fluxo WhatsApp, complemente com saúde do OpenWA, sessão `ready`, webhook HMAC e uma mensagem em cada direção.

## Dados locais

Não versione `.env`, bancos, `vendor/`, `node_modules/`, `storage/`, `public/storage` ou artefatos gerados fora do processo de release. Não altere PHP global, PATH ou serviços do host para corrigir o projeto sem necessidade explícita.
