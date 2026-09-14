STATUS: ARCHIVED
SUPERSEDED BY: docs/DOCUMENTATION-GOVERNANCE.md
DO NOT USE FOR IMPLEMENTATION

# TOPWEBCRM — DOCUMENTATION GOVERNANCE & SELF-SUSTAINING PLAYBOOK

**Status:** proposta de governança documental
**Escopo:** documentação do TopwebCRM, TopwebChat e programas associados
**Branch analisado:** `dev`
**Objetivo:** transformar a documentação em um sistema navegável, não redundante, verificável e autossustentável para humanos e agentes de IA.

---

# 0. Por que este documento existe

O problema atual não é falta de documentação.

O problema é que existem documentos suficientes para que um agente encontre informações corretas, antigas, parciais, locais, globais, operacionais e estratégicas ao mesmo tempo — sem uma forma determinística de saber:

1. qual arquivo deve ser lido primeiro;
2. qual documento é autoridade para cada tipo de decisão;
3. qual informação é descrição do estado atual;
4. qual informação é decisão futura;
5. qual informação é plano de execução;
6. qual informação já foi superada;
7. qual documento precisa ser atualizado quando uma mudança acontece;
8. se uma Epic aparentemente “quase concluída” cobre realmente UI, backend, segurança, QA e operação;
9. se uma decisão existe apenas em um checkout local e não acompanha um novo agente;
10. quando um playbook é instrução de execução e quando é especificação do produto.

Este playbook trata documentação como parte do sistema.

A documentação deixa de ser um conjunto de arquivos Markdown e passa a ser uma **arquitetura documental com fontes de verdade, contratos de atualização, gates de consistência e automação de verificação**.

---

# 1. Resultado esperado

Ao final da reorganização, qualquer agente que entre no repositório deve conseguir responder, em poucos passos:

> O que é este produto?

> Quais regras eu não posso violar?

> Em qual módulo estou trabalhando?

> Qual é o estado implementado hoje?

> Qual é o estado decidido para o futuro?

> Quais decisões ainda estão abertas?

> Qual Epic/Slice governa esta alteração?

> Qual skill devo usar?

> Quais documentos preciso atualizar se eu mudar isso?

> Como provo que a alteração está pronta?

> O que ainda falta no programa, mesmo que várias Issues estejam fechadas?

O sistema documental deve ser construído para que essas respostas não dependam da memória de um agente, do histórico do chat, de um arquivo escondido em um servidor ou de interpretação informal.

---

# 2. Diagnóstico do estado atual

## 2.1 O problema principal é mistura de naturezas documentais

Hoje `docs/` contém, no mesmo nível ou em níveis pouco diferenciados:

- arquitetura;
- mapa de código;
- regras de produto;
- branding;
- catálogo de skills;
- operação;
- contrato de provedor;
- documentação de engine;
- roadmap específico de módulo;
- dados demo;
- documentação de TopwebChat;
- referências locais não versionadas;
- ADRs locais;
- regras de segurança locais.

Esses artefatos não possuem o mesmo ciclo de vida.

Uma regra de produto pode durar anos.

Um runbook de deploy muda quando a infraestrutura muda.

Um roadmap muda quando Issues mudam.

Uma spec muda enquanto uma decisão está sendo formada.

Um ADR deveria ficar praticamente imutável depois de aceito.

Um playbook descreve **como executar**, não **o que o produto é**.

Misturar essas categorias faz o agente interpretar documentos com autoridade errada.

## 2.2 Existem múltiplas fontes de entrada

Atualmente um agente pode começar por `AGENTS.md`, `CONTEXT.md`, `docs/README.md`, `ORCHESTRATOR-ROADMAP.md`, `docs/SKILL_MAP.md`, `docs/topweb-chat/README.md`, a Issue da Epic, uma spec local ou um ADR local.

O problema não é possuir todos esses documentos. O problema é não existir um protocolo forte o suficiente para garantir que todos os caminhos convergem para o mesmo conjunto mínimo antes de o agente começar a decidir ou implementar.

## 2.3 Parte da autoridade está fora do Git

O repositório declara como fontes relevantes `docs/SECURITY_RULES.md`, `docs/adr/`, `docs/agents/`, `docs/krayincrm/` e `docs/reference/`, enquanto essas áreas são deliberadamente ignoradas pelo Git.

Isso pode ser apropriado para material privado, dumps e referências pesadas. Não é apropriado para uma regra sem a qual um agente não consegue tomar uma decisão correta.

### Regra nova

