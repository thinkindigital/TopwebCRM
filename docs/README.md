---
doc_id: documentation-index
type: entrypoint
status: active
authority: canonical-index
scope: topwebcrm
last_verified_commit: 205c296
update_triggers: [canonical-document, reading-order, authority]
related: [docs/DOCUMENTATION-GOVERNANCE.md]
---

# Documentação do TopwebCRM

Este é o único índice documental. Um comportamento é implementado somente
quando código e evidência reproduzível confirmam; decisão futura deve ser
marcada como tal.

## Ordem de leitura

1. `AGENTS.md`
2. `docs/README.md`
3. `CONTEXT.md`
4. policies aplicáveis
5. `docs/architecture/ARCHITECTURE.md`
6. `docs/architecture/SYSTEM_MAP.md`
7. README/STATE/CONTRACTS/SECURITY do módulo
8. README/COVERAGE do programa, ADRs, Epic e Issue
9. código, migrations, configuração e testes

## Fontes por objetivo

| Preciso entender | Fonte |
|---|---|
| Linguagem | `CONTEXT.md` |
| Governança dos documentos | `docs/DOCUMENTATION-GOVERNANCE.md` |
| Produto e UX global | `docs/product/` |
| Segurança, autorização e auditoria | `docs/policies/` |
| Fronteiras e decisões | `docs/architecture/ARCHITECTURE.md` e `docs/architecture/adr/` |
| Localização no checkout | `docs/architecture/SYSTEM_MAP.md` |
| TopwebChat | `docs/modules/topweb-chat/README.md` |
| Operação | `docs/operations/` |
| Commercial Workspace | `docs/programs/e14-commercial-workspace/README.md` |
| Trabalho pendente | `ORCHESTRATOR-ROADMAP.md` e GitHub Issues |
| Skills | `docs/SKILL_MAP.md` |

## Autoridade

Em conflito: código/migrations/config/testes para CURRENT; policies para
invariantes; ADRs vigentes para decisões; contexto e regras de produto;
arquitetura e contratos de módulo; runbooks; programas; roadmap/Issues;
referências e arquivo. Divergência entre CURRENT e DECIDED deve ficar explícita,
nunca resolvida por silêncio.

Nenhuma fonte necessária para decidir, autorizar, implementar ou validar pode
existir apenas no checkout local. Material privado pode ficar local; sua
invariante sanitizada deve ser versionada.
