---
doc_id: documentation-governance
type: policy
status: active
authority: canonical
scope: documentation
last_verified_commit: 205c296
update_triggers: [document-class, authority-order, documentation-gate, audit-rule]
related: [docs/README.md, scripts/docs-audit]
---

# Documentation Governance

> Como a documentação do TopwebCRM funciona e como mantê-la. A ordem de
> autoridade está em `docs/README.md`.

## Classes (um arquivo, uma classe)

ENTRYPOINT (o que ler) · GLOSSARY (termos, sem regras) · POLICY (inviolável,
versionada) · ARCHITECTURE (fronteiras e porquês) · SYSTEM-MAP (onde está no
código, factual) · MODULE-SPEC (como o módulo se comporta) · STATE (o que está
implementado: IMPLEMENTED / DECIDED_NOT_IMPLEMENTED / EXPERIMENTAL / DEPRECATED
/ REMOVED) · CONTRACT (acordos auditáveis) · ADR (decisão duradoura) · PROGRAM
(como ir de A a B) · PLAYBOOK (como executar) · RUNBOOK (como operar) ·
REFERENCE (sem autoridade) · ARCHIVE (com `STATUS: ARCHIVED` + `SUPERSEDED BY`)
· SKILLMAP (este mapa de skills) · WORKING-PAPER (rascunho, arquivar após uso).

## Um fato, uma casa

Decisão canônica vive em UM lugar; os demais referenciam. Playbook explica
quando validar, nunca replica o contrato. Spec separa CURRENT / TARGET / DELTA /
DECISIONS / OPEN QUESTIONS / SLICES. Nunca documentar futuro como presente nem
implementação como decisão.

## Cabeçalho de canônicos

`doc_id`, `type`, `status`, `authority`, `scope`, `last_verified_commit`,
`update_triggers`, `related` (mínimos). Arquivado exige STATUS + SUPERSEDED BY.

## Ordem de leitura

AGENTS → README → CONTEXT → policies → ARCHITECTURE → SYSTEM_MAP → (domínio:
módulo README/STATE/CONTRACTS/SECURITY) → (programa: README/COVERAGE/ADRs/Epic)
→ código, migrations, config, testes.

## Update triggers e impact check

Cada canônico declara quando é revisto. Toda Issue com mudança funcional declara
`Documentation impact:` (none com justificativa, ou glossary/policy/architecture/
map/state/contract/ADR/runbook/coverage). Nenhuma fonte necessária à decisão
vive só em checkout local — detalhe privado fica local, a invariante é versionada.

## Dívida documental

DOC-P0 (segurança ausente, link canônico quebrado, duas fontes contraditórias,
regra indescobrível) bloqueia implementação dependente. DOC-P1 (estado errado,
done sem cobertura, contrato duplicado, roadmap incoerente) antes de fechar
programa. DOC-P2 (naming, estrutura, texto) em batch.

## Done documental

Clean clone encontra as regras; README é o único índice; AGENTS aponta sem
replicar; CONTEXT é só glossário; policies versionadas; ARCHITECTURE × SYSTEM_MAP
separados; README de módulo é índice; providers sem runbook repetido; sem
roadmap paralelo; programas têm coverage; referências resolvem; docs-audit
passa; QA aprova; clean-room agent localiza decisões.

## Validador

`scripts/docs-audit` (DOC001–DOC012: paths, metadados, arquivados, roadmap,
coverage, playbook com CURRENT/NEXT/BLOCKED, ADRs, local-only, doc_id, commits
obsoletos, done com BLOCKED, índice). Roda em CI quando docs mudarem.