> Nenhuma fonte necessária para decidir, autorizar, implementar ou validar uma mudança pode existir exclusivamente em um checkout local.

Materiais privados podem permanecer locais. A **decisão derivada deles** precisa possuir uma versão sanitizada e versionável.

```text
PRIVADO
private-docs/security/customer-threat-model.md

CANÔNICO VERSIONADO
docs/policies/SECURITY_POLICY.md
```

O primeiro pode conter detalhes sigilosos. O segundo contém as invariantes que todo agente precisa obedecer.

## 2.4 Há documentos que se sobrepõem

### `ARCHITECTURE.md` × `SYSTEM_MAP.md`

Ambos descrevem pontos seguros de extensão, zonas de risco, módulos, autorização e estrutura do fork.

Eles deveriam responder perguntas diferentes:

- **ARCHITECTURE:** por que as fronteiras existem e quais padrões arquiteturais devem ser preservados?
- **SYSTEM_MAP:** onde cada parte existe no checkout atual?

### `topweb-chat/README.md` × `OPENWA.md` × `BAILEYS.md`

O README do módulo também contém engine, configuração, endpoints, deploy, operação, troubleshooting, estado de implementação e roadmap implícito. `OPENWA.md` já documenta o contrato externo e `BAILEYS.md` repete parte do contrato e da operação.

O módulo precisa de um README menor e links explícitos para contratos/runbooks especializados.

### `COMMUNICATION-ROADMAP.md` × `ORCHESTRATOR-ROADMAP.md` × Issues

Existem três representações possíveis de “o que ainda falta”. Isso é perigoso.

Roadmap detalhado pertence ao GitHub Issue. `ORCHESTRATOR-ROADMAP.md` deve ser somente mapa estratégico de Epics. Um roadmap paralelo dentro do módulo inevitavelmente envelhece.

## 2.5 Há divergência semântica documentada

Exemplo de classe de conflito:

```text
Documento global:
conversa sem responsável é restrita a administrador.

Documento do módulo:
fila A3 é visível de forma cega a agentes elegíveis.

Roadmap antigo:
conversa sem atendente aparece para equipe e primeiro agente responde.
```

O sistema documental precisa impedir que três documentos mantenham versões independentes dessa regra.

A solução não é “atualizar todos com o mesmo parágrafo”. A solução é ter **uma única fonte canônica** e os outros apenas apontarem para ela.

## 2.6 O status de Issue está sendo confundido com conclusão do produto

Uma Epic pode possuir várias slices fechadas e ainda não possuir a experiência final completa.

Nova regra:

> `Issue closed` significa apenas que o contrato daquela Issue foi aceito.

Não significa:

> “a feature inteira está pronta”.

A documentação precisa manter cobertura por capacidade.

---

# 3. Arquitetura documental alvo

Reorganizar a documentação pela **natureza da informação**, não pelo momento em que ela foi criada.

```text
/
├── AGENTS.md
├── CONTEXT.md
├── ORCHESTRATOR-ROADMAP.md
│
├── docs/
│   ├── README.md
│   ├── DOCUMENTATION-GOVERNANCE.md
│   ├── SKILL_MAP.md
│   │
│   ├── product/
│   │   ├── PRODUCT_RULES.md
│   │   ├── BRANDING.md
│   │   └── UX_PRINCIPLES.md
│   │
│   ├── policies/
│   │   ├── SECURITY_POLICY.md
│   │   ├── SENSITIVE_DATA_POLICY.md
│   │   ├── AUTHORIZATION_POLICY.md
│   │   └── AUDIT_POLICY.md
│   │
│   ├── architecture/
│   │   ├── ARCHITECTURE.md
│   │   ├── SYSTEM_MAP.md
│   │   ├── DOMAIN_MAP.md
│   │   └── adr/
│   │       ├── README.md
│   │       └── 00xx-*.md
│   │
│   ├── modules/
│   │   └── topweb-chat/
│   │       ├── README.md
│   │       ├── STATE.md
│   │       ├── CONTRACTS.md
│   │       ├── UX.md
│   │       ├── SECURITY.md
│   │       ├── providers/
│   │       │   ├── OPENWA.md
│   │       │   └── BAILEYS.md
│   │       └── playbooks/
│   │           ├── COMMERCIAL-WORKSPACE.md
│   │           └── INCIDENTS.md
│   │
│   ├── operations/
│   │   ├── LOCAL_DEVELOPMENT.md
│   │   ├── DEPLOYMENT.md
│   │   ├── DEMO_DATA.md
│   │   └── RELEASE_CHECKLIST.md
│   │
│   ├── programs/
│   │   └── e14-commercial-workspace/
│   │       ├── README.md
│   │       ├── MASTER.md
│   │       ├── COVERAGE.md
│   │       ├── DECISIONS.md
│   │       └── HANDOFF.md
│   │
│   └── archive/
│       └── ...
│
└── .github/
    └── skills/
```

