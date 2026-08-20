## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Domínio Chamadas (Calls) e Canais (Channels/Newsletters):
1. **Calls** (OpenWA events: `call.received`, `call.accepted`, `call.rejected`, `call.missed`):
   - Entidade `TopwebChatCall`: `id`, `conversation_id`, `call_id` (OpenWA), `from_jid`, `is_video`, `is_group`, `status` (ringing/accepted/rejected/missed), `started_at`, `ended_at`, `duration_seconds`
   - Webhook handlers: `ProcessCallReceived`, `ProcessCallEnded` (accepted/rejected/missed)
   - UI: badge "Chamada perdida" na conversa + log de chamadas no detalhe
   - Outbound: `POST /api/sessions/:sessionId/calls/:callId/reject` (rejeitar ativamente)

2. **Channels/Newsletters** (OpenWA: `status.received` opt-in):
   - Entidade `TopwebChatChannel`: `id`, `instance_id`, `channel_jid`, `name`, `description`, `followers_count`
   - Webhook `status.received` → `ProcessChannelStatus` → cria/atualiza Channel + `ChannelStatus` (mensagem do canal)
   - UI: menu TopwebChat > Canais (read-only, monitoramento)
   - ACL: `topweb_chat.calls.view`, `topweb_chat.channels.view`

## Acceptance Criteria
- [ ] Entidades `TopwebChatCall`, `TopwebChatChannel`, `TopwebChatChannelStatus`
- [ ] Webhooks call.* + status.received processados
- [ ] UI: chamadas perdidas visíveis na conversa + log de chamadas
- [ ] Outbound: rejeitar chamada ativa (POST /reject)
- [ ] Canais: listagem + status recebidos (read-only)
- [ ] ACL separada para calls/channels
- [ ] Testes: mock call webhooks → assert Call criada + status transitions

## Blocked by
- #20 (groups domain — similar infra)
- `OpenWaProvider` calls/channels methods
- Verificar suporte OpenWA: `call.*` events + `status.received` (opt-in)

## Verification
```bash
# Mock call.received → verificar Call criada status=ringing
# Mock call.accepted → status=accepted + duration calculada
# Mock status.received → ChannelStatus criada
```