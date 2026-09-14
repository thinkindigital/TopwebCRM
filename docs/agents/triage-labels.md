---
doc_id: agent-triage-labels
type: agent-context
status: active
authority: canonical
scope: tracker
last_verified_commit: 205c296
update_triggers: [triage-label, triage-state]
related: [docs/agents/issue-tracker.md]
---

# Labels de triage

| Label | Uso |
|---|---|
| `needs-triage` | Requer avaliação inicial |
| `needs-info` | Bloqueada por informação ausente |
| `ready-for-agent` | Contrato e aceite completos |
| `ready-for-human` | Requer decisão ou execução humana |
| `wontfix` | Não será executada |

Os labels devem existir no GitHub antes de serem usados. O estado da Issue, e não um arquivo local, é a fonte oficial.
