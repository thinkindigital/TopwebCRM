## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Validação rigorosa de MIME types para upload/download mídia:
1. **Lista permitida** (config `topweb_chat.media.allowed_mimes`):
   - `image/*` (jpeg, png, webp, gif) + **stickers** (`image/webp`)
   - `video/*` (mp4, 3gp, mov)
   - `audio/*` (ogg/opus, mp3, m4a, aac) — voice notes: `audio/ogg; codecs=opus` + `ptt=true`
   - `application/pdf`
   - `application/vnd.openxmlformats-officedocument.*` (docx, xlsx, pptx)
   - `application/msword`, `application/vnd.ms-excel`, `application/vnd.ms-powerpoint`
2. **Lista bloqueada** (sempre rejeita):
   - `application/x-executable`, `application/x-msdownload`, `application/x-sh`, `application/x-php`
   - Qualquer `application/*` não explicitamente permitida
3. **Validação dual**:
   - Client-side: `accept` attribute + JS check
   - Server-side: `finfo_file` (magic bytes) + MIME da extensão — **ambos** devem estar na permitida
4. **Stickers**: detectar `image/webp` + tamanho típico < 100KB → flag `is_sticker=true` no metadata

## Acceptance Criteria
- [ ] Config `allowed_mimes` com lista completa acima
- [ ] Server-side: `finfo_file` + extensão validados contra lista
- [ ] Rejeita qualquer `application/*` não listada
- [ ] Stickers detectados automaticamente (`is_sticker=true`)
- [ ] Testes: upload de cada tipo permitido → 200; bloqueado → 400 com mensagem clara
- [ ] Testes: arquivo com extensão .jpg mas conteúdo .php → rejeitado (magic bytes)

## Blocked by
- #13 (media upload outbound)
- Config `topweb_chat.media.allowed_mimes`

## Verification
```bash
# Upload imagem válida → 200
# Upload .exe renomeado .jpg → 400 (magic bytes detecta)
# Upload .php → 400
# Upload sticker webp < 100KB → is_sticker=true
```