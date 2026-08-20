## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Upload de mídia outbound pelo operador:
1. **Frontend** (Vue component no composer):
   - Drag & drop / file input → valida MIME/tamanho client-side
   - Preview (imagem/video/áudio/documento)
   - Envio para `POST /api/topweb-chat/media/upload`
2. **Backend** `MediaController@upload`:
   - Recebe `multipart/form-data` → valida MIME/tamanho (≤ 50 MiB)
   - Salva temporário: `storage/app/private/temp/{operator_id}/{conversation_id}/{uuid}.{ext}`
   - Retorna `media_token` (JWT assinado, expiração 1h) + `expires_at`
3. **Envio** (`MessageController@send`):
   - Se `media_token` presente → `OpenWaProvider::sendMedia()` usa:
     - `base64` (se arquivo ≤ 10 MiB, lê temp + encode)
     - `url` (rota assinada temporária MinIO/S3 futuro: `GET /api/topweb-chat/media/temp/{token}`)
   - Limpa arquivo temp após envio bem-sucedido (job `CleanupTempMedia`)

## Acceptance Criteria
- [ ] Frontend: drag-drop + preview + validação client-side
- [ ] Backend: `POST /media/upload` → retorna `media_token` (JWT, 1h)
- [ ] Arquivo salvo em `storage/app/private/temp/{operator_id}/{conversation_id}/`
- [ ] Envio usa `base64` (≤ 10MiB) OU `url` assinada (futuro MinIO)
- [ ] Cleanup job remove temp após envio (sucesso) ou expiração token (1h)
- [ ] Validação MIME/tamanho both client + server
- [ ] Testes: upload → token → envio → cleanup → assert fluxo completo

## Blocked by
- #11-12 (media download inbound)
- `OpenWaProvider::sendMedia()` com suporte a `base64` e `url`

## Verification
```bash
# Frontend: anexar imagem → upload → token retornado
# Enviar mensagem → verificar OpenWA recebe base64/url
# Verificar: temp file removido após envio
```