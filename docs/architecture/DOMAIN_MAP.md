---
doc_id: domain-map
type: architecture
status: active
authority: canonical
scope: topwebcrm
last_verified_commit: 205c296
update_triggers: [bounded-context, domain-ownership, integration-boundary]
related: [CONTEXT.md, docs/architecture/ARCHITECTURE.md]
---

# Domain Map

| Contexto | Autoridade principal | Relações |
|---|---|---|
| CRM | Lead, Pessoa, Organização, Activity | fornece contexto comercial ao chat |
| Identidade e acesso | User, Role, concessões | limita todas as superfícies |
| TopwebChat | Conversation, Message, Attendance | projeta mídia/atendimento no CRM |
| Mensageria externa | MessagingProvider/OpenWA | transporta; não decide domínio CRM |
| Operação | deploy, queue, scheduler, storage | mantém execução e evidência |
