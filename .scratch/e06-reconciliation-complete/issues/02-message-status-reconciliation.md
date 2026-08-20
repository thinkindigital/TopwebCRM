## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Job `topweb-chat:reconcile --history` (everyFiveMinutes, limit 20 conversations). Para mensagens com `status IN ('sent','delivered')` e `updated_at > 10min` sem ack:
1. `GET /api/sessions/:sessionId/messages/:chatId/history?limit=50` no OpenWA
2. Comparar `status` remoto vs local para cada `provider_message_id`
3. Atualizar `Message.status` local (monotônico: `sent`→`delivered`→`read`; `failed` terminal)
4. Se remoto=`failed` → `Message.status=failed` + `last_error` do histórico
5. Atualizar `Conversation.last_message_at` e `unread_count` se inbound

## Acceptance Criteria
- [ ] Comando `php artisan topweb-chat:reconcile --history --limit=20` executa
- [ ] Scheduler `everyFiveMinutes()` registrado
- [ ] Status monotônico respeitado: nunca rebaixa `delivered`/`read` para `sent`/`failed`
- [ ] `failed` terminal: uma vez `failed`, não volta para `sent`/`delivered`
- [ ] `Conversation.unread_count` recalculado corretamente
- [ ] Log: `reconciliation.message_status` com `message_id`, `before_status`, `after_status`, `source=openwa_history`
- [ ] Testes: mock history response com status variados → assert updates corretos

## Blocked by
- #01 (instance status deve estar `ready` para consultar history)

## Verification
```bash
php artisan topweb-chat:reconcile --history --limit=20
# Verificar: storage/logs/laravel.log | grep reconciliation.message_status
```