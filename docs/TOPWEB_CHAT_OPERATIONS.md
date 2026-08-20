# Operação do Topweb Chat (OpenWA — Especificação Oficial)

> Baseado em: `docs/openwa/06-api-specification.md`, `docs/openwa/07-api-collection.md`, `docs/openwa/31-session-lifecycle-design.md`
> OpenWA roda na porta **2785**: `http://<host>:2785/api`

---

## Estado Operacional

- **Scheduler (Laravel):**
  - `topweb-chat:reconcile --state` — everyMinute: consulta `GET /api/sessions/:sessionId` → sincroniza status, `engineLoaded`, `restriction`
  - `topweb-chat:reconcile --history` — everyFiveMinutes (limit 20): `GET /api/sessions/:sessionId/messages/:chatId/history` → catch-up paginado
- **Worker Redis:** `php artisan queue:work redis --queue=default,topweb_chat --sleep=2 --tries=3 --timeout=120`
- **Abrir conversa:** agenda catch-up histórico + `POST /api/sessions/:sessionId/messages/send-text` com `markChatRead` (via webhook `message.ack`)
- **Compositor bloqueado** enquanto `Instance.status !== 'ready'` OU `engineLoaded !== true`
- **Histórico REST** não enumera conversas novas → inbound desconhecido depende do **webhook público**
- **Mídia:** URLs do OpenWA são temporárias → **download server-side obrigatório** via `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` → `storage/app/private`

---

## Configuração

### Pré-requisitos Infra
- OpenWA rodando (Docker: `openwa-api`, `postgres`, `redis`, `minio` opcional)
- HTTPS público no OpenWA (Traefik termina TLS) → `https://openwa.seudominio.com`
- HTTPS público no TopwebCRM (Traefik) → `https://crm.seudominio.com`
- Rede Docker compartilhada ou conectividade `crm ↔ openwa`

### Variáveis de Ambiente (TopwebCRM)
```env
TOPWEB_CHAT_PUBLIC_URL=https://crm.seudominio.com
QUEUE_CONNECTION=redis
# OpenWA interno (Docker network)
OPENWA_BASE_URL=http://openwa:2785
# OpenWA externo (se Traefik expõe)
# OPENWA_BASE_URL=https://openwa.seudominio.com
```

### Cadastro de Instância (Admin UI)
1. **Topweb Chat > Configurações > Nova Instância**
2. **Provider:** `openwa`
3. **Session UUID:** UUID retornado pelo OpenWA no `POST /api/sessions` (campo `id`)
4. **Session Name:** nome da sessão no OpenWA (ex: `crm-topweb`)
5. **Base URL:** `http://openwa:2785` (interno) ou `https://openwa.seudominio.com`
6. **API Key:** `owa_k1_...` (role `operator`, gerada no OpenWA Dashboard ou via API)
7. **Webhook Secret:** string aleatória 32+ chars (HMAC SHA256)
8. **Salvar** → clicar **Configurar webhook** (faz `POST /api/sessions/:sessionId/webhooks/:id/test`)

### Criação da Sessão no OpenWA (via API)
```bash
# 1. Criar sessão (requer API Key ADMIN/OPERATOR unscoped)
curl -X POST "http://openwa:2785/api/sessions" \
  -H "X-API-Key: owa_k1_admin_key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "crm-topweb",
    "config": { "autoReconnect": true }
  }'

# Response: { "id": "SESSION_UUID", "name": "crm-topweb", "status": "created", ... }

# 2. Configurar webhook (requer OPERATOR)
curl -X POST "http://openwa:2785/api/sessions/SESSION_UUID/webhooks" \
  -H "X-API-Key: owa_k1_operator_key" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://crm.seudominio.com/api/topweb-chat/webhooks/openwa/SESSION_UUID",
    "events": [
      "message.received","message.sent","message.ack","message.failed",
      "message.revoked","message.reaction","message.edited",
      "session.status","session.authenticated","session.disconnected",
      "group.join","group.leave","group.update","call.received"
    ],
    "secret": "SEGREDO_HMAC_32_CHARS_ALEATORIO",
    "retryCount": 3
  }'

# 3. Iniciar sessão (gera QR)
curl -X POST "http://openwa:2785/api/sessions/SESSION_UUID/start" \
  -H "X-API-Key: owa_k1_operator_key"

# 4. Obter QR (até status=qr_ready)
curl -X GET "http://openwa:2785/api/sessions/SESSION_UUID/qr" \
  -H "X-API-Key: owa_k1_operator_key"
# Response: { "qrCode": "data:image/png;base64,...", "status": "qr_ready" }

# 5. Escanear QR no WhatsApp → status transiciona: authenticating → ready
# Webhook session.authenticated dispara: { sessionId, phone, pushName }
```

