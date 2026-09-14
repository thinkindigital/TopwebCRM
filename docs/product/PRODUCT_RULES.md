---
doc_id: product-rules
type: product
status: active
authority: canonical
scope: topwebcrm
last_verified_commit: 205c296
update_triggers: [product-principle, target-user, strategic-priority]
related: [docs/policies/SECURITY_POLICY.md, docs/modules/topweb-chat/README.md]
---

# Product Rules

TopwebCRM é um fork do Krayin para operação interna, governança de dados e
comunicação integrada. Não busca ser CRM genérico.

## Princípios

1. Segurança antes de conveniência.
2. Fluxo direto, sem exigir interpretação oculta do operador.
3. Comportamento previsível e auditável.
4. Evolução incremental, sem reescrita ampla.
5. O mesmo dado obedece à mesma regra em tela, API, busca, exportação e integração.
6. Histórico responde o que aconteceu, quando, com quem, por quem e com qual impacto.

## Prioridade

Segurança e acesso → estabilidade do fluxo central → visibilidade correta →
histórico operacional → integrações → refinamentos de UX.

Dados sensíveis obedecem às [policies](../policies/). O produto deve permanecer
operável com valores mascarados, sem permitir inferência do valor integral.

O WhatsApp deve parecer parte do ciclo comercial: conversa, Pessoa, Lead,
Activity e arquivos formam um fluxo único. Os comportamentos canônicos ficam em
`docs/modules/topweb-chat/`; não são duplicados aqui.

## Não objetivos atuais

Reescrita da interface, microservicização, refatoração estética massiva,
cosmética sem impacto operacional e múltiplos canais antes de estabilizar o
WhatsApp.

## Pronto

Uma melhoria resolve o problema real, preserva policies, cobre todas as
superfícies aplicáveis, não exige conhecimento oculto, possui evidência e
atualiza a fonte canônica.