Essa estrutura não precisa ser aplicada de uma vez. Ela é o target. O importante é primeiro estabelecer as classes documentais.

---

# 4. Classes documentais

Todo Markdown relevante precisa pertencer a exatamente uma classe primária.

## 4.1 ENTRYPOINT

Exemplos: `AGENTS.md`, `docs/README.md`.

Função: dizer o que ler e apontar autoridade. Não carregar especificação inteira.

## 4.2 GLOSSARY

Exemplo: `CONTEXT.md`.

Função: definir termos.

Se uma frase contém regra operacional completa, fluxo, endpoint ou procedimento, provavelmente não pertence ao glossário.

## 4.3 POLICY

Exemplos: segurança, autorização, dados sensíveis, auditoria.

Respondem: **o que nunca pode ser violado?**

Devem ser versionadas quando necessárias para execução. Detalhes sigilosos podem ficar fora do Git, invariantes não.

## 4.4 ARCHITECTURE

Responde: **quais são as fronteiras e por que existem?**

Contém contextos, componentes, dependências, invariantes arquiteturais, padrões e links para ADRs.

## 4.5 SYSTEM MAP

Responde: **onde isto está no código hoje?**

Contém caminhos, packages, controllers, services, repositories, models, jobs, views e tests.

É factual e deve ser validado contra o checkout.

## 4.6 MODULE SPEC

Responde: **como este módulo se comporta?**

O README do módulo deve ser curto e apontar para STATE, CONTRACTS, UX, SECURITY, providers e playbooks.

## 4.7 STATE

Responde: **o que está implementado agora?**

Marcadores obrigatórios:

```text
IMPLEMENTED
DECIDED_NOT_IMPLEMENTED
EXPERIMENTAL
DEPRECATED
REMOVED
```

O agente não pode inferir estado por tempo verbal.

## 4.8 ADR

Responde: **qual decisão duradoura foi tomada e por quê?**

ADR não é roadmap, changelog ou status.

## 4.9 PROGRAM / MASTER SPEC

Responde: **como um programa grande deve chegar do estado A ao estado B?**

Pode conter visão, decisões, slices, dependências, gates e cenários. Não deve substituir políticas globais.

## 4.10 PLAYBOOK

Responde: **como executar uma classe de trabalho?**

Playbook deve possuir estado operacional: CURRENT PHASE, COMPLETED, NEXT, BLOCKED e LAST VERIFIED.

## 4.11 RUNBOOK

Responde: **como operar algo existente?**

Exemplos: deployment, local development, restore, smoke.

## 4.12 REFERENCE

Material auxiliar. Não possui autoridade.

## 4.13 ARCHIVE

Todo arquivo arquivado deve começar com:

```text
STATUS: ARCHIVED
SUPERSEDED BY: <path>
DO NOT USE FOR IMPLEMENTATION
```

---

# 5. Regra de “um fato, uma casa”

## Proibido

```text
PRODUCT_RULES.md:
fila A3 funciona assim...

TopwebChat README:
fila A3 funciona assim...

Commercial Workspace:
fila A3 funciona assim...

Playbook:
fila A3 funciona assim...
```

Mesmo que os quatro estejam inicialmente iguais, irão divergir.

## Correto

A decisão canônica vive em:

```text
docs/modules/topweb-chat/CONTRACTS.md
# Conversation ownership and unassigned queue
```

Os demais apenas referenciam.

O playbook explica **quando validar** o contrato, não replica o contrato inteiro.

---

# 6. Cabeçalho obrigatório de documentos canônicos

Recomendação:

```yaml
---
doc_id: topwebchat-contracts
type: module-contract
status: active
authority: canonical
scope: topweb-chat
owner: E14
last_verified_commit: <sha>
update_triggers:
  - conversation authorization changes
  - queue/claim behavior changes
  - lead ownership contract changes
supersedes: []
related:
  - docs/policies/AUTHORIZATION_POLICY.md
  - ORCHESTRATOR-ROADMAP.md#e14
---
```

Campos mínimos:

