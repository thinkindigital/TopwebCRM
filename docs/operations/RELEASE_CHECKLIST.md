---
doc_id: release-checklist
type: runbook
status: active
authority: operational
scope: release
last_verified_commit: 205c296
update_triggers: [ci, deploy-flow, smoke-contract]
related: [docs/operations/DEPLOYMENT.md]
---

# Release Checklist

- CI e `scripts/docs-audit --strict` passam.
- Migrations, rollback e backup aplicáveis foram revisados.
- Policies e Documentation Impact estão cobertos.
- QA da DAG registrou evidência.
- Imagem imutável por SHA foi publicada e validada.
- Health, queue, scheduler e smoke do fluxo alterado passam.
- Rollback possui imagem e dados compatíveis.
