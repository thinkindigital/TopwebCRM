## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Job para mensagens locais com `status='unknown'` (timeout sem ack conclusivo). Para cada `Message` com `status='unknown'` e `reconcile_attempts < 5`:
1. `GET /api/sessions/:sessionId/messages/:chatId/history?limit=100` no OpenWA
2. Buscar por `provider_message_id` no histórico remoto
3. Se encontrado:
   - `delivered`/`read` → `Message.status=delivered`/`read` (monotônico)
   - `failed` → `Message.status=failed` + `last_error`
   - `sent` → mantém `unknown` (ainda sem ack final)
4. Se não encontrado → `reconcile_attempts++`, mantém `unknown`
5. Se `reconcile_attempts >= 5` → alerta + mantém `unknown` (não auto-converte para failed)

## Acceptance Criteria
- [ ] Comando `php artisan topweb-chat:reconcile --unknown` (ou flag no --history)
- [ ] Coluna `reconcile_attempts` em `topweb_chat_messages` (migration)
- [ ] Máximo 5 tentativas antes de alertar
- [ ] Nunca converte `unknown` → `failed` automaticamente (evita false positive)
- [ ] Log: `reconciliation.unknown_message` com `attempt`, `remote_status`, `action`
- [ ] Testes: mock history com/sem message → assert behavior correto

## Blocked by
- #02 (message status reconciliation infra)

## Verification
```bash
php artisan topweb-chat:reconcile --history --limit=20
# Verificar coluna reconcile_attempts em topweb_chat_messages
```