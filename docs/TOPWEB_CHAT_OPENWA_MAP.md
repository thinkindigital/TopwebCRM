# Mapa TopwebChat ↔ OpenWA (Especificação Oficial)

> Baseado em: `docs/openwa/06-api-specification.md`, `docs/openwa/07-api-collection.md`, `docs/openwa/31-session-lifecycle-design.md`
> OpenWA roda na porta **2785** por padrão: `http://<host>:2785/api`

## Visão Geral

OpenWA (https://github.com/rmyndharis/OpenWA) — API WhatsApp self-hosted, multi-sessão, HMAC webhooks, API Key auth, Docker/PostgreSQL/SQLite/Redis/S3, Swagger docs em `/api/docs` (habilitar `ENABLE_SWAGGER=true`).

## Autenticação e Base

| Item | Especificação Oficial |
|------|----------------------|
| **Base URL** | `http://<host>:2785/api` |
| **Auth** | Header `X-API-Key: owa_k1_...` (header-only, nunca query param) |
| **Roles** | `viewer` (read), `operator` (write), `admin` (management) |
| **Session ID** | **UUID** retornado no `POST /api/sessions` (nunca o nome) |
| **Response** | Raw payload (sem envelope `{success,data}`) |
| **Errors** | NestJS default: `{statusCode, message, error}` |

## Contratos Implementados (Endpoints Reais)

| Uso | Endpoint OpenWA (Real) | Implementação TopwebChat |
|-----|------------------------|--------------------------|
| **Criar sessão** | `POST /api/sessions` | Admin UI: cria instância + sessão no OpenWA |
| **Listar sessões** | `GET /api/sessions` | Reconciliação: sincroniza status |
| **Obter sessão** | `GET /api/sessions/:sessionId` | Detalhes da instância |
| **Iniciar sessão** | `POST /api/sessions/:sessionId/start` | Botão "Iniciar" na UI |
| **Parar sessão** | `POST /api/sessions/:sessionId/stop` | Botão "Parar" |
| **Logout (unlink)** | `POST /api/sessions/:sessionId/logout` | Botão "Desconectar" (remove device) |
| **Force-kill** | `POST /api/sessions/:sessionId/force-kill` | Emergência |
| **QR Code** | `GET /api/sessions/:sessionId/qr` | Exibir QR na configuração |
| **Pairing Code** | `POST /api/sessions/:sessionId/pairing-code` | Alternativa ao QR |
| **Config sessão** | `GET/PATCH /api/sessions/:sessionId/config` | Auto-reject calls, reconnect settings |
| **Enviar texto** | `POST /api/sessions/:sessionId/messages/send-text` | Job assíncrono + `operation_key` |
| **Enviar mídia** | `POST /api/sessions/:sessionId/messages/send-image\|video\|audio\|document\|sticker` | Job + download server-side → disco privado |
| **Enviar localização** | `POST /api/sessions/:sessionId/messages/send-location` | Futuro |
| **Enviar contato** | `POST /api/sessions/:sessionId/messages/send-contact` | Futuro |
| **Enviar sticker** | `POST /api/sessions/:sessionId/messages/send-sticker` | Futuro |
| **Enviar enquete** | `POST /api/sessions/:sessionId/messages/send-poll` | Futuro |
| **Responder (quote)** | `POST /api/sessions/:sessionId/messages/reply` | Futuro (quotedMessageId) |
| **Encaminhar** | `POST /api/sessions/:sessionId/messages/forward` | Futuro |
| **Reação** | `POST /api/sessions/:sessionId/messages/react` | Futuro (message.reaction webhook) |
| **Deletar msg** | `POST /api/sessions/:sessionId/messages/delete` | Futuro |
| **Editar msg** | `POST /api/sessions/:sessionId/messages/edit` | Futuro (message.edited webhook) |
| **Bulk send** | `POST /api/sessions/:sessionId/messages/send-bulk` | Futuro |
| **Histórico** | `GET /api/sessions/:sessionId/messages/:chatId/history` | Catch-up paginado |
| **Media download** | `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` | **Obrigatório** - URLs são temporárias |
| **Reações** | `GET /api/sessions/:sessionId/messages/:chatId/:messageId/reactions` | Futuro |
| **Verificar contato** | `GET /api/sessions/:sessionId/contacts/check/:number` | Pré-validação antes de enviar |
| **Obter contato** | `GET /api/sessions/:sessionId/contacts/:contactId` | Resolução de identidade |
| **Resolver telefone** | `GET /api/sessions/:sessionId/contacts/:contactId/phone` | Mapear @lid → telefone |
| **Listar grupos** | `GET /api/sessions/:sessionId/groups` | Domínio Grupos (E06) |
| **Info grupo** | `GET /api/sessions/:sessionId/groups/:groupId` | Domínio Grupos |
| **Webhooks (mgmt)** | `POST/GET/PUT/DELETE /api/sessions/:sessionId/webhooks` | Configuração automática na UI |
| **Test webhook** | `POST /api/sessions/:sessionId/webhooks/:id/test` | Validação na UI |

## Webhook Events (Entrada - Oficial)

| Evento OpenWA | Quando Dispara | `data` Payload (Resumo) |
|---------------|----------------|-------------------------|
| `message.received` | Msg inbound chega | Full message obj: `id`, `from`, `to`, `body`, `type`, `timestamp` (epoch sec), `isGroup`, `kind`, `hasMedia`, `contact{name?,pushName?}`, `senderPhone` (opt-in `RESOLVE_LID_TO_PHONE`) |
| `message.sent` | Msg outbound criada/enviada | Mesmo shape de `message.received` |
| `message.ack` | Delivery/read receipt atualiza msg outbound | `{id, messageId, status, ack}` — status: `pending|sent|delivered|read|failed` |
| `message.failed` | Receipt resolve para `failed` | `{id, messageId, status:"failed", ack:-1}` |
| `message.revoked` | Msg deletada/recalled | `{id, revokedId?, chatId, from, to, type:"revoked", body:"", timestamp}` — reconciliar por `revokedId` |
| `message.reaction` | Reação add/change/remove | `{messageId, chatId, reaction, senderId, reactions?}` |
| `message.edited` | Body/caption editado | `{messageId, chatId, body, senderId, from, to, fromMe, isGroup, type, hasMedia, author?, mentionedIds?, timestamp}` (epoch sec) |
| `session.qr` | Novo QR gerado | `{sessionId, qr}` — QR é **PNG data URL** |
| `session.authenticated` | Sessão pareia e fica ready | `{sessionId, phone, pushName}` |
| `session.disconnected` | Desconexão engine/WhatsApp (não API stop/logout/delete) | `{sessionId, reason}` |
| `session.reconnect_loop` | A cada 5ª tentativa reconexão | `{sessionId, attempts, nextDelayMs}` |
| `session.restriction` | WhatsApp coloca/levanta restrição | `{sessionId, active, kind, code, expiresAt}` — kinds: `reachout_timelock`, `tos_block`, `proxy_block` |
| `session.status` | Transição de status | `{sessionId, status}` — valores: `created|initializing|qr_ready|authenticating|ready|disconnected|action_required|failed` |
| `group.join` | Participantes adicionados | `{groupId, actorId?, participantIds, timestamp}` |
| `group.leave` | Participantes saem/removidos | `{groupId, actorId?, participantIds, timestamp}` |
| `group.update` | Metadados grupo mudam | `{groupId, actorId?, participantIds, changes?}` — changes: `subject?`, `description?`, `announce?`, `locked?` |
| `group.join_request` | Pedido para entrar (approval mode) | `{groupId, actorId?, participantIds, timestamp}` |
| `call.received` | Call voice/video inicia | `{callId, from, isVideo, isGroup, timestamp}` |
| `call.accepted/rejected/missed` | Call finalizada | **Baileys only** — `{sessionId, callId, from, outcome, isVideo, isGroup, timestamp}` |
| `status.received` | Contato posta status/story | **Opt-in** — `{sessionId, statusId, contact{...}, type, caption?, hasMedia, mediaOmitted, postedAt, expiresAt}` (epoch ms) |

## Webhook Delivery & Security (Oficial)

| Aspecto | Especificação |
|---------|---------------|
| **Delivery** | At-least-once (pode duplicar) |
| **Idempotency Key** | Header `X-OpenWA-Idempotency-Key` (content-derived, stable across retries) |
| **Delivery ID** | Header `X-OpenWA-Delivery-Id` (fresh `dlv_<uuid>` per delivery) |
| **Retry Count** | Header `X-OpenWA-Retry-Count` (0 = first) |
| **HMAC Signature** | Header `X-OpenWA-Signature: sha256=<hex>` (HMAC-SHA256 over **raw JSON body** usando `secret` do webhook) |
| **Event Header** | `X-OpenWA-Event` (mirrors `event`) |
| **Retries** | Exponential backoff: base `WEBHOOK_RETRY_DELAY` (default 5s), max `retryCount` (default 3) |
| **Queue** | `QUEUE_ENABLED=true` + Redis (BullMQ) — torna dispatch durável desde enqueue |
| **SSRF Guard** | Validação no registration (não só delivery) — rejeita private/internal/loopback IPs |
| **Media Inline** | Up to `WEBHOOK_MEDIA_INLINE_MAX_BYTES` (default 1MiB); larger → marker `{mimetype, filename?, omitted:true, sizeBytes}` |
| **Payload Cap** | `WEBHOOK_MAX_PAYLOAD_BYTES` (default 1MiB) — shed media before dropping |

## Idempotency Keys (Derivação Oficial)

| Evento | Chave |
|--------|-------|
| `message.received` / `message.sent` | `msg_{sessionId}_{messageId}` |
| `message.ack` | `ack_{sessionId}_{messageId}_{status}` |
| `message.failed` | `failed_{sessionId}_{messageId}_{status}` |
| `message.revoked` | `rev_{sessionId}_{messageId}` |
| `message.edited` | `edit_{sessionId}_{messageId}_{occurredAt}` |
| `message.reaction` | `react_{sessionId}_{messageId}_{senderId}_{occurredAt}` |
| `session.status` | `sess_{sessionId}_{status}_{occurredAt}` |
| `session.authenticated` | `auth_{sessionId}_{hash(data)}_{occurredAt}` |
| `session.disconnected` | `disc_{sessionId}_{hash(reason)}_{occurredAt}` |
| `group.join/leave` | `grp_{groupId}_{hash(participantIds)}_{join|leave}_{occurredAt}` |
| `call.received` | `call_{sessionId}_{callId}` |

## Entidades Persistidas (Ajustadas para OpenWA Real)

| Entidade | Campos Chave (OpenWA) |
|----------|----------------------|
| `Instance` | `provider='openwa'`, `session_uuid` (UUID do OpenWA), `session_name` (nome), `base_url` (ex: `http://openwa:2785`), `api_key` (criptografado), `webhook_secret` (HMAC, criptografado), `status` (lowercase), `enabled`, `engine_loaded` (bool) |
| `Conversation` | `remote_jid` criptografado (`5511999999999@c.us`), `remote_jid_key` (hash), `provider='openwa'`, `session_uuid` (FK Instance) |
| `Message` | `provider_message_id` (OpenWA message ID, ex: `true_5511999999999@c.us_3EB0ABCD`), `type` (text,image,video,audio,voice,document,sticker,location,contact,poll,call,revoked,masked,unknown), `status` (queued,sent,delivered,read,failed,unknown), `metadata` JSON criptografado (reactions, edit_history, quotedMessage, etc.), `timestamp` (epoch seconds) |
| `InternalNote` | Inalterado |
| `WebhookEvent` | `provider='openwa'`, `provider_event_id` (idempotency key), `event_type` (ex: `message.received`), `signature_header` (HMAC), payload criptografado, `retry_count`, `status` |

## Fluxos (Corrigidos para OpenWA Real)

**Saída (Envio):**
```text
Blade → MessageController → ConversationAccessService
      → MessageService → Message queued (operation_key + status=queued)
      → SendMessage (Job) → OpenWaProvider::sendText()
      → POST /api/sessions/{session_uuid}/messages/send-text
         Headers: X-API-Key, Content-Type: application/json
         Body: {chatId: "5511999999999@c.us", text: "..."}
      → Response 201: {messageId: "true_...", timestamp: 1719312000}
      → Atualiza Message: provider_message_id, status=sent
      → Webhook message.ack/message.failed (confirmação assíncrona status monotônico)
```

**Entrada (Webhook):**
```text
OpenWA → POST /api/topweb-chat/webhooks/openwa/{session_uuid}
       → Headers: X-OpenWA-Signature, X-OpenWA-Event, X-OpenWA-Idempotency-Key, X-OpenWA-Delivery-Id, X-OpenWA-Retry-Count
       → WebhookController valida HMAC SHA256 (raw body) + secret criptografado
       → Persiste WebhookEvent idempotente (provider_event_id = idempotencyKey)
       → Job ProcessWebhookEvent → WebhookProcessor
       → Normaliza remote_jid (from/to) → RemoteIdentityService
       → Busca/cria Conversation (Pessoa obrigatória via contacts/check ou telefone)
       → Persiste Message inbound (status=received/delivered conforme ack)
       → Atualiza contadores unread, last_message_at
       → UI polling 5s pega timeline atualizada
```

## Configuração de Instância (OpenWA Real)

### No OpenWA (via API ou Dashboard):
```bash
# Criar sessão
curl -X POST "http://openwa:2785/api/sessions" \
  -H "X-API-Key: owa_k1_admin_key" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "crm-topweb",
    "webhook": "https://crm.seudominio.com/api/topweb-chat/webhooks/openwa/SESSION_UUID",
    "webhook_secret": "SEGREDO_HMAC_32_CHARS",
    "events": ["message.received","message.sent","message.ack","message.failed","message.revoked","message.reaction","message.edited","session.status","session.authenticated","session.disconnected","group.join","group.leave","group.update","call.received"],
    "retryCount": 3
  }'
```

### No TopwebCRM (Admin UI):
1. **Topweb Chat > Configurações > Nova Instância**
2. Provider: `openwa`
3. Session UUID: copiar do retorno do `POST /api/sessions` (campo `id`)
4. Session Name: `crm-topweb` (igual ao `name` no OpenWA)
5. Base URL: `http://openwa:2785` (interno Docker) ou `https://openwa.seudominio.com` (externo)
6. API Key: gerada no OpenWA (`owa_k1_...` com role `operator`)
7. Webhook Secret: mesmo valor usado no OpenWA (`SEGREDO_HMAC_32_CHARS`)
8. Salvar → clicar **Configurar webhook** (faz `POST /webhooks/:id/test`)

## Media Handling (Crítico)

| Ponto | Especificação OpenWA | Implicação TopwebChat |
|-------|---------------------|----------------------|
| **Upload** | `url` (http/https) **OU** `base64` (exactly one) | Aceitar ambos no upload |
| **Mimetype** | Obrigatório quando `base64` | Validar no backend |
| **Size Limit** | `MEDIA_DOWNLOAD_MAX_BYTES` = **50 MiB** (52,428,800 bytes) | Rejeitar > 50MB |
| **Download** | **URLs são temporárias/assinadas** → `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` | **Obrigatório** download server-side → `storage/app/private` |
| **Archive** | Opt-in: `CHAT_MEDIA_ARCHIVE_ENABLED` (S3/local) | Habilitar para durabilidade |
| **Outbound URL** | Gateway fetches at send time, **não armazena** | Não há cópia persistida para URL-based sends |

## Rate Limiting & Send Pacing

| Feature | Config | Comportamento |
|---------|--------|---------------|
| **Global rate limit** | Per-client-IP tiers + per-instance ingress limit | 429 com `Retry-After` |
| **Send pacing** | `SEND_PACING_ENABLED=true` (opt-in) | 429 `SEND_PACING_LIMITED` + `retryAfterSeconds` |
| **Warm-up cap** | `SEND_PACING_WARMUP_SCHEDULE` | Cresce com idade da sessão |
| **Cold reachout cap** | `SEND_PACING_COLD_DAILY_CAP` | Novas conversas/dia |

## Session Status Wire Values (Lowercase!)

```
created → initializing → qr_ready → authenticating → ready
                                    ↘ disconnected (stop/logout/force-kill/drop)
                                    ↘ action_required (ex: reconnect needed)
                                    ↘ failed (terminal - não adotado por takeover)
```

## Health & Monitoring

| Endpoint | Descrição |
|----------|-----------|
| `GET /api/health` | Basic health (public) |
| `GET /api/health/live` | Liveness probe — sempre `{status:"ok"}` (public) |
| `GET /api/sessions/stats/overview` | Stats multi-sessão (ADMIN) |
| `GET /api/webhooks/delivery-failures` | Webhooks que esgotaram retries (ADMIN) |

## Limitações Conhecidas (Oficiais)

1. **201 ≠ Delivered** — só "accepted by gateway"; delivery async via `message.ack`
2. **Unregistered numbers** — WhatsApp não rejeita sincronamente; `contacts/check` pré-valida
3. **History pagination** — `limit` clamped 1-100 (deep=true → 2000, whatsapp-web.js only)
4. **Media inline budget** — 8 MiB encoded base64 per response (newest-first)
5. **Presence** — Baileys only; `POST /presence/subscribe` required first
6. **FAILED sessions** — Não adotadas por takeover; intervenção humana necessária
6. **Logout** — Não garante remoção no handset UI; só unlink local
7. **@lid senders** — `senderPhone` opt-in via `RESOLVE_LID_TO_PHONE=true`

## Referência Externa

- Repo: https://github.com/rmyndharis/OpenWA
- Swagger: `http://openwa:2785/api/docs` (dev) ou `https://openwa.seudominio.com/api/docs`
- OpenAPI JSON: `openapi.json` na raiz do repo