---
doc_id: adr-0012
type: adr
status: active
authority: canonical
scope: lead-ownership
last_verified_commit: 205c296
update_triggers: [ownership-enforcement, decision-superseded]
related: [docs/policies/AUTHORIZATION_POLICY.md]
---

# ADR 0012: Dono do Lead como autoridade operacional (D04)

- Status: aceito (grill 2026-09-12)
- Data: 2026-09-12

## Contexto

`Conversation.assigned_user_id` funcionava como segunda fonte de autoridade,
divergindo do dono do Lead e permitindo acesso, busca e contadores fora da
carteira.

## Decisão

Onde há Lead, `lead.user_id` é a única autoridade operacional;
`assigned_user_id` é projeção sincronizada atomicamente na transferência (com
auditoria, sem PII desnecessária); ex-dono perde acesso imediato; sem assignment
divergente. Sem Lead, vale o legado até existir dono. Ingestão externa sem
owner: 422 sem persistência. Elegibilidade de próxima ação por semântica
centralizada, nunca por lista de tipos espalhada.

## Consequências

Matriz 100% por superfície; divergência zero; writers de `user_id` só pela
reconciliação; propriedade de Activity preservada; V-06 desbloqueada após E10.