- `doc_id`;
- `type`;
- `status`;
- `authority`;
- `scope`;
- `last_verified_commit`;
- `update_triggers`;
- `related`.

---

# 7. `docs/README.md` como único índice

Ele deve conter somente:

1. ordem de leitura;
2. tabela de fontes;
3. regra de autoridade;
4. links por objetivo;
5. link para governança documental.

Exemplo:

```text
Preciso entender linguagem -> CONTEXT
Preciso entender regras invioláveis -> policies/
Preciso entender arquitetura -> architecture/ARCHITECTURE
Preciso achar código -> architecture/SYSTEM_MAP
Preciso entender TopwebChat -> modules/topweb-chat/README
Preciso ver estado implementado -> modules/topweb-chat/STATE
Preciso ver trabalho pendente -> roadmap + Issues
Preciso operar produção -> operations/
Preciso executar E14 -> programs/e14-commercial-workspace/
```

---

# 8. Ordem de leitura obrigatória do agente

## 8.1 Sempre

```text
1. AGENTS.md
2. docs/README.md
3. CONTEXT.md
4. policies aplicáveis
5. ARCHITECTURE.md
6. SYSTEM_MAP.md
```

## 8.2 Depois, por domínio

Se TopwebChat:

```text
7. docs/modules/topweb-chat/README.md
8. docs/modules/topweb-chat/STATE.md
9. docs/modules/topweb-chat/CONTRACTS.md
10. docs/modules/topweb-chat/SECURITY.md
```

## 8.3 Se a tarefa pertence a programa

```text
11. programs/<programa>/README.md
12. programs/<programa>/COVERAGE.md
13. ADRs relacionados
14. Epic + Issue atual
```

## 8.4 Só então

```text
15. código
16. migrations
17. config
18. testes
```

Exceção: investigação de divergência docs × código.

---

# 9. Skill protocol para documentação

Não usar as 86 skills ao mesmo tempo. O `SKILL_MAP` existe para selecionar a habilidade correta.

## 9.1 `/orchestrator`

Controla o programa documental, cria DAG, verifica blockers, delega e exige QA. Não deve reescrever todos os documentos sozinho.

## 9.2 `zoom-out`

Usar no início para sair do detalhe do TopwebChat e mapear documentação como sistema.

Saída: `DOCUMENTATION-INVENTORY`.

## 9.3 `agent-md-refactor`

Usar para reduzir `AGENTS.md` e impedir que vire manual gigante. Não usar para reorganizar todo `docs/`.

## 9.4 `setup-skills`

Usar somente para validar a estrutura mínima de governança. Não recriar artefatos existentes.

## 9.5 `grill-feature-with-docs`

Principal skill para módulos existentes. Deve confrontar código + docs, identificar divergências e separar implemented/decided/planned.

## 9.6 `grill-with-docs`

Usar quando a confusão é de domínio, não de código: owner, assignment, attendance, account/session, quarantine.

## 9.7 `requirements-clarity`

Usar antes de nova spec. Definir outcome, non-goals, surfaces, authorization, negativos e sucesso mensurável.

## 9.8 `writing-clearly-and-concisely`

Obrigatória na consolidação. Remover repetição sem reduzir precisão.

## 9.9 `naming-analyzer`

Usar em nomes documentais ambíguos. Ex.: `COMMUNICATION-ROADMAP.md` não deve sobreviver como roadmap concorrente.

## 9.10 `mermaid-diagrams`, `c4-architecture`, `archify`

Usar quando diagrama substitui texto repetitivo. Não criar diagramas decorativos.

## 9.11 `roadmap`

Depois da documentação canônica estabilizada. Roadmap referencia specs; não contém as specs.

## 9.12 `to-issues`

Somente depois de uma decisão estar canônica. Issue nunca é a única casa de uma regra duradoura.

## 9.13 `triage`

Executar após reorganização documental para detectar Issues cujo estado contradiz docs e cobertura.

## 9.14 `qa-test-planner`

Planejar links, paths, referências, estados, cobertura e invariantes.

## 9.15 `qa-analyst`

Gate final obrigatório. Confronta `docs × código × Issues × ADR × testes × coverage`.

Reprova se existir regra em duas casas, link canônico quebrado, estado inconsistente, `done` sem evidência ou fonte necessária apenas local.

## 9.16 `lesson-learned`

Registrar apenas lições generalizáveis ao fechar reorganização/programa.

## 9.17 `handoff` / `session-handoff`

