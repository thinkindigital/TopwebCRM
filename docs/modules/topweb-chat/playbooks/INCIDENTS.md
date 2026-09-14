---
doc_id: topwebchat-incidents
type: playbook
status: active
authority: operational
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [incident-pattern, provider-failure, recovery-procedure]
related: [docs/operations/TOPWEBCHAT.md]
---

# TopwebChat Incidents

## Purpose

Orientar diagnóstico sem transformar sintomas em regra de produto.

## Scope

Incidentes do módulo.

## Authority

Operacional, subordinada a policies e contratos.

## Inputs

Alerta sanitizado e logs sem PII.

## Required reading

Runbook TopwebChat, STATE e provider aplicável.

## Skills

`diagnose`.

## Execution phases

Use `diagnose`: reproduzir → minimizar → hipótese → instrumentar → corrigir → regressão.

## Current status

Diagnóstico operacional vive no runbook TopwebChat.

## Completed

Health, DNS, webhook, queue, scheduler e reconciliação têm ordem de checagem.

## Blocked

Nenhum incidente ativo é governado por este playbook.

## Next

Registrar apenas padrões reproduzidos que exigirem procedimento próprio.

Pare diante de segredo em output, risco de duplicar mensagem `unknown`, perda de
storage privado ou operação destrutiva de sessão. Preserve evidência sanitizada
e use `diagnose` antes de alterar comportamento.

## Stop conditions

Pare diante de segredo, operação destrutiva, dados insuficientes ou risco de
duplicar mensagem.

## Definition of Done

Causa reproduzida, correção testada, regressão executada, runbook atualizado e
evidência sanitizada.

## Update triggers

Novo padrão de incidente, procedimento de recuperação ou mudança de provider.