### Alternativa: Pairing Code (sem QR)
```bash
curl -X POST "http://openwa:2785/api/sessions/SESSION_UUID/pairing-code" \
  -H "X-API-Key: owa_k1_operator_key" \
  -H "Content-Type: application/json" \
  -d '{ "phoneNumber": "5511999999999" }'
# Response: { "code": "ABCD-EFGH" } (8 chars)
# Digitar no WhatsApp: Settings → Linked Devices → Link with phone number
```

---

## Fluxo de Atendimento

1. Em **Pessoa** ou **Lead**, clicar **Enviar WhatsApp**
2. Backend usa telefone salvo (mascarado na UI) → normaliza `remote_jid` = `5511999999999@c.us`
3. **Pré-validação opcional:** `GET /api/sessions/:sessionId/contacts/check/5511999999999` → `{exists:true, whatsappId:"5511999999999@c.us"}`
4. Se existir conversa com mesma `remote_jid_key` + `session_uuid` → reabre
5. Primeira resposta de operador assume conversa sem atendente (fila "Sem atendente")
6. Conversas vinculadas a Lead permitem alterar etapa do pipeline
7. Mensagens recebidas pós-webhook entram na fila **Sem atendente**

### Envio (Outbox Local — Padrão Atual)
- Cria `Message` com `status=queued`, `operation_key` (UUID v4 único)
- Worker: `php artisan queue:work redis --queue=default,topweb_chat --sleep=2 --tries=3 --timeout=120`
- Job `SendMessage` → `OpenWaProvider::sendText()`
  ```php
  // POST /api/sessions/{session_uuid}/messages/send-text
  Http::withHeaders(['X-API-Key' => $instance->api_key])
      ->post("{$instance->base_url}/api/sessions/{$instance->session_uuid}/messages/send-text", [
          'chatId' => $conversation->remote_jid, // 5511999999999@c.us
          'text' => $message->content,
          'quotedMessageId' => $message->quoted_message_id ?? null,
      ]);
  ```
- Response **201**: `{messageId: "true_5511999999999@c.us_3EB0ABCD", timestamp: 1719312000}`
- Atualiza `Message`: `provider_message_id`, `status=sent`, `sent_at`
- **Confirmação final assíncrona** via webhook `message.ack`:
  - `status: delivered` → `Message.status=delivered`
  - `status: read` → `Message.status=read` (monotônico: não rebaixa)
  - `status: failed` → `Message.status=failed` + `last_error`

### Recebimento (Webhook HMAC)
```
OpenWA → POST https://crm.seudominio.com/api/topweb-chat/webhooks/openwa/{session_uuid}
Headers:
  X-OpenWA-Signature: sha256=<HMAC_SHA256(raw_body, webhook_secret)>
  X-OpenWA-Event: message.received
  X-OpenWA-Idempotency-Key: msg_{sessionUuid}_{messageId}
  X-OpenWA-Delivery-Id: dlv_<uuid>
  X-OpenWA-Retry-Count: 0
Body (raw JSON):
{
  "event": "message.received",
  "timestamp": "2026-02-02T10:00:00.000Z",
  "sessionId": "SESSION_UUID",
  "idempotencyKey": "msg_SESSION_UUID_true_...",
  "deliveryId": "dlv_...",
  "data": {
    "id": "true_5511999999999@c.us_3EB0ABCD",
    "from": "5511999999999@c.us",
    "to": "5511888888888@c.us",
    "body": "Olá!",
    "type": "text",
    "timestamp": 1719312000,
    "isGroup": false,
    "kind": "individual",
    "hasMedia": false,
    "contact": { "name": "João", "pushName": "João Silva" }
  }
}
```

