---
doc_id: agent-topwebchat
type: agent-context
status: active
authority: derived
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [module-contract, agent-guidance]
related: [docs/modules/topweb-chat/README.md]
---

# TopwebChat para agentes

Use este arquivo apenas para navegação. Não contém contrato de produto.

## Leitura

1. `docs/README.md`
2. `docs/modules/topweb-chat/README.md`
3. `STATE.md`, `CONTRACTS.md`, `SECURITY.md` e provider aplicável
4. programa/ADRs/Issue da tarefa
5. código e testes

## Pontos de entrada do código

- `packages/Webkul/TopwebChat/src/Routes/`
- `packages/Webkul/TopwebChat/src/Http/Controllers/`
- `packages/Webkul/TopwebChat/src/Services/`
- `packages/Webkul/TopwebChat/src/Providers/`
- `packages/Webkul/TopwebChat/src/Repositories/`
- `packages/Webkul/TopwebChat/src/Resources/views/`
- `tests/Feature/TopwebChat/`

## Stop conditions

Pare se documentação e código divergirem em autorização, dado sensível,
ownership, concorrência ou semântica; registre CURRENT e DECIDED antes de agir.
