## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Botão "Checar WhatsApp" no Lead/Pessoa (quando `Conversation` em quarentena `no_match` ou sob demanda):
1. **Localização**: View Lead/Pessoa → actions toolbar (eventos `admin.leads.view.actions.after`, `admin.contact.persons.view.actions.after`)
2. **Comportamento**:
   - Disponível se: `Conversation` existe com `quarantine_reason='no_match'` OU não existe Conversation para o telefone
   - Ao clicar: executa `GET /api/sessions/:sessionId/contacts/check/:number` no OpenWA (via `OpenWaProvider::checkContact()`)
   - Loading state durante requisição
   - Resultado:
     - `exists=true` + `whatsappId` compatível → toast success "WhatsApp verificado" → habilita "Enviar WhatsApp" → se Lead/Pessoa match → vincula Conversation automaticamente
     - `exists=false` → toast warning "Número não possui WhatsApp" → mantém quarentena
     - Erro → toast error + log
3. **Permissão**: `topweb_chat.access` (operadores autorizados)

## Acceptance Criteria
- [ ] Botão aparece no Lead/Pessoa quando aplicável
- [ ] Executa `contacts/check` via OpenWaProvider
- [ ] Feedback visual: loading, success, warning, error
- [ ] Sucesso → habilita "Enviar WhatsApp" + vinculação automática se match
- [ ] Falha → mantém quarentena + mensagem clara
- [ ] Log: `whatsapp.check` com `lead_id`/`person_id`, `number`, `result`
- [ ] Testes: mock contacts/check true/false/error → assert UI states + Conversation transitions

## Blocked by
- #06 (zero matches + botão Checar WhatsApp)
- `OpenWaProvider::checkContact()` implementado

## Verification
```bash
# Acessar Lead com telefone não verificado
# Clicar "Checar WhatsApp" → mock true → verificar botão Enviar habilitado
# Mock false → verificar mantém quarentena
```