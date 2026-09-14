---
doc_id: security-policy
type: policy
status: active
authority: canonical
scope: topwebcrm
last_verified_commit: 205c296
update_triggers: [authentication, authorization, sensitive-data, integration, logging]
related: [docs/policies/AUTHORIZATION_POLICY.md, docs/policies/SENSITIVE_DATA_POLICY.md, docs/policies/AUDIT_POLICY.md]
---

# Security Policy

TopwebCRM aplica menor privilégio, negação por padrão e exposição mínima.
Proteção apenas na interface nunca é suficiente. A mesma decisão deve valer em
UI, URL direta, controller/service, API/Resource, busca, autocomplete, filtro,
exportação, relatório, arquivo, log, notificação, cache, webhook e integração.

Segredos permanecem criptografados ou em configuração segura. O navegador,
logs, Issues e respostas de erro nunca recebem API keys, tokens, segredos HMAC
ou payloads pessoais integrais. Integrações validam origem, modelam timeout,
retry, idempotência e reconciliação.

Mudança que toca Pessoa, Organização, Lead, Mensagem, Activity ou arquivo só
fica pronta após validar todas as superfícies aplicáveis.