Obrigatório para programas longos. Deve responder CURRENT, DONE, NEXT, BLOCKED, DECISIONS, FILES TO READ e DO NOT REOPEN.

## 9.18 `commit-work`

Commits documentais semanticamente claros.

## 9.19 `command-creator`

Recomendado depois da reorganização para criar `/docs-audit`.

## 9.20 `skill-creator` / `write-a-skill`

Somente se a composição das skills existentes não cobrir a governança documental recorrente.

---

# 10. Nova definição de “Done”

Nenhum programa grande pode usar apenas checkboxes de Issues para inferir conclusão.

Criar `COVERAGE.md`.

| Capability | Product decision | Backend | UI | Auth/Security | TDD | E2E | Docs | HITL | Status |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---|
| Queue A3 | ✅ | ✅ | ⚠️ | ✅ | ✅ | ⚠️ | ✅ | ⚠️ | PARTIAL |
| Commercial context | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | NOT READY |
| Composer | ✅ | ✅ | ❌ | ✅ | ✅ | ❌ | ✅ | ❌ | NOT READY |
| Next action | ✅ | ✅ | ⚠️ | ✅ | ✅ | ❌ | ✅ | ❌ | PARTIAL |
| Secure search | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | BLOCKED |

Regras:

- `DONE` exige todas as colunas aplicáveis.
- `Issue closed` pode preencher uma ou mais colunas.
- status da capability é calculado pela cobertura, não pela quantidade de Issues fechadas.

---

# 11. Program Status Header

Todo playbook/programa ativo começa com algo equivalente a:

```yaml
program: E14
status: active
phase: implementation
overall_completion: PARTIAL
current_gate: D04
blocked_capabilities:
  - V-06 secure search
next_required:
  - finish D04
  - re-evaluate UX coverage
last_verified_commit: <sha>
```

E deve conter:

```text
DONE
IN PROGRESS
BLOCKED
NOT STARTED
NEXT
```

Atualizar no mesmo slice que muda estado macroscópico.

---

# 12. Update Triggers

Cada documento declara quando precisa ser revisto.

Exemplos:

### `AUTHORIZATION_POLICY.md`

Atualizar quando escopo Bouncer, owner, assignment, search scope ou semântica de admin mudar.

### `topweb-chat/STATE.md`

Atualizar quando model, rota, fluxo, provider, UI ou feature flag mudar.

### `SYSTEM_MAP.md`

Atualizar quando responsabilidade de arquivo/pacote/service/fronteira mudar.

### `DEPLOYMENT.md`

Atualizar quando compose, secret, volume, healthcheck ou release workflow mudar.

---

# 13. Documentation Impact Check

Toda Issue deve possuir:

```text
Documentation impact:
[ ] none
[ ] glossary
[ ] policy
[ ] architecture
[ ] system map
[ ] module state
[ ] module contract
[ ] ADR
[ ] runbook
[ ] program coverage
```

Se marcar `none`, justificar.

---

# 14. Doc Debt é blocker quando afeta decisão

## DOC-P0

- regra de segurança ausente;
- canonical link quebrado;
- duas fontes canônicas contraditórias;
- agente novo não consegue descobrir a regra.

Bloqueia implementação dependente.

## DOC-P1

- estado implementado incorreto;
- Issue marcada done sem cobertura;
- contrato duplicado;
- roadmap incoerente.

Corrigir antes de fechar programa.

## DOC-P2

- naming;
- estrutura;
- texto longo;
- exemplos desatualizados sem impacto decisório.

Pode entrar em batch documental.

---

# 15. Migração proposta dos documentos atuais

## `AGENTS.md`

Manter na raiz. Reduzir para leitura, invariantes, GitHub/skills e DoD mínimo. Remover detalhes de módulo.

## `CONTEXT.md`

Manter na raiz como glossário.

## `docs/README.md`

Manter como único índice. Não guardar política detalhada.

## `docs/SKILL_MAP.md`

Manter. Adicionar seção `Documentation workflows` que aponta para `DOCUMENTATION-GOVERNANCE.md`.

## `docs/ARCHITECTURE.md`

Mover para `docs/architecture/ARCHITECTURE.md`. Remover inventário que pertença ao SYSTEM_MAP.

## `docs/SYSTEM_MAP.md`

Mover para `docs/architecture/SYSTEM_MAP.md`. Manter factual.

## `docs/PRODUCT_RULES.md`

Mover para `docs/product/PRODUCT_RULES.md`. Reduzir detalhes de segurança e contratos TopwebChat a referências.

## `docs/BRANDING.md`

