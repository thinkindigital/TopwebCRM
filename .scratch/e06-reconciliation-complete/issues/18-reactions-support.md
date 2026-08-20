## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Suporte a reações (emoji) em mensagens:
1. **Inbound** (webhook `message.reaction`):
   - Payload: `{messageId, chatId, reaction, senderId, reactions?}`
   - Job `ProcessReaction` → atualiza `Message.metadata.reactions` (JSON: `{emoji: [{senderId, timestamp}]}`)
   - Se `reaction` vazio → remove reação do sender
2. **Outbound** (operador reage):
   - UI: botão de reações na mensagem (hover/touch) → picker emoji
   - `POST /api/sessions/:sessionId/messages/react` com `{chatId, messageId, emoji}`
   - `OpenWaProvider::react()` → envia para OpenWA
3. **Persistência**: `Message.metadata.reactions` = array de objetos `{emoji, senderId, senderName, timestamp}`
4. **UI**: mostrar reações agrupadas por emoji + contagem + lista senders (tooltip)

## Acceptance Criteria
- [ ] Webhook `message.received` + `message.reaction` processados
- [ ] `Message.metadata.reactions` estruturado corretamente
- [ ] Outbound: picker emoji → POST /react → OpenWA confirma
- [ ] UI mostra reações agrupadas (emoji + count + senders)
- [ ] Remoção reação (emoji vazio) funciona
- [ ] Testes: mock reaction webhook → assert metadata; outbound react → 200

## Blocked by
- #11-14 (media pipeline)
- `OpenWaProvider::react()` implementado

## Verification
```bash
# Mock webhook message.reaction → verificar Message.metadata.reactions
# Frontend: reagir à mensagem → verificar OpenWA recebe
```