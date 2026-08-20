## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Política de retenção configurável para mídia privada:
1. **Config** `config/topweb-chat.php`:
   ```php
   'media' => [
     'retention_ttl_days' => env('TOPWEB_CHAT_MEDIA_RETENTION_DAYS', 365), // 0 = nunca expira
     'cleanup_schedule' => 'daily', // daily|weekly|monthly
     'max_size_mb' => 50,
     'allowed_mimes' => [...],
   ]
   ```
2. **Command** `topweb-chat:media-cleanup`:
   - Query: `Message` com `metadata.downloaded_at < now() - retention_ttl_days`
   - Para cada: deleta arquivo `storage/app/private/{path}` se existe
   - Atualiza `Message.metadata.media_expired=true`, `media_expired_at`
   - Log: `media.retention_cleanup` com `count`, `freed_bytes`
3. **Scheduler**: `daily()` (ou conforme config)
4. **UI Admin**: Configurações TopwebChat > Mídia > Retenção (TTL dias, agendamento)

## Acceptance Criteria
- [ ] Config `topweb_chat.media.retention_ttl_days` (default 365, 0 = nunca)
- [ ] Command `php artisan topweb-chat:media-cleanup` executa limpeza
- [ ] Scheduler `daily()` registrado
- [ ] Deleta arquivo físico + atualiza metadata `media_expired=true`
- [ ] Log `media.retention_cleanup` com contagem + bytes liberados
- [ ] UI Admin para configurar TTL + agendamento
- [ ] Testes: criar mídia antiga → rodar cleanup → assert arquivo removido + metadata updated

## Blocked by
- #11-14 (media pipeline completo)

## Verification
```bash
php artisan topweb-chat:media-cleanup
# Verificar: storage/app/private/topweb_chat/... arquivos antigos removidos
# Verificar: Message.metadata.media_expired=true
```