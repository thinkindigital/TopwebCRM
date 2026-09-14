---
doc_id: authorization-policy
type: policy
status: active
authority: canonical
scope: topwebcrm
last_verified_commit: 205c296
update_triggers: [bouncer, owner, assignment, search-scope, admin-semantics]
related: [docs/modules/topweb-chat/CONTRACTS.md, docs/architecture/adr/0012-lead-ownership-enforcement.md]
---

# Authorization Policy

Autorização é centralizada em policy, gate, middleware, serviço ou scope seguro.
Não duplicar decisões em Blade, JavaScript, controller e query.

Onde há Lead, `Lead.user_id` é a autoridade operacional. A atribuição da conversa
é projeção e deve acompanhar transferência atomicamente. O ex-dono perde acesso
imediato. Administradores têm escopo global, mas não recebem automaticamente
dados sensíveis integrais.

Conversas sem Lead mantêm o comportamento legado documentado no `STATE.md` até
uma decisão específica substituir essa transição. A fila A3 revela a agentes
elegíveis apenas informação não identificável antes do claim atômico. Este
parágrafo explicita a precedência do ADR 0012 sobre o ADR 0008 nesse caso.

Ingestão externa de Lead sem dono retorna 422 sem persistir. Busca e descoberta
obedecem à mesma carteira e não revelam existência fora do escopo.