Mover para `docs/product/BRANDING.md`.

## `docs/topweb-chat/README.md`

Reescrever como índice do módulo:

```text
Purpose
Boundaries
Current maturity
Canonical docs
Entry points
Related epics
```

## `docs/topweb-chat/OPENWA.md`

Mover para `docs/modules/topweb-chat/providers/OPENWA.md`.

## `docs/topweb-chat/BAILEYS.md`

Mover para `docs/modules/topweb-chat/providers/BAILEYS.md` e documentar apenas delta da engine.

## `docs/topweb-chat/COMMUNICATION-ROADMAP.md`

Não continuar como roadmap concorrente. Extrair decisões válidas, mover pendências para Issues e arquivar.

## `docs/topweb-chat/DEMO-DATA.md`

Mover para `docs/operations/DEMO_DATA.md`.

## `docs/operations/LOCAL_DEVELOPMENT.md`

Manter como runbook.

## `docs/operations/DEPLOYMENT.md`

Manter como runbook. Pode continuar grande se tiver TOC, passos reproduzíveis e não duplicar contratos do módulo.

## Commercial Workspace Spec

Precisa ser versionada ou possuir versão canônica sanitizada no Git:

```text
docs/programs/e14-commercial-workspace/MASTER.md
```

Roadmap nunca deve apontar para arquivo ausente em clean checkout.

## ADRs

ADRs necessários à execução devem ser versionados. Se contiverem detalhes privados, separar decisão pública/sanitizada de anexos privados.

---

# 16. Fase de recuperação documental

## D0 — Freeze de conclusão

Não fechar Epic ampla por contagem de slices até o Coverage Ledger existir.

## D1 — Inventory

Skills:

```text
/orchestrator
zoom-out
agent-md-refactor
```

Produzir lista de docs, classe, autoridade, duplicações, referências quebradas e dependências local-only.

## D2 — Canonicality audit

Skills:

```text
grill-feature-with-docs
grill-with-docs
requirements-clarity
```

Para cada domínio: current truth, decided truth, planned truth e contradictions.

## D3 — Information architecture

Skills:

```text
writing-clearly-and-concisely
naming-analyzer
mermaid-diagrams
```

Aplicar árvore alvo sem reescrever regra de produto ainda.

## D4 — Consolidation

Mover cada fato para uma casa. Substituir cópia por link. Arquivar superseded.

## D5 — Program coverage reconstruction

Especialmente E02, E03/E05/E06, E08, E10, E13 e E14.

## D6 — Tracker reconciliation

Skills:

```text
roadmap
triage
to-issues
```

Comparar `docs × roadmap × Issues × código`.

## D7 — Automation

Skills:

```text
command-creator
setup-pre-commit
git-guardrails-claude-code
```

Criar validação documental.

## D8 — QA

Skills:

```text
qa-test-planner
qa-analyst
```

Gate final.

---

# 17. Validador documental recomendado

Criar depois da reorganização.

Nome sugerido: `scripts/docs-audit`.

Verificações:

```text
[DOC001] canonical referenced path exists
[DOC002] active doc has metadata
[DOC003] archived doc not referenced as canonical
[DOC004] roadmap source exists
[DOC005] program has COVERAGE
[DOC006] active playbook has CURRENT/NEXT/BLOCKED
[DOC007] ADR link exists
[DOC008] local-only path is not mandatory canonical dependency
[DOC009] no duplicated doc_id
[DOC010] no stale last_verified_commit beyond configured threshold
[DOC011] no parent program marked done while coverage contains BLOCKED/NOT_STARTED
[DOC012] README index resolves all canonical docs
```

Rodar em CI quando arquivos relevantes mudarem.

---

# 18. Completion semantics

Padronizar e não misturar eixos.

## Documento

```text
draft
active
superseded
archived
```

## Decisão

```text
open
decided
superseded
```

## Capability

```text
not_started
partial
blocked
ready_for_hitl
done
```

## Issue

GitHub continua `open` / `closed`.

---

# 19. Regra contra “documentar futuro como presente”

Evitar:

> O sistema usa Conta WhatsApp duradoura.

Se ainda não implementado.

Usar:

```text
IMPLEMENTED:
Instance concentra sessão e conta.

DECIDED:
Conta WhatsApp e Sessão serão separadas.

PLANNED:
Migração da sessão...
```

---

# 20. Regra contra “documentar implementação como decisão”

O fato de um código existir não torna o desenho canônico.