**Processamento no TopwebCRM:**
1. `WebhookController` valida HMAC SHA256 sobre **raw body** (não re-serializado)
2. Persiste `WebhookEvent` idempotente (`provider_event_id = idempotencyKey`)
3. Job `ProcessWebhookEvent` → `WebhookProcessor`
4. Normaliza `from` → `RemoteIdentityService::normalize($from)` → `5511999999999@c.us`
5. `remote_jid_key` = hash (ex: `sha256(5511999999999@c.us)`)
6. Busca `Conversation` por `session_uuid` + `remote_jid_key`
   - **Não encontrado:** Quarentena identidade (E06) — não cria Pessoa silenciosamente
   - **Múltiplas Pessoas:** Quarentena identidade (E06) — revisão humana
   - **Encontrada única:** Vincula à Pessoa/Lead existente
7. Persiste `Message` inbound:
   - `direction=in`, `type=text`, `content=body`, `provider_message_id=id`
   - `status=delivered` (inbound já chegou) ou `received`
   - `timestamp` = epoch seconds do payload
   - `metadata` = JSON criptografado (contact, quotedMessage, etc.)
8. Atualiza `Conversation`: `last_message_at`, `unread_count++`
9. UI polling 5s (`GET /api/topweb-chat/conversations/{id}/timeline`) pega timeline atualizada

---

## Mídia — Download Server-Side (Obrigatório)

