## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
UI para fila "Quarentena Identidade" (menu TopwebChat > Quarentena):
1. **Lista** (DataGrid): `Conversation` onde `status='quarantined'`
   - Colunas: `remote_jid` (mascarado), `quarantine_reason`, `candidate_person_ids` (se ambiguous), `created_at`, `messages_count`
   - Filtros: `quarantine_reason`, data, instance
   - Ações: "Ver detalhes", "Resolver"
2. **Detalhe/Resolução** (modal ou página):
   - Mostra mensões inbound (readonly, ocultas até resolução)
   - Se `ambiguous`: lista candidatos (nome, telefone, email, lead) → radio select → "Confirmar vinculação"
   - Se `no_match`: botão "Checar WhatsApp" (executa contacts/check) + campo para criar nova Pessoa/Lead
   - Se `lid_unresolved`: badge "LID não resolvido" + opção "Vincular manualmente" (busca Pessoa/Lead)
3. **Ação "Resolver"**: vincula `person_id`/`lead_id`, `status='active'`, limpa campos quarentena
4. **Permissão**: `topweb_chat.quarantine_resolve` (operadores + admins)

## Acceptance Criteria
- [ ] Menu TopwebChat > Quarentena acessível com permissão
- [ ] DataGrid com paginação, filtros, export
- [ ] Detalhe mostra mensagens inbound (readonly) + candidatos (se ambiguous)
- [ ] Ações: "Checar WhatsApp", "Vincular manualmente", "Confirmar"
- [ ] Resolução → `status='active'`, mensagens tornam-se visíveis nas filas normais
- [ ] Log: `quarantine.resolved` com `conversation_id`, `selected_person_id`, `resolved_by`, `method` (auto|manual)
- [ ] Testes: criar quarentenas dos 3 tipos → resolver cada uma → assert transições

## Blocked by
- #06, #07, #08 (quarentena types implementados)
- Permissão `topweb_chat.quarantine_resolve` na ACL

## Verification
```bash
# Acessar /admin/topweb-chat/quarantine
# Verificar 3 tipos listados
# Resolver cada tipo → verificar transição active + mensagens visíveis
```