```text
CURRENT:
ConversationAccessService usa assigned_user_id.

DECIDED:
Lead.user_id é fonte de verdade.

DIVERGENCE:
D04 ainda precisa reconciliar.
```

---

# 21. Regras específicas para playbooks

Todo playbook ativo deve ter:

```text
Purpose
Scope
Authority
Inputs
Required reading
Skills
Execution phases
Current status
Completed
Blocked
Next
Stop conditions
Definition of Done
Update triggers
```

Playbook não deve copiar PRODUCT_RULES, SECURITY_POLICY, contratos ou roadmap. Deve referenciá-los.

---

# 22. Regras específicas para specs

Spec contém target e precisa separar:

```text
CURRENT
TARGET
DELTA
DECISIONS
OPEN QUESTIONS
SLICES
```

Sem isso, o agente mistura decidido com implementado.

---

# 23. Regras específicas para README de módulo

Objetivo: entender o módulo em cinco minutos.

```text
# Module
Purpose
Boundaries
Core entities
Primary flows
Current state summary
Canonical docs
Related Epics
Operational links
```

README não é manual.

---

# 24. Como saber o que atualizar após uma mudança

Exemplo: D04 Lead Ownership.

A mudança altera `Lead.user_id` authority, `Conversation.assigned_user_id` projection, search scope e transfer semantics.

Então impacta:

```text
policies/AUTHORIZATION_POLICY.md
modules/topweb-chat/CONTRACTS.md
modules/topweb-chat/STATE.md
architecture/SYSTEM_MAP.md
programs/e14-commercial-workspace/COVERAGE.md
ADR aplicável
Issue evidence
```

Não precisa alterar Branding, Deployment ou OpenWA contract.

Esse raciocínio deve ser declarado pela Issue.

---

# 25. Recuperação específica do E14

O estado atual precisa ser reavaliado antes de assumir que está quase concluído.

Criar:

```text
docs/programs/e14-commercial-workspace/COVERAGE.md
```

Reavaliar D-01, E-01, E-02, E-03 e V-01..V-08.

Para cada uma:

```text
Decision
Backend
Frontend
Responsive
Auth
Sensitive data
TDD
Secure E2E
Browser evidence
HITL
Docs
```

Se uma Issue fechada não entregou uma dimensão aplicável:

- não necessariamente reabrir imediatamente;
- marcar capability como PARTIAL;
- criar follow-up slice se a Issue original realmente cumpriu seu contrato;
- reabrir se o acceptance original exigia aquilo.

Isso evita falsificar o histórico.

---

# 26. E01 não está realmente encerrada enquanto a governança não for reproduzível

Critério novo proposto:

```text
[ ] clean clone encontra todas as fontes necessárias
[ ] docs index não aponta para canonical inexistente
[ ] nenhum contrato importante vive apenas localmente
[ ] cada documento tem classe definida
[ ] duplicações críticas removidas
[ ] roadmap não compete com roadmap de módulo
[ ] active programs possuem coverage
[ ] docs-audit passa
[ ] agent fresh-session consegue localizar decisão em teste
```

Exercício real:

> Sem histórico desta conversa, descubra qual regra governa a fila sem atendente e mostre a fonte.

Se o agente precisar adivinhar ou pesquisar quatro arquivos contraditórios, E01 falha.

---

# 27. Regra de clean-room agent

Um agente novo recebe apenas:

```text
repo
branch
Issue number
```

Ele deve conseguir chegar à fonte correta seguindo links.

Não fornecer histórico humano.

Se falhar, o problema é documentação/governança, não memória do agente.

---

# 28. Handoff documental

Todo programa deve possuir handoff pequeno.

```text
# E14 HANDOFF

CURRENT:
D04 grill complete; V06 blocked.

DONE:
...

NEXT:
Implement D04.

DO NOT REOPEN:
A/B decision -> ADR 0011
A3 -> decided
safe activity envelope -> decided

READ:
1...
2...
3...

BLOCKERS:
...
```

Não virar diário.

---

# 29. O que NÃO fazer

- não criar mais documentos como resposta automática;
- não colocar toda decisão no README;
- não manter roadmap de módulo paralelo;
- não copiar contrato para playbook;
- não usar `.scratch/` como fonte duradoura;
- não fechar programa porque todas as Issues locais fecharam;
- não manter regra canônica somente fora do Git.

---

# 30. Sequência imediata recomendada

## Passo 1 — congelar novas expansões documentais

Nada de criar novo documento sem classe.

## Passo 2 — abrir uma slice documental própria

