# SKILL_MAP — Mapa de skills

> Só skills disponíveis + como se relacionam com o desenvolvimento do TopwebCRM.
> Decisões, auditorias e problemas vivem nos lugares próprios (§4).
> Ordem de autoridade: `docs/README.md`.

## Como escolher (regra do arquivo)

1. Leia a coluna **O que faz** — ela descreve o funcionamento, não o nome.
2. Confirme na coluna **Quando escolher aqui** — gatilhos concretos neste projeto.
3. Cheque **Não confundir com** — o near-miss mais provável e quem vence.
4. Em dúvida entre governança × execução: governança (`orchestrator`, `roadmap`,
   `to-issues`) sempre vence; execução só entra via delegação.
5. Nomes parecidos não decidem nada — `prototype` ≠ `scaffold-mvp`,
   `grill-me` ≠ `grill-with-docs` ≠ `grill-feature-with-docs`,
   `design-system` ≠ `design-system-starter` ≠ `ui-ux-pro-max`.

## 1. Fontes (estado verificado em 2026-09-11)

| Fonte | Estado |
|---|---|
| `alltomatos/skills` (`main`, `37ae283`) | Conteúdo sincronizado; diretório local segue `orchestrator/`. Sync por cópia aditiva, escopo global. |
| `ui-ux-pro-max-skill` (npm `2.15.0`) | `/root/.opencode/skills/ui-ux-pro-max/`. Subordinada ao projeto (master-spec §20). |
| `softaworks/agent-toolkit` (43 skills) | `/root/.agents/skills/` + symlinks. Fusão: governança alltomatos comanda; softaworks injeta capacidade quando o `/orchestrator` delegar. |
| `tt-a1i/archify` | Skill de **diagramas** (não gerencia versões). `/root/.agents/skills/archify` + symlink. |
| `.github/skills/` | `crm-package-development`, `pest-testing` — canônicas neste repo. |

## 2. Catálogo

### 2.1 Engenharia (`/root/.opencode/skills/engineering/`)

| Skill | O que faz | Quando escolher aqui | Não confundir com |
|---|---|---|---|
| `orchestrator` | Gerente do ciclo fechado: verifica framework/GitHub, audita, monta DAG em Issues, delega às especializadas, exige QA antes de PR. Não executa trabalho pesado direto. | Qualquer programa ou etapa multi-skill; ponto de entrada padrão. | `grill-*` (mentoria pontual, sem DAG); `triage` (só move Issues). |
| `setup-skills` | Provisiona artefatos de governança (`AGENTS.md`, `CONTEXT.md`, `docs/agents/`, `docs/adr/`). | Só se faltar artefato (hoje presentes). | `agent-md-refactor` (reforma docs inchados, não cria governança). |
| `roadmap` | Mantém `ORCHESTRATOR-ROADMAP.md`: Epics `E##` estáveis + Issues linkadas, sem renumerar nem inventar. | Reconciliar E08/E13 e decidir a E14, antes de fatiar. | `to-issues` (fatia o decidido; não decide Epics). |
| `grill-feature-with-docs` | Mentoria de módulo **existente**: lê código + docs, confronta divergências, entrevista 1 pergunta por vez, gera/atualiza docs por arquivo. Nunca implementa. | Entender ou preparar um módulo com código (TopwebChat feito em 2026-09-11). | `grill-with-docs` (domínio/linguagem, sem código-alvo); `grill-me` (qualquer plano, sem docs). |
| `grill-with-docs` | Mentoria de linguagem de domínio e ADRs antes de implementar. | Termo novo de domínio fora do TopwebChat. | `grill-feature-with-docs` (exige módulo com código). |
| `to-issues` | Transforma Epic aprovada em Issues de slice vertical (aceite + `Blocked by` + HITL/AFK) e publica em ordem de dependência. Para antes de publicar sem GitHub. | Fatiar a E14 após roadmap estável. Nunca publicar E14–E19 do vision literalmente. | `triage` (classifica Issues existentes; não cria). |
| `to-prd` | Gera PRD do contexto atual. | Não usado (spec mestra já existe). | `grill-feature-with-docs` (parte de realidade, não gera PRD). |
| `triage` | Move Issues na máquina `needs-triage` → `needs-info`/`ready-for-agent`/`ready-for-human`/`wontfix`, com disclaimer de IA e briefs. | Higiene do tracker antes de cada DAG. | `to-issues` (cria; triage classifica). |
| `tdd` | Red-green-refactor por comportamento público, com mocks e refatoração guiada. | Cada slice (tracer bullets TB01–TB08, §29). | `secure-e2e` (E2E + negativos de segurança, não unitário). |
| `diagnose` | Loop reproduce→minimise→hypothesise→instrument→fix→regression-test. | Bug/regressão difícil ou falha de verificação. | `tdd` (constrói; diagnose conserta o quebrado). |
| `secure-e2e` | E2E funcional + negativos de segurança (IDOR, auth, XSS, sessões) via Playwright. | Toda slice com superfície sensível (SEC-UX-01…12). | `qa-analyst` (julga tudo; secure-e2e executa os testes). |
| `qa-analyst` | Gate final: confronta requisito×Issue×código×testes×erros×escopo; reprova reabre Issue. | Antes de qualquer PR à `main`, sem exceção. | `qa-test-planner` (planeja testes; não dá veredito). |
| `query-docs` | Contrato atual de libs externas (Context7; fallback: tipagem local). Evita alucinar API. | Dúvida de API Laravel/Krayin, Playwright, OpenWA/Baileys. | Leitura de docs internas (sempre primeiro; query-docs é só externo). |
| `improve-codebase-architecture` | Acha atrito (módulos rasos, seams) via deletion test; relatório HTML fora do repo; aprofunda o escolhido. | Seams do `show.blade.php` antes do redesign (§23). | Refactor ad-hoc (sem relatório, sem teste de deleção). |
| `prototype` | Protótipo **descartável** (terminal ou variações de UI numa rota); saída persistente é só a decisão. | Protótipo A/B (`?variant=A\|B`, §24), depois apagar. | `scaffold-mvp` (repo novo; prototype nunca vira produto). |
| `scaffold-mvp` | Bootstrap de repositório vazio. | Não aplicável (repo existente). | `prototype` (pergunta visual; scaffold funda projeto). |
| `zoom-out` | Volta ao contexto amplo quando perdido no detalhe. | Sob demanda. | `grill-me` (grill interroga plano; zoom-out só recontextualiza). |
| `mcp-builder` | Constrói servidores MCP (Python/Node) com avaliação. | Só surgindo integração via MCP. | `plugin-forge` (plugins Claude Code, não servidores MCP). |
| `devsetup` | Provisiona máquina **Windows** via winget (sem tweaks). | Só formatou/VM nova Windows. | Qualquer instalação neste host Linux (proibido por `AGENTS.md`). |
| `expo-expert` | Conhecimento Expo/React Native com docs vivas. | Não usado (stack Laravel/Blade). | `query-docs` (libs gerais; expo-expert é nicho mobile). |
| `superkuma-monitoring` | Deploy/operação SuperKuma + MCP de descoberta. | Fora do programa. | `datadog-cli` (observabilidade de app, não infra SuperKuma). |

