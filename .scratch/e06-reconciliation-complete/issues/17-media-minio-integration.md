## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Integração futura com MinIO/S3 para armazenamento de mídia (substitui `storage/app/private`):
1. **Config** `config/topweb-chat.php`:
   ```php
   'media' => [
     'disk' => env('TOPWEB_CHAT_MEDIA_DISK', 'private'), // private|minio|s3
     'minio' => [
       'endpoint' => env('MINIO_ENDPOINT'),
       'bucket' => env('MINIO_BUCKET', 'topweb-chat'),
       'region' => env('MINIO_REGION', 'us-east-1'),
       'access_key' => env('MINIO_ACCESS_KEY'),
       'secret_key' => env('MINIO_SECRET_KEY'),
       'use_path_style' => true,
     ],
   ]
   ```
2. **Filesystem** Laravel: `minio` disk (league/flysystem-aws-s3-v3)
3. **Bucket policy**: versioning habilitado, lifecycle rules (TTL), CORS para signed URLs
4. **Path pattern**: `{conversation_id}/{message_id}.{ext}` (mesmo pattern local)
5. **Migração**: command `topweb-chat:media-migrate --to=minio` — copia arquivos locais → MinIO, atualiza `storage_path` no metadata
6. **Signed URLs**: para outbound `url` + operador download → `Storage::temporaryUrl()`

## Acceptance Criteria
- [ ] Config `media.disk` (private|minio|s3)
- [ ] MinIO disk configurado no Laravel
- [ ] Bucket `topweb-chat` criado com versioning + lifecycle
- [ ] Path pattern `{conversation_id}/{message_id}.{ext}` mantido
- [ ] Command `topweb-chat:media-migrate --to=minio` migra + atualiza metadata
- [ ] Signed URLs funcionam para outbound/download operador
- [ ] Testes: upload → MinIO → download → assert integridade

## Blocked by
- #15 (retention policy)
- MinIO/S3 infra provisionada

## Verification
```bash
php artisan topweb-chat:media-migrate --to=minio
# Verificar: MinIO bucket topweb-chat com arquivos
# Verificar: Message.metadata.storage_path atualizado
# Download via signed URL → 200
```