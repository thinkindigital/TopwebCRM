## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Cenário: webhook `message.received` com `from` = `@lid` (ex: `12345678901234@lid`), `RESOLVE_LID_TO_PHONE=false` no OpenWA, e `GET /contacts/:contactId/phone` retorna `null`.
1. Tentar resolução on-demand: `GET /api/sessions/:sessionId/contacts/:contactId/phone`
2. Se retorna telefone → usar para `contacts/check` + vinculação normal
3. Se retorna `null` → criar `Conversation` com:
   - `status='quarantined'`
   - `quarantine_reason='lid_unresolved'`
   - `needs_lid_resolution=true`
   - `remote_jid` = `@lid` criptografado
4. Fila "Quarentena Identidade" mostra badge "LID não resolvido"
5. Operador pode: (a) aguardar resolução automática futura, (b) vincular manualmente se souber o telefone

## Acceptance Criteria
- [ ] Detecta `@lid` no `from` do webhook
- [ ] Tenta `GET /contacts/:contactId/phone` on-demand antes de quarentenar
- [ ] Se `null` → `quarantine_reason='lid_unresolved'`, `needs_lid_resolution=true`
- [ ] Fila mostra badge "LID não resolvido" + `remote_jid` mascarado
- [ ] Opção de vinculação manual se operador souber o telefone
- [ ] Se `RESOLVE_LID_TO_PHONE=true` no OpenWA → `senderPhone` vem no webhook → evita quarentena
- [ ] Testes: mock webhook com @lid + phone=null → assert quarentena lid_unresolved

## Blocked by
- #06-07 (quarentena base)
- `GET /contacts/:contactId/phone` endpoint no OpenWaProvider

## Verification
```bash
# Mock webhook message.received com from=12345678901234@lid
# Mock GET /contacts/:contactId/phone → null
# Verificar: quarantine_reason=lid_unresolved, needs_lid_resolution=true
```