### 2.2 Design

| Skill | O que faz | Quando escolher aqui | Não confundir com |
|---|---|---|---|
| `ui-ux-pro-max` | Inteligência de design com busca local (`scripts/search.py`): 79 estilos, 192 paletas/regras, 74 fontes, 119 guidelines, 22 stacks; gera design system. | Design system + audits do TopwebChat após o grill (`--stack laravel`, detalhe `html-tailwind`). | `design-system-starter` (checklist genérico, sem motor de busca); `design` (identidade premium, fora das restrições Krayin). |
| `design-system` | Tokens em 3 camadas, specs de componentes, slides estratégicos. | Apoio ao MASTER E14-S01. | `design-system-starter` (starter didático; `design-system` é arquitetura de tokens). |
| `design-system-starter` | Cria e evolui design systems (tokens, acessibilidade, templates). | Alternativa didática ao MASTER. | `ui-ux-pro-max` (tem dados pesquisáveis; starter não). |
| `ui-styling` | UIs com shadcn/Tailwind, dark mode, acessibilidade. | Só referência dentro do admin Krayin. | `ui-ux-pro-max` (decide o sistema; ui-styling implementa estilo). |
| `design` | Identidade premium (logos, CIP, mockups, ícones). | Fora do programa (TopwebChat não vira marca alienígena). | `brand` (voz/guidelines; `design` gera peças). |
| `brand` | Voz, identidade, messaging, compliance de marca. | Fora do programa. | `design` (peças; `brand` governa). |
| `banner-design` / `slides` | Banners e apresentações HTML. | `slides` pode servir ao veredito do protótipo; banners fora. | `marp-slide` (slides Markdown simples; `slides` é estratégico com Chart.js). |

### 2.3 Produtividade, misc, pessoais e do repo

