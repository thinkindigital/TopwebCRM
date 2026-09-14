---
doc_id: e14-coverage
type: program-coverage
status: active
authority: derived (fonte: Issues #92–103 + CI + evidências)
scope: E14
last_verified_commit: 205c296
update_triggers:
  - slice E14 entregue ou revertida
  - Issue E14 reaberta/fechada
  - evidência nova (screenshot, E2E, smoke)
related:
  - docs/programs/e14-commercial-workspace/MASTER.md
  - ORCHESTRATOR-ROADMAP.md
---

# E14 COVERAGE

`Issue closed` = contrato da Issue aceito. `Capability done` = todas as colunas
aplicáveis. Tabela honesta em 2026-09-12 (lente `qa-test-planner`):

| Capability (slice) | Decisão | Backend | UI | Auth/Sec | TDD | E2E | Docs | HITL | Status |
|---|---|---|---|---|---|---|---|---|---|
| D-01 veredito | ✅ | n/a | ✅ | n/a | ✅ | ✅ | ✅ | ✅ | DONE |
| E-01 tokens | ✅ | n/a | ⚠️ | n/a | ✅ | n/a | ✅ | ✅ | PARTIAL |
| E-02/C1–C4 seams | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | n/a | PARTIAL |
| E-03 renderer | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ | PARTIAL |
| V-01 fila A3 | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | n/a | PARTIAL |
| V-02 contexto | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | n/a | PARTIAL |
| V-03 composer | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | n/a | PARTIAL |
| V-04 próxima ação | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | n/a | PARTIAL |
| V-05 notas | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | n/a | DONE |
| V-06 busca | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | BLOCKED (E10) |
| V-07 claim | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | n/a | PARTIAL |
| V-08 falha | ✅ | ✅ | ✅ | ✅ | ✅ | ⚠️ | ✅ | n/a | PARTIAL |

Legenda: ✅ evidenciado · ⚠️ parcial (ex.: E2E cobre sec-negativos mas não a
matriz viewport/dark/teclado sistemática; UI validada em desktop + mobile
pontual) · ❌ ausente.

## Deep coverage por slice (amostra)

- **V-01:** Decision A3 · Backend contadores/claim · UI fila cega + claim ·
  Auth matriz + sem-oráculo · TDD 4 its · E2E pendente de viewport matrix ·
  Docs README+gaps · sem HITL pendente.
- **V-04:** Decision envelope+policy · Backend NextActionService · UI bloco+dot ·
  Auth owner-escopo · TDD 3 its · E2E ausente (fluxo envelope em navegador real) ·
  Docs spec+README.
- **E-03:** Decision fragmento · Backend endpoint+partial · UI convergida ·
  Auth idêntica · TDD 3 its · E2E fragment-auth na suite sec · Docs ADR pendente
  (registrar E-03 em ADR próprio — follow-up).

## Follow-ups abertos por esta cobertura

1. Matriz sistemática viewport/dark/teclado por slice (V-01–V-05, V-07, V-08).
2. E2E de fluxo para V-02/V-04 (envelope em navegador).
3. ADR da decisão E-03 (renderer fragmento).
4. V-06 quando E10 destravar.
