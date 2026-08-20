## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Job de reconciliação `topweb-chat:reconcile --state` executado everyMinute pelo scheduler. Para cada `Instance` com `enabled=true`:
1. `GET /api/sessions/:sessionId` no OpenWA
2. Comparar: `status`, `engineLoaded`, `restriction`, `lastActive`, `phone`, `pushName`
3. Atualizar `Instance` local se divergente
4. Se `status='failed'` → alerta (log + notificação admin) + **não** tentar auto-recovery (INV-7: FAILED terminal, não adotado por takeover)
5. Se `status='disconnected'` e `engineLoaded=false` → opcional: disparar `POST /start` se config `auto_start=true`

## Acceptance Criteria
- [ ] Comando `php artisan topweb-chat:reconcile --state` executa sem erros
- [ ] Scheduler registra job `everyMinute()` em `routes/console.php`
- [ ] Instance status local sincronizado com OpenWA (status, engineLoaded, restriction)
- [ ] `status='failed'` gera log estruturado `channel=reconciliation level=warning` + notificação
- [ ] Não há auto-recovery de `failed` (respeita INV-7)
- [ ] Log de auditoria: `reconciliation.instance_status` com `before`/`after`
- [ ] Testes: mock OpenWA responses (ready, disconnected, failed, restriction) → assert Instance updated

## Blocked by
- Nenhuma (independente)

## Verification
```bash
php artisan topweb-chat:reconcile --state
php artisan schedule:run  # verifica registro
# Verificar logs: storage/logs/laravel.log | grep reconciliation.instance_status
```