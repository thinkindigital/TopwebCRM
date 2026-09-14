---
doc_id: adr-0008
type: adr
status: active
authority: canonical
scope: lead-ownership
last_verified_commit: 205c296
update_triggers: [ownership-contract, decision-superseded]
related: [docs/architecture/adr/0012-lead-ownership-enforcement.md]
---

# ADR 0008: Dono do Lead controla acesso à conversa

- Status: parcialmente superado pelo ADR 0012 e pela policy de autorização
- Data: 2026-08-21

## Contexto

Lead e Conversation possuem atribuições independentes no código atual. Essa duplicidade permite divergência, acesso indevido e comportamento imprevisível durante transferência e futura distribuição automática.

## Decisão

- O dono do Lead é a fonte de verdade para acesso à conversa vinculada.
- Administradores veem todas as conversas.
- Conversas com Lead obedecem ao dono. Sem Lead, vale a transição legada definida pelo ADR 0012 e a exposição cega A3 da policy.
- Transferência do Lead atualiza o acesso às conversas na mesma transação e gera auditoria.
- A regra cobre listagens, URLs diretas, polling, histórico, busca, exportação, anexos e downloads.

## Consequências

- `assigned_user_id` da Conversation não pode criar autoridade divergente do Lead.
- A roleta futura deve atribuir Lead e conversas como uma única operação concorrente.
- A fila compartilhada identificável deixa de existir para usuários comuns; a fila A3 cega pode oferecer claim atômico a agentes elegíveis.