| Skill | O que faz | Quando escolher aqui | Não confundir com |
|---|---|---|---|
| `grill-me` | Interroga um plano até entendimento compartilhado. | Decisão travada fora de módulo. | `grill-with-docs` (amarra em glossário/ADR; grill-me não). |
| `handoff` / `session-handoff` | Compacta contexto entre agentes/sessões. | Toda troca de agente em slice longa. | `lesson-learned` (extrai lição; handoff transfere estado). |
| `skill-creator` / `write-a-skill` / `skill-judge` | Cria, avalia (evals/viewer) e julga skills. | Gargalo sem skill existente. | Usar skill existente (criar é último recurso). |
| `caveman` / `humanizer` | Modo conciso / remove marcas de IA do texto. | Respostas densas / textos ao usuário. | `writing-clearly-and-concisely` (clareza profissional, não compressão). |
| `git-guardrails-claude-code`, `setup-pre-commit`, `commit-work` | Trava git destrutivo; hooks; commits convencionais. | `commit-work` nos commits por slice. | `triage` (Issues; estes são git local). |
| `migrate-to-shoehorn`, `scaffold-exercises` | Shoehorn em testes; exercícios didáticos. | Fora (§49 usa exercícios manuais). | `tdd` (constrói com testes; estes são utilidades). |
| `command-creator`, `plugin-forge` | Cria slash commands / plugins. | Só criando comando (ex.: smoke). | `write-a-skill` (skills; estes são comandos/plugins). |
| `codex`, `gemini`, `perplexity` | Codex/GPT-5.2, review gigante, pesquisa web. | Pontuais, confrontados com o código. | `query-docs` (contrato de lib; estes são oráculos). |
| `edit-article`, `obsidian-vault` | Edição de artigos; notas pessoais. | Fora do programa. | — |
| `crm-package-development` | Novo pacote Krayin sem tocar o core. | Só criando módulo novo. | `improve-codebase-architecture` (melhora existente; este funda). |
| `pest-testing` | Testes Pest 3 + Laravel 12. | Escrever/depurar testes. | `tdd` (método; pest-testing é a ferramenta). |

### 2.4 Softaworks + archify (quando o `/orchestrator` delegar)

| Skill | O que faz | Quando escolher aqui | Não confundir com |
|---|---|---|---|
| `requirements-clarity` | Clareia requisitos antes de codar. | Insumo de slice (Definition of Ready §45). | `grill-feature-with-docs` (lê código real; este clareia pedido). |
| `gepetto` | Planejamento detalhado de implementação. | Detalhar E14-S01…S10 antes do `/tdd`. | `to-issues` (publica; gepetto planeja). |
| `qa-test-planner` | Plano de testes abrangente. | Insumo de `/secure-e2e` e `/qa-analyst` (§30, SEC-UX). | `qa-analyst` (veredito; este planeja). |
| `database-schema-designer` | Schemas SQL/NoSQL, índices, migrações. | Migrações D01/D05 (Conta, `state`/`sla_due_at`). | `improve-codebase-architecture` (código; este é dados). |
| `mermaid-diagrams` / `c4-architecture` / `archify` | Diagramas Mermaid/C4 e mapas HTML auto-contidos e validados. | Fluxos, estados, delta antes/depois (fora do repo). | `draw-io`/`excalidraw` (rascunho livre; estes são verificáveis). |
| `draw-io` / `excalidraw` | Diagramas livres e rápidos. | Rascunho do protótipo A/B. | `archify` (validado; estes são livres). |
| `naming-analyzer` / `reducing-entropy` | Nomes melhores; menos código. | Refactor S03. | `agent-md-refactor` (docs; estes são código). |
| `lesson-learned` | Extrai lições de mudanças recentes. | Fecho de cada slice. | `handoff` (estado; este é aprendizado). |
| `agent-md-refactor` | Reforma `AGENTS.md`/`CLAUDE.md` inchados. | Só se incharem. | `setup-skills` (cria; este reforma). |
| `writing-clearly-and-concisely` / `marp-slide` | Escrita clara; slides Markdown. | Docs e veredito. | `slides` (estratégico; marp é simples). |
| `backend-to-frontend-handoff-docs` / `frontend-to-backend-requirements` | Contratos front↔back. | Decisão §23 (fragmentos × renderer JS). | `query-docs` (lib externa; estes são contratos internos). |
| `commit-work` | Commits de alta qualidade. | Commits por slice. | Ver linha em §2.3. |
| `session-handoff` | Transferência entre sessões. | Ver §2.3. | — |
| `humanizer` / `web-to-markdown` / `perplexity` | Tom humano; página→Markdown; pesquisa. | Pontuais. | Ver §2.3. |
| `skill-judge` / `command-creator` / `plugin-forge` | Julga skills; cria comandos/plugins. | Ver §2.3. | — |
| `codex`, `gemini`, `datadog-cli`, `jira`, `mui`, `react-dev`, `react-useeffect`, `game-changing-features`, `ship-learn-next`, `daily-meeting-update`, `professional-communication`, `feedback-mastery`, `difficult-workplace-conversations`, `domain-name-brainstormer`, `meme-factory`, `dependency-updater`, `openapi-to-typescript`, `crafting-effective-readmes` | Nichos (React/MUI, Jira/Datadog, RH, domínios, deps). | Fora do programa (stack/serviços divergentes). | Análogos da tabela acima quando houver sobreposição. |

## 3. Fluxo no projeto

```text
grill-feature-with-docs → roadmap → ui-ux-pro-max → prototype → veredito+ADR
→ improve-codebase-architecture → to-issues → tdd → secure-e2e → diagnose (se falhar)
→ qa-analyst → PR → smoke real
```

## 4. Onde vive cada coisa

- Decisões do grill + inventário do módulo: `docs/agents/topwebchat.md` (local).
- Rastreamento e aceite: GitHub Issues + `ORCHESTRATOR-ROADMAP.md`.
- Segredos e runtime: `/root/.topweb-ops/paths.md` (0600, fora do repo).
