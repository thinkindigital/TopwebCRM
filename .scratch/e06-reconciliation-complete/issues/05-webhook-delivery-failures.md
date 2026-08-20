## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Monitoramento de `GET /api/webhooks/delivery-failures` (ADMIN) no OpenWA:
1. Job periódico (daily ou everyFiveMinutes) consulta endpoint
2. Para cada delivery falho esgotado: log estruturado + notificação admin
3. UI Admin: página "Webhook Failures" (DataGrid) com:
   - `delivery_id`, `event`, `session_id`, `url`, `last_status_code`, `attempts`, `failed_at`, `error`
   - Botão "Reprocessar" → re-dispatch manual via `POST /webhooks/:id/test` ou re-enqueue job
   - Filtros: session, event, date range, status

## Acceptance Criteria
- [ ] Job `topweb-chat:reconcile --webhook-failures` (daily)
- [ ] UI Admin acessível via menu TopwebChat > Webhook Failures
- [ ] DataGrid com paginação, filtros, export
- [ ] Botão "Reprocessar" dispara re-dispatch manual
- [ ] Log: `reconciliation.webhook_failure` com `delivery_id`, `action=alerted|reprocessed`
- [ ] Testes: mock delivery-failures response → assert UI + reprocess

## Blocked by
- #01 (instance status para saber sessões ativas)

## Verification
```bash
php artisan topweb-chat:reconcile --webhook-failures
# Acessar: /admin/topweb-chat/webhook-failures
```