# Módulo WhatsApp Nativo (TopwebChat) — Multi-Provider com OpenWA Primário

**Contexto**: Precisávamos de atendimento e histórico conversacional WhatsApp dentro do CRM, desacoplado do provedor. RyzeAPI tornou-se paga e sem acesso para testes.

**Decisão**: 
1. Manter arquitetura provider-agnostic: `MessagingProvider` (contract) + adapters concretos
2. **Provedor primário: OpenWA** (https://github.com/rmyndharis/OpenWA) — self-hosted, 100% open source, multi-sessão, HMAC webhooks, API Key auth, Docker/PostgreSQL/SQLite/Redis/S3, Swagger docs. Deploy em KVM próprio. Porta padrão **2785**, base path `/api`.
3. Provedor secundário futuro: Evolution API (mesmo contrato `MessagingProvider`)
4. Pacote `packages/Webkul/TopwebChat` como extensão nativa Concord com: entidades (Instance, Conversation, Message, InternalNote, WebhookEvent), `MessagingProvider` + `OpenWaProvider`, webhook HMAC idempotente (header `X-OpenWA-Signature`), jobs assíncronos (Redis), outbox local com `operation_key`, ACL granular, concessão individual dados sensíveis.

**Por que OpenWA**: 
- Self-hosted → controle total, sem custos por mensagem, sem dependência de SaaS
- Open source → auditoria, customização, sem vendor lock-in
- Feature parity+: texto, mídia, reações, edição, bulk, grupos, canais, chamadas, labels, proxy, rate limiting, CIDR whitelist, audit logging
- Infra madura: Docker 1-command, PostgreSQL/SQLite, Redis cache, S3/MinIO, health checks, data migration
- Multi-sessão nativa → uma `Instance` = uma sessão OpenWA (session UUID)

**Arquitetura Multi-Provider**:
- `Instance.provider` define qual adapter usar (`openwa` | `evolution`)
- `ProviderFactory::make(Instance $instance): MessagingProvider`
- Config por instância: `base_url` (ex: `http://openwa:2785`), `api_key` (`owa_k1_...`), `webhook_secret` (HMAC SHA256), `session_uuid` (UUID do OpenWA), `session_name`
- Webhook route: `/api/topweb-chat/webhooks/{provider}/{session_uuid}`

**Camadas** (inalteradas):
Transporte (provider) → Ingestão (webhook HMAC → job) → Atendimento (conversa, mensagem, atribuição, nota) → CRM (Pessoa obrigatória, Lead opcional, Etapa pipeline)

**Endpoints OpenWA Oficiais (Implementados/Planejados)**:

| Operação | Endpoint Real | Auth | Status |
|----------|---------------|------|--------|
| Criar sessão | `POST /api/sessions` | API Key (OPERATOR unscoped) | Admin UI |
| Listar sessões | `GET /api/sessions` | API Key | Reconciliação |
| Obter sessão | `GET /api/sessions/:sessionId` | API Key | Detalhes |
| Iniciar sessão | `POST /api/sessions/:sessionId/start` | API Key (OPERATOR) | Botão "Iniciar" |
| Parar sessão | `POST /api/sessions/:sessionId/stop` | API Key (OPERATOR) | Botão "Parar" |
| Logout (unlink) | `POST /api/sessions/:sessionId/logout` | API Key (OPERATOR) | Botão "Desconectar" |
| Force-kill | `POST /api/sessions/:sessionId/force-kill` | API Key (OPERATOR) | Emergência |
| QR Code | `GET /api/sessions/:sessionId/qr` | API Key (OPERATOR) | Exibir QR |
| Pairing Code | `POST /api/sessions/:sessionId/pairing-code` | API Key (OPERATOR) | Alternativa QR |
| Config sessão | `GET/PATCH /api/sessions/:sessionId/config` | API Key (OPERATOR) | Auto-reject, reconnect |
| **Enviar texto** | `POST /api/sessions/:sessionId/messages/send-text` | API Key (OPERATOR) | ✅ Job + `operation_key` |
| **Enviar mídia** | `POST /api/sessions/:sessionId/messages/send-image\|video\|audio\|document\|sticker` | API Key (OPERATOR) | 🔄 Job + download server-side |
| Enviar localização | `POST /api/sessions/:sessionId/messages/send-location` | API Key (OPERATOR) | Futuro |
| Enviar contato | `POST /api/sessions/:sessionId/messages/send-contact` | API Key (OPERATOR) | Futuro |
| Enviar sticker | `POST /api/sessions/:sessionId/messages/send-sticker` | API Key (OPERATOR) | Futuro |
| Enviar enquete | `POST /api/sessions/:sessionId/messages/send-poll` | API Key (OPERATOR) | Futuro |
| Responder (quote) | `POST /api/sessions/:sessionId/messages/reply` | API Key (OPERATOR) | Futuro (`quotedMessageId`) |
| Encaminhar | `POST /api/sessions/:sessionId/messages/forward` | API Key (OPERATOR) | Futuro |
| Reação | `POST /api/sessions/:sessionId/messages/react` | API Key (OPERATOR) | Futuro |
| Deletar msg | `POST /api/sessions/:sessionId/messages/delete` | API Key (OPERATOR) | Futuro |
| Editar msg | `POST /api/sessions/:sessionId/messages/edit` | API Key (OPERATOR) | Futuro |
| Bulk send | `POST /api/sessions/:sessionId/messages/send-bulk` | API Key (OPERATOR) | Futuro |
| Histórico | `GET /api/sessions/:sessionId/messages/:chatId/history` | API Key | 🔄 Catch-up paginado |
| **Media download** | `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` | API Key | 🔄 **Obrigatório** → disco privado |
| Reações | `GET /api/sessions/:sessionId/messages/:chatId/:messageId/reactions` | API Key | Futuro |
| Verificar contato | `GET /api/sessions/:sessionId/contacts/check/:number` | API Key | Pré-validação |
| Obter contato | `GET /api/sessions/:sessionId/contacts/:contactId` | API Key | Resolução identidade |
| Resolver telefone | `GET /api/sessions/:sessionId/contacts/:contactId/phone` | API Key | `@lid` → telefone |
| Listar grupos | `GET /api/sessions/:sessionId/groups` | API Key | Domínio Grupos (E06) |
| Info grupo | `GET /api/sessions/:sessionId/groups/:groupId` | API Key | Domínio Grupos |
| Webhooks (mgmt) | `POST/GET/PUT/DELETE /api/sessions/:sessionId/webhooks` | API Key (OPERATOR) | Config automática UI |
| Test webhook | `POST /api/sessions/:sessionId/webhooks/:id/test` | API Key (OPERATOR) | Validação UI |

**Webhook Events OpenWA (Oficiais - Entrada)**:

| Evento | Quando | `data` Chave |
|--------|--------|--------------|
| `message.received` | Inbound chega | Full message: `id`, `from`, `to`, `body`, `type`, `timestamp` (epoch sec), `isGroup`, `kind`, `hasMedia`, `contact{name?,pushName?}`, `senderPhone` (opt-in) |
| `message.sent` | Outbound criado | Mesmo shape |
| `message.ack` | Delivery/read receipt | `{id, messageId, status, ack}` — status: `pending\|sent\|delivered\|read\|failed` |
| `message.failed` | Falha entrega | `{id, messageId, status:"failed", ack:-1}` |
| `message.revoked` | Msg deletada | `{id, revokedId?, chatId, from, to, type:"revoked", body:"", timestamp}` — reconciliar por `revokedId` |
| `message.reaction` | Reação add/remove | `{messageId, chatId, reaction, senderId, reactions?}` |
| `message.edited` | Body/caption editado | `{messageId, chatId, body, senderId, from, to, fromMe, isGroup, type, hasMedia, author?, mentionedIds?, timestamp}` (epoch sec) |
| `session.status` | Transição status | `{sessionId, status}` — `created\|initializing\|qr_ready\|authenticating\|ready\|disconnected\|action_required\|failed` |
| `session.authenticated` | Sessão ready | `{sessionId, phone, pushName}` |
| `session.disconnected` | Drop/conflito/unlink | `{sessionId, reason}` |
| `session.reconnect_loop` | A cada 5ª tentativa | `{sessionId, attempts, nextDelayMs}` |
| `session.restriction` | Restrição WhatsApp | `{sessionId, active, kind, code, expiresAt}` — kinds: `reachout_timelock`, `tos_block`, `proxy_block` |
| `group.join/leave/update` | Mudanças grupo | `{groupId, actorId?, participantIds, changes?}` |
| `call.received` | Call inicia | `{callId, from, isVideo, isGroup, timestamp}` |
| `call.accepted/rejected/missed` | Call finalizada | **Baileys only** |
| `status.received` | Story/status | **Opt-in** — `{sessionId, statusId, contact{...}, hasMedia, postedAt, expiresAt}` (epoch ms) |

**Webhook Security & Delivery (Oficial)**:
- **HMAC**: Header `X-OpenWA-Signature: sha256=<hex>` (HMAC-SHA256 sobre **raw JSON body** usando `secret`)
- **Idempotency**: Header `X-OpenWA-Idempotency-Key` (content-derived: `msg_{sessionId}_{messageId}` para msg, `sess_{sessionId}_{status}_{occurredAt}` para session.status, etc.) — **stable across retries**
- **Delivery ID**: Header `X-OpenWA-Delivery-Id` (`dlv_<uuid>`) — fresh per delivery
- **Retry Count**: Header `X-OpenWA-Retry-Count` (0 = first)
- **Event Header**: `X-OpenWA-Event` (mirrors `event`)
- **Retries**: Exponential backoff (base 5s, max `retryCount` default 3)
- **Queue**: `QUEUE_ENABLED=true` + Redis (BullMQ) — dispatch durável desde enqueue
- **SSRF Guard**: Validação no registration (rejeita private/internal/loopback IPs)
- **Media Inline**: Up to 1MiB (`WEBHOOK_MEDIA_INLINE_MAX_BYTES`); larger → marker `{mimetype, filename?, omitted:true, sizeBytes}`
- **At-least-once**: Consumer deve ser idempotente (dedupe por `X-OpenWA-Idempotency-Key`)

**Media Handling (Crítico - Oficial)**:
- Upload: `url` (http/https) **OU** `base64` (exactly one) + `mimetype` obrigatório para base64
- Size Limit: **50 MiB** (`MEDIA_DOWNLOAD_MAX_BYTES`) — rejeita maior
- **Download Obrigatório**: URLs OpenWA temporárias/assinadas → `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` → `storage/app/private`
- Archive: Opt-in `CHAT_MEDIA_ARCHIVE_ENABLED` (S3/local) para durabilidade
- Outbound URL-based: Gateway fetches at send time, **não armazena** — sem cópia persistida

**Session Status Wire Values (Lowercase!)**:
```
created → initializing → qr_ready → authenticating → ready
                                    ↘ disconnected (stop/logout/force-kill/drop)
                                    ↘ action_required
                                    ↘ failed (terminal — NÃO adotado por takeover)
```

**Riscos e Decisões Derivadas da Spec Oficial**:

1. **201 ≠ Delivered** — Response 201 = "accepted by gateway"; delivery confirmado assincronamente via `message.ack` (status monotônico: `sent`→`delivered`→`read`; `failed` terminal). Implementar `unknown` para timeout sem ack.

2. **Unregistered numbers** — WhatsApp não rejeita sincronamente. Usar `contacts/check` pré-envio para diferenciar não-registrado de offline.

3. **@lid senders** — `senderPhone` opt-in via `RESOLVE_LID_TO_PHONE=true` no OpenWA ou on-demand `GET /contacts/:contactId/phone`. Preservar `@lid` no `remote_jid` criptografado.

4. **FAILED sessions** — Não adotadas por takeover (INV-7). Intervenção humana obrigatória. Alertar operador.

5. **Media inline budget** — Webhook payload cap 1MiB; media > 1MiB → marker. Fetch via history endpoint quando necessário.

6. **Send pacing** — Opt-in `SEND_PACING_ENABLED`. Respeitar 429 `SEND_PACING_LIMITED` + `retryAfterSeconds`.

7. **QR/Pairing** — `GET /qr` retorna PNG data URL; `POST /pairing-code` retorna código 8 chars. Exibir na UI admin.

8. **Reconnect loop** — `session.reconnect_loop` webhook a cada 5ª tentativa. Alertar operacional.

**Riscos Atuais (Epic E06)**:
- OpenWA em desenvolvimento ativo → acompanhar breaking changes via Swagger/docs (`/api/docs`)
- Localhost sem túnel não recebe inbound → Cloudflare Tunnel para dev
- Mídia: URLs temporárias → **download server-side obrigatório** → `storage/app/private`
- Reconciliação automática drift (webhook ↔ estado local OpenWA)
- Quarentena identidade ambígua (múltiplas Pessoas ou nenhuma via `contacts/check`)
- Grupos/PIX/Canais/Chamadas → domínios separados com ACL própria (E06+)

**Referências Oficiais Consultadas**:
- `docs/openwa/06-api-specification.md` (6846 linhas)
- `docs/openwa/07-api-collection.md` (1793 linhas)
- `docs/openwa/31-session-lifecycle-design.md` (invariants INV-1 a INV-10)
- `docs/openwa/04-security-design.md` (HMAC, SSRF, rate limits)
- `docs/openwa/05-database-design.md` (webhooks, audit_logs, delivery_failures)