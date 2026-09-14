---
doc_id: topwebchat-readme
type: module-spec
status: active
authority: index
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [module-boundary, canonical-document]
related: [ORCHESTRATOR-ROADMAP.md]
---

# TopwebChat

TopwebChat opera conversas WhatsApp no contexto comercial. O CRM controla
autorização, vínculos com Pessoa/Lead, histórico, atribuição, notas e eventos; o
OpenWA controla sessões e transporte.

## Fronteiras

`UI/controller → serviços TopwebChat → banco/jobs → MessagingProvider → OpenWA`.
Controllers validam, autorizam e coordenam. Regras do gateway ficam em adapters
e normalizadores. O navegador não recebe segredos.

## Entidades

`Instance`, `Conversation`, `Message`, `InternalNote`, `WebhookEvent`,
`Attendance` e `MediaProjection`. Conta WhatsApp duradoura ainda é decisão não
implementada.

## Leia por objetivo

| Objetivo | Fonte |
|---|---|
| Estado do checkout | [STATE](STATE.md) |
| Contratos internos | [CONTRACTS](CONTRACTS.md) |
| UX vigente e alvo | [UX](UX.md) |
| Segurança do módulo | [SECURITY](SECURITY.md) |
| Contrato OpenWA | [providers/OPENWA](providers/OPENWA.md) |
| Delta Baileys | [providers/BAILEYS](providers/BAILEYS.md) |
| Operação e diagnóstico | [Runbook](../../operations/TOPWEBCHAT.md) |
| Programa Commercial Workspace | [E14](../../programs/e14-commercial-workspace/README.md) |

Epics relacionadas: E03, E05, E06, E08, E10, E13 e E14 no roadmap raiz.