Nome sugerido:

```text
E01-DOC-REFORM — Canonical Documentation Architecture
```

## Passo 3 — produzir inventário

Todos os docs atuais classificados.

## Passo 4 — corrigir referências impossíveis

Prioridade: `SECURITY_RULES`, ADRs necessários e Commercial Workspace spec.

## Passo 5 — criar Documentation Governance

Este playbook pode virar `docs/DOCUMENTATION-GOVERNANCE.md` após revisão.

## Passo 6 — criar Coverage do E14

Antes de continuar dizendo que E14 está quase pronta.

## Passo 7 — consolidar TopwebChat

Extrair do README: STATE, CONTRACTS, SECURITY e providers.

## Passo 8 — remover roadmap paralelo

Extrair conteúdo válido de `COMMUNICATION-ROADMAP.md` e arquivar.

## Passo 9 — reconciliar Issues

Usar `triage`.

## Passo 10 — automatizar

Criar `docs-audit`.

---

# 31. Definition of Done da reforma documental

```text
[ ] clean clone tem todas as regras necessárias
[ ] docs/README é o único ponto de entrada documental
[ ] AGENTS aponta para docs/README e não replica docs
[ ] CONTEXT é só glossário
[ ] policies são canônicas e versionadas
[ ] ARCHITECTURE e SYSTEM_MAP não duplicam função
[ ] TopwebChat README é índice, não monólito
[ ] provider docs não repetem runbook
[ ] roadmaps paralelos foram removidos/arquivados
[ ] E14 possui Coverage Ledger
[ ] programs ativos possuem Current/Next/Blocked
[ ] todas as canonical references resolvem
[ ] Issues apontam para fontes existentes
[ ] docs-audit passa
[ ] QA documental aprova
[ ] exercício clean-room agent passa
```

---

# 32. Comando inicial para o agente

> Execute o programa de governança documental do TopwebCRM.
>
> Antes de modificar qualquer arquivo:
>
> 1. leia `AGENTS.md`, `docs/README.md`, `CONTEXT.md`, `docs/SKILL_MAP.md`, `ORCHESTRATOR-ROADMAP.md`;
> 2. faça inventário completo de documentação versionada e referências locais obrigatórias;
> 3. classifique cada documento por tipo, autoridade, estado, escopo e update trigger;
> 4. detecte duplicações, contradições, links quebrados e fontes canônicas não disponíveis em clean clone;
> 5. não reescreva produto ou código nesta etapa;
> 6. use `/orchestrator` como controlador;
> 7. delegue análise de módulos existentes a `grill-feature-with-docs`;
> 8. use `agent-md-refactor` somente para arquivos de instrução de agentes;
> 9. use `writing-clearly-and-concisely` e `naming-analyzer` durante consolidação;
> 10. use `roadmap`/`triage` somente depois que a fonte canônica estiver estável;
> 11. crie Coverage Ledger para programas ativos antes de inferir conclusão por Issues;
> 12. submeta o resultado final a `qa-analyst`.
>
> Não crie novas fontes de verdade concorrentes.
>
> Não copie regras entre documentos quando uma referência canônica for suficiente.
>
> Não trate `Issue closed` como `capability done`.
>
> Não aceite fonte necessária que exista somente em checkout local.
>
> Ao final, entregue inventário before/after, árvore documental alvo, migration map arquivo-a-arquivo, contradições resolvidas e pendentes, Coverage Ledger dos programas ativos, links quebrados, validações executadas e próximo passo único recomendado.

---

# 33. Estado recomendado após esta proposta

```text
DOCUMENTATION PROGRAM
STATUS: PROPOSED

NEXT:
1. aprovar arquitetura documental;
2. executar inventory completo no servidor;
3. migrar fontes canônicas locais necessárias;
4. criar Coverage E14;
5. reconciliar E01/E08/E10/E14;
6. somente então retomar a sequência de implementação.

BLOCKER ATUAL:
Não usar “quantidade de Issues E14 fechadas” como estimativa de conclusão do Commercial Workspace até reconstruir a cobertura UI/backend/security/E2E/HITL.
```

---

# 34. Princípio final

A documentação ideal não tenta contar tudo em todos os lugares.

Ela faz algo mais importante:

> **leva qualquer agente, a partir de qualquer tarefa, até a única fonte certa; obriga o agente a atualizar essa fonte quando o comportamento muda; e impede que progresso local seja confundido com conclusão do produto.**

Esse é o critério de sustentabilidade documental do TopwebCRM.
