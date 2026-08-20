## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Rota autenticada para download de mídia pelo operador:
```
GET /api/topweb-chat/media/{messageId}?token={media_token}
```
1. **Middleware** `MediaTokenValidator`:
   - Decodifica JWT `media_token` → `message_id`, `conversation_id`, `operator_id`, `exp`
   - Valida assinatura + expiração + `message_id` match
2. **Controller** `MediaController@download`:
   - `ConversationAccessService::canView(conversation, user)` → autorização
   - Lê `Message.metadata.storage_path` → `storage/app/private/{path}`
   - `response()->file($path, $headers)` com:
     - `Content-Disposition: attachment; filename="{metadata.filename}"`
     - `Content-Type: {metadata.mimetype}` (conservador: `application/octet-stream` se não inerte)
     - `X-Content-Type-Options: nosniff`
3. **Logs**: `media.downloaded` com `message_id`, `operator_id`, `ip`

## Acceptance Criteria
- [ ] Rota `GET /api/topweb-chat/media/{messageId}` com token query param
- [ ] Middleware valida JWT (assinatura, expiração, message_id)
- [ ] `ConversationAccessService` autoriza acesso à conversa
- [ ] Response: `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`
- [ ] MIME type conservador (aplica `finfo_file` se necessário)
- [ ] Log `media.downloaded` estruturado
- [ ] Testes: token válido/inválido/expirado → assert 200/401/403/404

## Blocked by
- #11-13 (media upload/download inbound + outbound)
- `MediaToken` JWT implementation

## Verification
```bash
# Gerar media_token para mensagem com mídia
# GET /api/topweb-chat/media/{id}?token=... → 200 + arquivo
# Token inválido → 401; token expirado → 401; sem permissão → 403
```