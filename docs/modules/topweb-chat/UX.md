---
doc_id: topwebchat-ux
type: module-ux
status: active
authority: canonical
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [workspace-layout, queue-state, composer, accessibility]
related: [design-system/topwebchat/MASTER.md, docs/programs/e14-commercial-workspace/MASTER.md]
---

# TopwebChat UX

O target aceito é a síntese B-operacional: fila priorizável, conversa central,
contexto comercial e próxima ação sem transformar o chat em dashboard. O
renderer canônico de atualização é fragmento HTML do servidor.

Estados de fila sem responsável são cegos antes do claim. Falha, offline,
enviando e resultado desconhecido precisam ser distinguíveis; `unknown` nunca
sugere reenvio automático. Composer permanece fora do scroll da timeline.

Tokens e detalhes de componentes vivem em `design-system/topwebchat/MASTER.md`.
Cobertura responsiva, dark mode e teclado permanece PARTIAL no ledger E14.
