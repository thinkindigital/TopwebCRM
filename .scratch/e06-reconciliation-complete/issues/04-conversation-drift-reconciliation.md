## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Recalcular `Conversation.unread_count` baseado nas mensagens inbound reais:
1. Query: `Message` onde `conversation_id`, `direction='in'`, `status != 'read'`
2. Atualizar `Conversation.unread_count` = count
3. Atualizar `Conversation.last_message_at` = max(`Message.created_at`) onde `direction='in'`
4. Executar no job `--history` (everyFiveMinutes) e opcionalmente `--state`

## Acceptance Criteria
- [ ] `unread_count` sempre = count inbound `status != 'read'`
- [ ] `last_message_at` = timestamp da última inbound
- [ ] Executado automaticamente no scheduler `everyFiveMinutes`
- [ ] Log: `reconciliation.conversation_drift` com `conversation_id`, `before_count`, `after_count`
- [ ] Testes: criar mensões inbound com status variados → assert count correto

## Blocked by
- #02 (message status reconciliation)

## Verification
```bash
# Verificar: Conversation.unread_count vs count(Message inbound status != 'read')
```