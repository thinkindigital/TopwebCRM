## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Edição de mensagem (própria):
1. **Inbound** (webhook `message.edited`):
   - Payload: `{messageId, chatId, body, senderId, from, to, fromMe, isGroup, type, hasMedia, author?, mentionedIds?, timestamp}`
   - Job `ProcessMessageEdit` → atualiza `Message.content` = novo `body`, `metadata.edited=true`, `metadata.edit_history.push({old_body, new_body, edited_at, edited_by})`
   - Se `hasMedia=true` + `caption` alterado → atualiza `metadata.caption`
2. **Outbound** (operador edita):
   - UI: hover mensagem própria → ícone editar → modal com texto atual → salvar
   - `POST /api/sessions/:sessionId/messages/edit` com `{chatId, messageId, body}`
   - `OpenWaProvider::editMessage()` → envia para OpenWA
3. **Regra**: apenas mensagens `fromMe=true` (próprias) editáveis
4. **Persistência**: `Message.content` = versão atual; `metadata.edit_history` = array de edições

## Acceptance Criteria
- [ ] Webhook `message.edited` processado → `Message.content` atualizado + `edit_history`
- [ ] Outbound: editar mensagem própria → POST /edit → OpenWA confirma
- [ ] UI: apenas mensagens próprias mostram ícone editar
- [ ] Histórico de edições visível (tooltip ou expandível)
- [ ] Testes: mock edit webhook → assert content + edit_history; outbound edit → 200

## Blocked by
- #18 (reactions infra similar)
- `OpenWaProvider::editMessage()` implementado

## Verification
```bash
# Mock webhook message.edited → verificar content + edit_history
# Frontend: editar mensagem própria → verificar OpenWA recebe
```