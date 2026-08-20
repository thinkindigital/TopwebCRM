## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Cenário: webhook `message.received` chega, `remote_jid_key` (hash) encontra 2+ `Person` no CRM com mesmo telefone/JID.
1. Criar `Conversation` com:
   - `person_id=NULL`, `lead_id=NULL`
   - `status='quarantined'`
   - `quarantine_reason='ambiguous'`
   - `candidate_person_ids` (JSON array com IDs das Pessoas candidatas)
2. Mensagens inbound persistem mas **ocultas** do operador comum
3. Fila "Quarentena Identidade" (apenas `topweb_chat.quarantine_resolve`):
   - Lista conversas `quarantine_reason='ambiguous'`
   - Mostra candidatos: nome, telefone, email, lead associado
   - Operador seleciona **um** Lead/Pessoa principal → vincula
4. Ao resolver: `person_id`/`lead_id` preenchido, `status='active'`, `quarantine_reason=NULL`, `candidate_person_ids=NULL`

## Acceptance Criteria
- [ ] `Conversation` com `quarantine_reason='ambiguous'`, `candidate_person_ids` JSON array
- [ ] Fila "Quarentena Identidade" acessível apenas com permissão
- [ ] UI mostra candidatos com info relevante (nome, telefone, email, lead)
- [ ] Seleção de um candidato → vinculação + transição para `active`
- [ ] Mensagens tornam-se visíveis após resolução
- [ ] Log: `quarantine.resolved` com `conversation_id`, `selected_person_id`, `resolved_by`
- [ ] Testes: criar 2 Pessoas com mesmo telefone → webhook inbound → assert quarentena + resolução

## Blocked by
- #06 (zero matches infra)
- Permissão `topweb_chat.quarantine_resolve` na ACL

## Verification
```bash
# Criar 2 Pessoas com mesmo telefone
# Simular webhook inbound → verificar quarentena ambiguous
# Acessar fila Quarentena → selecionar Pessoa → verificar transição active
```