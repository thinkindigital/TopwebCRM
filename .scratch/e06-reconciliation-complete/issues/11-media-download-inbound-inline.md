## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Job `DownloadMedia` (queue `topweb_chat_media`) para mídia inbound **inline** (`media.data` base64, `sizeBytes <= 1MiB`):
1. Trigger: webhook `message.received` com `hasMedia=true` e `media.data` presente
2. Job assíncrono: decodifica base64 → valida MIME/tamanho → salva em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}`
3. Atualiza `Message.metadata` (criptografado):
   ```json
   {
     "mimetype": "image/jpeg",
     "filename": "photo.jpg",
     "sizeBytes": 102400,
     "sha256": "...",
     "storage_path": "topweb_chat/conv_123/msg_456.jpg",
     "downloaded_at": "2026-08-20T10:00:00.000Z",
     "source": "inline_base64"
   }
   ```
4. Se falha (MIME inválido, > 1MiB, decode error) → log error + `Message.metadata.media_download_failed=true` + alerta

## Acceptance Criteria
- [ ] Job `DownloadMedia` na queue `topweb_chat_media`
- [ ] Decodifica base64 → salva em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}`
- [ ] Valida MIME contra lista permitida + tamanho ≤ 1MiB (inline budget)
- [ ] Metadados completos em `Message.metadata` (criptografado)
- [ ] Falha → log estruturado + flag `media_download_failed`
- [ ] Testes: mock webhook inline media → assert arquivo salvo + metadata correto

## Blocked by
- #01-05 (reconciliação base)
- `OpenWaProvider` webhook handler disparando job

## Verification
```bash
# Simular webhook message.received com media.data (base64)
# Verificar: arquivo em storage/app/private/topweb_chat/...
# Verificar: Message.metadata.media_download_failed=false
```