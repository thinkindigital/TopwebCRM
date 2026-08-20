## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Job `DownloadMedia` para mídia inbound **omitted** (`media.omitted=true`, `sizeBytes > 1MiB`):
1. Trigger: webhook `message.received` com `hasMedia=true`, `media.omitted=true`
2. Job assíncrono: `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` (stream)
3. Stream response → salva em chunks em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}`
4. Valida `Content-Type` header vs MIME permitidos + `Content-Length` ≤ 50 MiB (`MEDIA_DOWNLOAD_MAX_BYTES`)
5. Atualiza `Message.metadata`:
   ```json
   {
     "mimetype": "video/mp4",
     "filename": "video.mp4",
     "sizeBytes": 15728640,
     "sha256": "...",
     "storage_path": "topweb_chat/conv_123/msg_456.mp4",
     "downloaded_at": "2026-08-20T10:00:00.000Z",
     "source": "omitted_stream"
   }
   ```
6. Timeout: 60s (configurável); retry: 2x com backoff
7. Se falha → log + `media_download_failed=true` + alerta

## Acceptance Criteria
- [ ] Job usa `GET /media` endpoint (stream download)
- [ ] Salva em chunks (memory efficient para arquivos grandes)
- [ ] Valida MIME + tamanho ≤ 50 MiB
- [ ] Metadados completos + `source: omitted_stream`
- [ ] Timeout 60s + retry 2x com backoff exponencial
- [ ] Falha → log + flag + alerta
- [ ] Testes: mock GET /media (stream) → assert arquivo salvo + metadata

## Blocked by
- #11 (inline media infra)
- `OpenWaProvider::downloadMedia()` implementado

## Verification
```bash
# Simular webhook omitted media
# Mock GET /media → stream response
# Verificar: arquivo salvo em chunks + metadata source=omitted_stream
```