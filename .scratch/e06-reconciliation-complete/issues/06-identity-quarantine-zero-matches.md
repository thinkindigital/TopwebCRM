## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Cenário: webhook `message.received` chega, `contacts/check` retorna `exists=false` OU número não encontrado no CRM.
1. Criar `Conversation` com:
   - `person_id=NULL`, `lead_id=NULL`
   - `status='quarantined'`
   - `quarantine_reason='no_match'`
   - `remote_jid` criptografado, `remote_jid_key` (hash)
2. Mensagens inbound persistem na `Conversation` mas **ocultas** do operador comum
3. Botão "Enviar WhatsApp" no Lead/Pessoa → **indisponível** (disabled + tooltip "Número não verificado no WhatsApp")
4. Botão "Checar WhatsApp" → **habilitado** (executa `contacts/check` on-demand)
5. Se `contacts/check` posterior retorna `exists=true` + `whatsappId` compatível:
   - Habilita "Enviar WhatsApp"
   - Se Lead/Pessoa encontrado → vincula `Conversation.person_id`/`lead_id`, `status='active'`, `quarantine_reason=NULL`

## Acceptance Criteria
- [ ] `Conversation` criada com `status='quarantined'`, `quarantine_reason='no_match'`
- [ ] Mensagens inbound persistem mas não aparecem na UI comum (apenas fila "Quarentena")
- [ ] Botão "Enviar WhatsApp" disabled + tooltip explicativo
- [ ] Botão "Checar WhatsApp" visível e funcional
- [ ] Re-check bem-sucedido → transição `quarantined` → `active` + vinculação automática se match
- [ ] Permissão `topweb_chat.quarantine_resolve` para acessar fila quarentena
- [ ] Testes: mock contacts/check false/true → assert UI states + Conversation transitions

## Blocked by
- #01 (instance status ready)
- `contacts/check` endpoint implementado no OpenWaProvider

## Verification
```bash
# Simular webhook message.received com número inexistente
# Verificar: Conversation.status=quarantined, quarantine_reason=no_match
# UI: botão "Enviar WhatsApp" disabled, "Checar WhatsApp" enabled
# Clicar "Checar WhatsApp" → mock contacts/check true → verificar transição
```