| Cenário | Ação |
|---------|------|
| Inbound com mídia (`hasMedia=true`, `media.omitted=true` ou `sizeBytes > 1MiB`) | Job `DownloadMedia` → `GET /api/sessions/{session_uuid}/messages/{chatId}/{messageId}/media` → salva em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}` |
| Inbound mídia inline (`media.data` base64, ≤ 1MiB) | Decodifica base64 → salva em `storage/app/private/...` |
| Outbound (envio) | Upload via `POST /messages/send-image|video|document` com `url` (fetch server-side pelo OpenWA) **OU** `base64` + `mimetype` |
| Download pelo operador | Rota autenticada `GET /api/topweb-chat/media/{messageId}` → serve do `storage/app/private` com `Content-Disposition: attachment` |

**Validação:**
- `MEDIA_DOWNLOAD_MAX_BYTES` = 50 MiB (padrão OpenWA) → rejeitar maior
- Mimetype permitido: `image/*`, `video/*`, `audio/*`, `application/pdf`, `application/*`
- Filename sanitizado

---

## Dados Sensíveis

- Telefone/JID mascarado para todos, exceto `users.can_view_sensitive_data = true`
- Botão/envio funcionam com valor privado no backend (`ConversationAccessService` resolve)
- Textos livres de mensagens e notas **não** mascarados (regra de produto)
- Mídia: armazenada em `storage/app/private`; download via rota autenticada
- `provider_message_id`, `remote_jid`, `webhook_secret`, `api_key` **nunca** no navegador

---

## Workers e Scheduler (Produção)

```bash
# Worker filas (mínimo 1, ideal 2+)
php artisan queue:work redis --queue=default,topweb_chat --sleep=2 --tries=3 --timeout=120 --max-jobs=1000

# Scheduler (cron Laravel)
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

**Scheduler registrado em `routes/console.php`:**
```php
Schedule::command('topweb-chat:reconcile --state')->everyMinute();
Schedule::command('topweb-chat:reconcile --history --limit=20')->everyFiveMinutes();
```

---

## Traduções

- `packages/Webkul/TopwebChat/src/Resources/lang/en/app.php`
- `packages/Webkul/TopwebChat/src/Resources/lang/pt_BR/app.php`
- Novo idioma: criar `Resources/lang/<locale>/app.php` com mesmas chaves
- Após alterações: `php artisan optimize:clear && php artisan view:cache`

---

## Validação e Testes

```bash
# Rotas do módulo
php artisan route:list --name=topweb_chat

# Testes Pest
php artisan test tests/Feature/TopwebChatAccessTest.php tests/Feature/TopwebChatProviderTest.php

# Pós-deploy / atualização
php artisan migrate --force
php artisan queue:restart
php artisan optimize:clear
php artisan view:cache

# Verificar colunas outbox
php artisan tinker --execute="echo Schema::hasColumns('topweb_chat_messages', ['operation_key','attempts','last_error']) ? 'OK' : 'MISSING';"
```

---

## Troubleshooting Comum

| Sintoma | Causa Provável (OpenWA Spec) | Ação |
|---------|-------------------------------|------|
| Instância `created`/`disconnected` | Sessão não iniciada / QR não escaneado | `POST /start` → `GET /qr` → escanear QR ou pairing code |
| Instância `qr_ready` mas não avança | WhatsApp Web não carrega / proxy bloqueado | Verificar `proxyUrl` se configurado; `force-kill` + `start` |
| Instância `failed` | Erro real do engine (não rede) | **Não adotada por takeover** — investigar `lastError` + logs OpenWA |
| Webhook 401/403 | HMAC inválido | Verificar `webhook_secret` idêntico no OpenWA e TopwebCRM; raw body |
| Mensagens `unknown` | Timeout OpenWA / rede / engine error | Verificar logs OpenWA (`docker logs openwa-api`); conectividade CRM↔OpenWA |
| Mídia não baixa | `MEDIA_DOWNLOAD_ENABLED=false` ou > 50MB ou URL-based send | Verificar env OpenWA; `GET /media` retorna 404 se não armazenado |
| Duplicidade inbound | Webhook re-fired | `WebhookEvent` idempotente por `provider_event_id` (idempotencyKey) |
| Status `sent` não avança | Destinatário offline / WhatsApp não enviou ack | **Comportamento normal** — não é falha; `contacts/check` diferencia não-registrado |
| Rate limit 429 | `SEND_PACING_ENABLED` ou global IP limit | Respeitar `Retry-After`; backoff exponencial no job |
| `session.reconnect_loop` webhook | A cada 5ª tentativa falha | Alerta operacional; verificar conectividade OpenWA→WhatsApp |

---

## Monitoramento

| Componente | Health Check |
|------------|--------------|
| **OpenWA** | `GET http://openwa:2785/api/health` (public) |
| **OpenWA Liveness** | `GET http://openwa:2785/api/health/live` → `{status:"ok"}` |
| **TopwebCRM** | `GET https://crm.seudominio.com/up` |
| **Filas Laravel** | `php artisan queue:monitor redis --timeout=60` |
| **Logs** | `storage/logs/laravel.log` (filtrar `topweb_chat`) |
| **OpenWA Logs** | `docker logs openwa-api 2>&1 | grep -i webhook` |
| **Webhook Failures** | `GET /api/webhooks/delivery-failures` (ADMIN) |
| **Métricas OpenWA** | Dashboard Web > Sessions > Metrics / Prometheus `/metrics` (Bearer METRICS_TOKEN) |

---

## Backup e Rotação de Segredos

| Item | Frequência | Método |
|------|------------|--------|
| **OpenWA PostgreSQL** | Diário | `pg_dump` + S3/MinIO |
| **OpenWA SQLite** (se dev) | Diário | Copy `data/openwa.db` |
| **MinIO/S3 (mídia)** | Contínuo | Replicação cross-region |
| **TopwebCRM MySQL** | Diário | `mysqldump` + S3 |
| **TopwebCRM `storage/app/private`** | Diário | Sync S3 |
| **API Keys OpenWA** | 90 dias | `POST /api/auth/api-keys` (nova) → revogar antiga |
| **Webhook Secrets** | 90 dias | Atualizar no OpenWA (`PUT /webhooks/:id`) + TopwebCRM |
| **APP_KEY Laravel** | Anual | Rotacionar + re-encrypt campos criptografados |

---

## Referências Oficiais OpenWA

- **Repo:** https://github.com/rmyndharis/OpenWA
- **Swagger UI:** `http://openwa:2785/api/docs` (dev) | `https://openwa.seudominio.com/api/docs` (prod)
- **OpenAPI Spec:** `openapi.json` na raiz do repo
- **Porta padrão:** 2785
- **Base path:** `/api`