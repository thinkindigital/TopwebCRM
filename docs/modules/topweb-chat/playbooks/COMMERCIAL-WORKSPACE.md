---
doc_id: e14-execution-playbook
type: playbook
status: active
authority: operational
scope: E14
last_verified_commit: 205c296
update_triggers: [program-phase, blocker, next, completion]
related: [docs/programs/e14-commercial-workspace/MASTER.md, docs/programs/e14-commercial-workspace/COVERAGE.md]
---

# Commercial Workspace Execution Playbook

## Purpose

Executar slices E14 sem improvisar produto, arquitetura ou segurança.

## Scope

Planejamento, execução, evidência e QA das slices E14. Não define policies ou
contratos do produto.

## Authority

Operacional. Policies, ADRs, contratos e Issues prevalecem.

## Inputs

Issue atual, estado do checkout e evidência disponível.

## Required reading

Siga `docs/README.md`; leia módulo, programa, ADRs e Issue antes do código. Use
Roadmap e Issue guardam escopo; policies e contracts guardam regras.

## Skills

`orchestrator → tdd → secure-e2e → qa-analyst`. Se uma verificação falhar, use
`diagnose`.

## Execution phases

1. Confirmar CURRENT/TARGET/DELTA no Master e capability no Coverage.
2. Definir uma slice vertical e Documentation Impact.
3. TDD: teste falha, mudança mínima, teste passa.
4. Validar autorização, dados sensíveis, erros e browser quando aplicável.
5. Atualizar STATE/CONTRACT/COVERAGE e evidência da Issue no mesmo slice.
6. QA da DAG antes de PR.

## Current status

E14 está `in_progress`; 11 slices foram aceitas, mas nove capabilities são parciais.

## Completed

D-01, E-01–E-03, V-01–V-05, V-07 e V-08 em nível de Issue.

## Blocked

V-06 por E10/D04.

## Next

Implementar E10, executar V-06 e fechar gaps do Coverage.

## Stop conditions

Pare diante de decisão aberta que afete schema/auth/API/dados, regra necessária
somente local, conflito policy×código ou teste de segurança falhando.

## Definition of Done

Contrato da Issue, testes, segurança, browser/HITL aplicável, docs, coverage,
evidência e QA concordam. Issue fechada sozinha não basta.

## Update triggers

Mudança de fase, blocker, NEXT, capability ou gate final do E14.
