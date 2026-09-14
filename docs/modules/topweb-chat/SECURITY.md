---
doc_id: topwebchat-security
type: module-security
status: active
authority: canonical
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [message, media, webhook, search, ownership, provider-secret]
related: [docs/policies/SECURITY_POLICY.md, docs/policies/SENSITIVE_DATA_POLICY.md, docs/policies/AUTHORIZATION_POLICY.md]
---

# TopwebChat Security

Aplicam-se todas as policies globais. Em particular:

- conteúdo, mídia, telefone, identificadores externos e metadados são sensíveis;
- acesso contextual à conversa não libera automaticamente dado classificado;
- mídia exige rota autenticada, acesso à conversa/entidade e concessão;
- busca, contadores e autocomplete não revelam identidade fora do escopo;
- webhook valida HMAC antes do JSON e persiste idempotentemente;
- API key, segredo HMAC, assinatura recebida e payload integral não entram em
  navegador ou logs;
- downloads usam storage privado e `no-store`.

Superfícies obrigatórias: inbox, URL direta, fragment/polling, busca, exportação,
Activities, arquivos, logs, cache e integrações.
