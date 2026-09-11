# SKILL_MAP — Skills disponíveis e relação com o TopwebCRM

> **Natureza deste documento:** índice. Não repete procedimentos de `docs/README.md`,
> `docs/ARCHITECTURE.md`, `docs/SECURITY_RULES.md`, `docs/PRODUCT_RULES.md`,
> `docs/topweb-chat/*` nem das `SKILL.md` originais. Aponta para a fonte canônica
> de cada assunto e registra apenas o que foi **verificado no checkout atual**.
> Ordem de autoridade: ver `docs/README.md` (código > ADRs > contexto/regras >
> arquitetura/docs de módulo > runbooks > roadmap/Issues).
>
> **Verificação:** 2026-09-11, branch `dev`, sem push para `main` nesta etapa
> (absorção + grill, sem alteração de comportamento).
> **Atualização:** fusão de skills aplicada (alltomatos em `37ae283` + 43 skills
> softaworks + archify + 6 companions premium já presentes na imagem).

## 1. Status das fontes externas (verificado, não presumido)

| Fonte | Estado verificado |
|---|---|
| `https://github.com/alltomatos/skills` (branch `main`, `37ae283` de 2026-09-10) | **Sincronizado em conteúdo.** Rename upstream `orchestrator/` → `developer/` **não** aplicado ao diretório local: o catálogo do harness desta sessão resolve `orchestrator` (renomear quebrou o loader — revertido e verificado). O conteúdo é o mais recente (`SKILL.md` idêntico ao `developer/` upstream, `diff` vazio; templates `ESTADO_DEVELOPER` + `developer-delegation-protocol` + `README` sincronizados). O rename de diretório vale para rebuild futuro do catálogo. `superkuma-monitoring` copiado; demais idênticas. Instalador oficial em modo redeploy **não** executado (trocaria diretórios reais por symlinks para clone efêmero em `/tmp`) — sync por cópia aditiva, escopo global autorizado. |
| `https://github.com/nextlevelbuilder/ui-ux-pro-max-skill` | Instalado via `npx -y ui-ux-pro-max-cli@2.15.0 init --ai opencode --global` em `/root/.opencode/skills/ui-ux-pro-max/`. Uso subordinado às regras do projeto — ver §2.2 e master-spec §20. |
| `https://github.com/softaworks/agent-toolkit` (90 commits, 43 skills) | Instalado via `npx -y skills add softaworks/agent-toolkit -s '*' -a opencode -g --copy -y` em `/root/.agents/skills/` + symlinks em `/root/.opencode/skills/` (cópia real no primeiro, links no segundo — nada dentro do repo). Estratégia de fusão adotada (decisão do usuário): governança alltomatos dita o fluxo; softaworks injeta capacidade utilitária/visual quando o `/developer` delegar. Mapeamento de uso em §2.4. |
| `https://github.com/tt-a1i/archify` | **Correção factual:** este repo **não** é gerenciador de versões PHP/Node/Python estilo scoop — é uma skill de **diagramas de arquitetura** (JSON IR → HTML/SVG auto-contido, 5 tipos de diagrama, validações determinísticas, suporta opencode). Instalada via `npx -y skills add tt-a1i/archify -a opencode -g --copy -y` (`archify` em `/root/.agents/skills/` + symlink). Para PHP no host, continua valendo: só via ferramenta de versionamento ainda a ser indicada — **aceito como não-risco**: PHP roda nos containers; nada fora de Docker será instalado sem essa ferramenta aprovada. |
| Skills do repo (`.github/skills/`) | Fonte canônica para agentes neste repositório: `.github/skills/*/SKILL.md` (espelhos `.ai/`, `.claude/`, `.codex/`, `.cursor/`, `.kilocode/` são symlinks versionados). Catálogo do ambiente **não** deve ser copiado para a documentação do produto além deste índice (regra de `AGENTS.md`). |

## 2. Catálogo da máquina (o que existe, para que serve, quando usar no projeto)

### 2.1 Engenharia (`/root/.opencode/skills/engineering/` — 21 dirs)

| Skill | Função (1 linha) | Quando usar no TopwebCRM |
|---|---|---|
| `orchestrator` (conteúdo = `developer` upstream @37ae283) | Governança: audita pré-condições, coordena roadmap/Issues/execução/QA | Início de qualquer programa; dono do fluxo §27/§52 do master-spec |
| `setup-skills` | Provisiona `AGENTS.md`/`CONTEXT.md`, `docs/agents/`, `docs/adr/` | Só se faltar artefato de governança (hoje presentes — ver auditoria §6) |
| `roadmap` | Mantém `ORCHESTRATOR-ROADMAP.md` com Epics `E##` + Issues linkadas | Após veredito de protótipo, antes de `/to-issues`; reconciliar colisão E10–E13 |
| `grill-feature-with-docs` | Mentoria de módulo existente: lê código+docs, confronta, atualiza glossário | **Etapa em execução** para o TopwebChat com o master-spec como intenção |
| `grill-with-docs` | Mentoria de linguagem/decisões de domínio antes de implementar | Se surgir termo novo de domínio fora do TopwebChat |
| `to-issues` | Fatia Epic aprovada em Issues rastreáveis (slices verticais) | Só após roadmap reconciliado; não publicar E14–E19 do vision literalmente |
| `to-prd` | Gera PRD a partir do contexto atual | Não usado neste programa (spec mestra já existe) |
| `triage` | Estado e preparo de Issues (`needs-triage`, `ready-for-agent`, etc.) | Higiene do tracker antes de cada DAG |
| `tdd` | Red-green-refactor por comportamento público | Cada slice (tracer bullets TB01–TB08 do master-spec §29) |
| `diagnose` | reproduce→minimise→hypothesise→instrument→fix→regression-test | Bugs/regressões difíceis; alternativa ao chute |
| `secure-e2e` | E2E funcional + testes negativos de segurança (IDOR, auth, XSS) | Toda slice com superfície sensível (conversa, mídia, assignment, busca) |
| `qa-analyst` | Gate final obrigatório da DAG (requisito→teste→evidência) | Antes de qualquer PR para `main`; pode usar `qa-test-planner` (§2.4) como insumo |
| `query-docs` | Contrato atual de libs externas (Context7 ou tipagem local) | Laravel/Krayin, Playwright, OpenWA/Baileys quando a UX depender do contrato real |
| `improve-codebase-architecture` | Deepening: seams, localidade, alavancagem; relatório HTML fora do repo | Sobre `show.blade.php` + JS inline **antes** do redesign (decisão de renderer §23) |
| `prototype` | Protótipo descartável (terminal ou variações de UI numa rota) | A/B do master-spec §24 (`?variant=A\|B`, depois apagar) |
| `scaffold-mvp` | Bootstrap de repo vazio | **Não aplicável** (repo existente — master-spec §Ap.A) |
| `zoom-out` | Contexto amplo quando perdido no detalhe | Sob demanda |
| `mcp-builder` | Construir servidores MCP (Python FastMCP / Node SDK) | Só se surgir integração via MCP (hoje não há) |
| `devsetup` | Provisionar máquina Windows via winget | Não usar aqui (host Linux; regra `AGENTS.md`) |
| `expo-expert` | Conhecimento Expo/React Native | Não usar (stack é Laravel/Blade) |
| `superkuma-monitoring` | Deploy/operação SuperKuma + MCP de descoberta | Não usar neste programa (completude do framework) |

### 2.2 Design (`ui-ux-pro-max` + companions premium)

| Skill | Função | Uso no projeto (subordinado) |
|---|---|---|
| `ui-ux-pro-max` | Inteligência de design: system generator, 79 estilos (50 ativos), 192 paletas/regras, 74 fontes, 119 guidelines, 22 stacks; busca local via `scripts/search.py` | Design system + audits do TopwebChat **depois** do grill e **antes** do protótipo. Autoridades: segurança/produto TopwebCRM > contratos Krayin/stack > branding/tokens > ADR/design system > recomendações da skill. Stacks: `--stack laravel` (principal), `--stack html-tailwind` (detalhe). Nunca inventar recomendação sem match verificado. |
| `design-system`, `design-system-starter` | Tokens em 3 camadas, specs de componentes, starter de design system | Apoio ao MASTER do TopwebChat (E14-S01); `design-system-starter` (softaworks) como checklist alternativo |
| `ui-styling`, `design`, `brand`, `banner-design`, `slides` | shadcn/Tailwind/dark-mode; identidade visual/CIP; brand voice; banners; slides Chart.js | **Uso restrito:** só como referência dentro das restrições Krayin/TopwebCRM (master-spec §20: sem identidade alienígena, sem framework novo). `slides` pode servir ao handoff visual do veredito do protótipo. |

### 2.3 Produtividade / misc / pessoais (resumo)

`grill-me` (decisões em aberto, 1 pergunta por vez), `handoff` (troca de agente/sessão; usar também `session-handoff` §2.4 em sessões longas), `skill-creator` e `write-a-skill` (criar/avaliar skills), `caveman` (modo conciso).
`git-guardrails-claude-code`, `migrate-to-shoehorn`, `scaffold-exercises`, `setup-pre-commit` (casos específicos; `scaffold-exercise` do master-spec §49 usa exercícios manuais, não a skill). `commit-work` (§2.4) vale para os commits do programa.
`edit-article`, `obsidian-vault` (pessoais, fora do programa).
Skills oficiais do Krayin no repo: `crm-package-development` (novo pacote sem tocar core), `pest-testing` (Pest 3 + Laravel 12; ver `tests/Pest.php`, `phpunit.xml`).

### 2.4 Fusão softaworks + archify — o que aprimora nossas alterações

Governança alltomatos comanda; abaixo só entram **quando o `/developer` delegar**:

| Skill nova | Onde entra no programa TopwebChat |
|---|---|
| `requirements-clarity` | Apoio à Fase 2 do grill (clareza de requisitos antes de codar); reforça Definition of Ready §45 |
| `gepetto` | Planejamento detalhado de slices E14-S01…S10 antes do `/tdd` |
| `qa-test-planner` | Insumo de plano de testes para `/secure-e2e` e `/qa-analyst` (matriz §30, SEC-UX-01…12) |
| `database-schema-designer` | Quando D01/D05 VIXarem migração (Conta WhatsApp, `state`/`sla_due_at`): normalização, índices, backfill revisável |
| `mermaid-diagrams`, `c4-architecture`, `archify` | Diagramas verificáveis (fluxo de envio §Fluxo, máquina de estados, delta arquitetural antes/depois do refactor S03); `archify` gera HTML auto-contido fora do repo |
| `draw-io`, `excalidraw` | Rascunhos visuais descartáveis do protótipo A/B (alternativa rápida, fora do repo) |
| `naming-analyzer`, `reducing-entropy` | Higiene durante o refactor S03 (nomes de seams, remoção de duplicação SSR×JS) |
| `lesson-learned` | Fechar cada slice com lição registrada (sem dado sensível) |
| `agent-md-refactor` | Se `AGENTS.md`/`CONTEXT.md` incharem — sempre com progressive disclosure |
| `writing-clearly-and-concisely`, `marp-slide` | Clareza das docs canônicas e apresentação do veredito (fora do repo até decisão) |
| `backend-to-frontend-handoff-docs`, `frontend-to-backend-requirements` | Contratos polling/fragmentos vs renderer JS na decisão §23 |
| `commit-work` | Commits do programa (convencionais, um por slice) |
| `session-handoff`, `humanizer` | Continuidade entre sessões; revisão de textos voltados ao usuário |
| `web-to-markdown`, `perplexity` | Pesquisa externa pontual (sempre confrontada com código local) |
| `skill-judge`, `command-creator`, `plugin-forge` | Só se criarmos skill/comando novo (ex.: comando de smoke `topwebchat:smoke`) |
| Restantes (`codex`, `gemini`, `datadog-cli`, `jira`, `mui`, `react-*`, `game-changing-features`, `ship-learn-next`, `daily-meeting-update`, `professional-communication`, `feedback-mastery`, `difficult-workplace-conversations`, `domain-name-brainstormer`, `meme-factory`, `dependency-updater`, `openapi-to-typescript`, `excalidraw` já citado) | **Fora do programa** — stack divergente (React/MUI), serviços externos ausentes (Jira/Datadog) ou RH/comunicação; instaladas para completude, sem uso previsto |

## 3. Como as skills se relacionam neste projeto (fluxo do master-spec)

```text
/grill-feature-with-docs (TopwebChat + master-spec como intenção; reconcilia docs↔código) [EM EXECUÇÃO]
  → /roadmap (reconcilia E08/E10–E13; decide E14 única) — bloqueia /to-issues até IDs estáveis
  → ui-ux-pro-max (+ design-system-starter) → MASTER persistido
  → /prototype (A familiar × B central operacional, ?variant=, mobile transversal; depois apagar)
  → veredito humano + ADR → /improve-codebase-architecture (+ naming-analyzer/reducing-entropy; archify/mermaid p/ delta)
  → /to-issues (E14-S01…S10; gepetto/requirements-clarity como insumo) → por slice: /tdd → /query-docs
  → /secure-e2e (+ qa-test-planner) → /diagnose (se falhar) → /qa-analyst (gate) → commit-work → PR → smoke real
```

Gates: G0 docs fiéis · G1 design system · G2 veredito protótipo · G3 safety arquitetural ·
G4 QA por slice · G5 evidência em produção. Anti-patterns: master-spec §48.

## 4. Absorção do sistema (como funciona, sem alterar essência)

**Linguagem aplicada:** PHP 8.3 + Laravel 12 (fork Krayin 2.2.3, Concord + Prettus),
Blade + componentes Vue + Vite (4 builds: raiz/Admin/Installer/WebForm; TopwebChat
reutiliza o build do Admin, sem build próprio), Pest 3 / PHPUnit 11
(`composer.json`, `package.json`, `vite.config.js`, `docker/php/Dockerfile*`).
**Meios de execução:** local `compose.yaml` (app :8000, queue, scheduler, MySQL 3307,
Redis 6380; OpenWA separado :2785); produção Swarm (`compose.production.yaml` +
`compose.openwa.production.yaml`, imagem GHCR, instalador `SetupThinkin.sh`, Epic E11);
comandos `topweb-chat:reconcile[--history]`, `retry-failed`, `close-stale-attendances`,
`project-lead-media` (schedulers em `TopwebChatServiceProvider.php:65-90`).
PHP existe **só nos containers** (aceito como não-risco pelo usuário; nenhuma
instalação fora de Docker sem a ferramenta de versionamento aprovada).
Detalhes: `docs/operations/LOCAL_DEVELOPMENT.md`, `docs/operations/DEPLOYMENT.md`,
`docs/SYSTEM_MAP.md`.
**Comunicação dev↔sistema:** GitHub Issues = tracker oficial
(`docs/agents/issue-tracker.md`, sem dados sensíveis em relatos públicos);
`ORCHESTRATOR-ROADMAP.md` (E01–E12) resume; `.scratch/` é rascunho temporário;
ADRs `docs/adr/0001–0010`; PR template + workflows em `.github/`;
logs `storage/logs/topweb-chat-client-*.log` (canal `topweb_chat_client`, 14 dias) +
telemetria allowlisted (`ConversationController::clientEvent`, sem conteúdo integral).
**Essência a preservar:** backend como autoridade, concessão individual
`can_view_sensitive_data`, mídia privada, `operation_key`, `unknown` sem retry cego,
claim atômico, polling como baseline, design pertencente ao Krayin (invariantes
UX-I01…I11 do master-spec §8).

## 5. Inventário da pasta (verificado no checkout)

### 5.1 Reutilizáveis (usar antes de criar algo novo)

- `app/Services/SensitiveDataService.php`, `SensitiveFileService.php` (+ disco `private`)
- `TopwebChat/.../Providers/Contracts/MessagingProvider.php` (binding por `TOPWEB_CHAT_ENGINE`)
- Repositories `Conversation/Message/Instance/WebhookEvent/InternalNote`
  (+ `Contracts/*`); base DataGrid/Export; Bouncer/ACL (`Admin/.../Bouncer.php`, `Config/acl.php`)
- Resources com mascaramento (`Admin/.../Http/Resources/Lead|Person|Organization|Activity|...Resource.php`)
- Jobs `SendMessage/ProcessWebhookEvent/SyncConversationHistory/DownloadMessageMedia/Reconcile*/MarkConversationRead/ProjectLeadMedia`
- `Config/topweb-chat.php` (engine, limites de mídia, `attendance.*`, eventos webhook)

### 5.2 Ferramentas únicas do TopwebChat (não reinventar)

`OpenWaProvider` / `BaileysProvider` (paridade ~954 linhas cada);
`ContactResolverService` + `RemoteIdentityService` (resolução `@lid`, normalização BR 9º dígito);
`LeadMediaProjector` (projeção idempotente, mesmo objeto privado);
`AttendanceService` (Activity agregadora 24h); `ConversationAccessService`;
`MessageService` (outbox + `operation_key`); webhook HMAC (`WebhookController`);
`show.blade.php` (~55 KB / 1068 linhas, polling 3 s + diff/fallback + âncora +
`aria-live`) + `index.blade.php` + settings + extensions de Pessoa/Lead.
Caminhos completos: `packages/Webkul/TopwebChat/src/{Config,Console/Commands,Contracts,Http/Controllers,Jobs,Models,Providers,Repositories,Resources/views/conversations,Routes,Services}`; testes `tests/Feature/TopwebChat/` (20 arquivos).

### 5.3 Resumo de funcionamento

Outbound: composer → `MessageController` → `MessageService` (claim `lockForUpdate` se sem
atendente, `operation_key`) → `SendMessage` (fila Redis → OpenWA `X-API-Key`) →
webhooks reconciliam ACK/entrega/falha. Inbound: webhook HMAC → `WebhookEvent`
idempotente → normalização (LID resolvido, sem heurística) → mídia em job para disco
privado → projeção no Lead/Pessoa → timeline por polling 3 s. Atendimento: primeiro
outbound humano abre Activity; mensagens reais renovam 24 h; `close-stale` encerra;
novo outbound abre continuado. Reconciliação 1/5 min; retry só em condição segura.

## 6. Confronto documentação × código (gaps; nada corrigido nesta etapa)

**Implementado-confirmado (exemplos):** provider desacoplado + flag de engine; HMAC +
idempotência; projeção webhook só `message.received/sent/ack/failed` + `session.status`;
polling/diff/`operation_key`; envio texto+mídia com `unknown` sem retry cego; fila sem
atendente + claim atômico; `@lid` via API oficial; mídia privada autorizada; projeção
idempotente; Attendance 24 h; reconciliação; segredos criptografados; mascaramento base.
**Decidido-mas-não-implementado:** (D01) Conta duradoura × Sessão descartável (`phone`
correlator) — código só tem `Instance`; (D02) arquivamento sem hard delete — código dá
`cascadeOnDelete`; (D03) sessão padrão — código exige exatamente 1 `enabled`;
(D04) dono do Lead como fonte da verdade — `ConversationAccessService` autoriza por
`assigned_user_id`/admin; (D05–D06) estado explícito + SLA — só `status/closed_at` e
`inactivity_minutes`; (D07) importação manual 1–100 — só sync automática;
(D08) mascaramento seletivo no texto — conteúdo integral; (D09) quarentena — código
cria `Person` automaticamente. **Divergência Conta×Instância registrada como gap aberto
por decisão do usuário — sem correção de código nesta etapa.**
**Planejado (não tratar como entregue):** E01–E12 (nenhuma `done`); E14–E19 do
`CHAT-UX-VISION.md` (planejamento não publicado; E20 removida); E14-S01…S10 / E15–E17 do
master-spec (propostas condicionadas à reconciliação E08/E10–E13); P1–P3 do
`COMMUNICATION-ROADMAP.md`; roadmap `BAILEYS.md`.
**Existe em código e sem seção canônica:** `BaileysProvider` completo (README diz
“skeleton”); schedulers `retry-failed`/`project-lead-media` omitidos do README;
`destroyByPerson`, `client-events`, `MarkConversationRead`, `ReconcileConversationContext`;
`operation_key`, reagenda 429, status monotônico, normalização BR (só em código);
referência a `ImportRealEstateDemo` inexistente em `TopwebChatServiceProvider.php:43`;
trava `singleEnabledInstance` + `unique(instance_id,remote_jid_key)` que inviabiliza
“múltiplas sessões” sem estar descrita na operação.

**Correção de auditoria (developer, 2026-09-11, via `git HEAD...main` + container prod):**
o checkout `dev` está **0 ahead / 35 behind `main`** — a absorção acima foi feita sobre
árvore defasada. Na `main`: (a) `ImportRealEstateDemo.php` e `SmokeChat.php`
**existem** (`topwebchat:smoke` parametrizado, PR #85) — o "P1 provider" **não**
procede; console prod boota (`artisan about` exit 0, Laravel 12.58/PHP 8.3.33);
(b) `BaileysProvider` do workdir ≡ `main` (só comentários diferem);
(c) SEARCH-SEC-01 parcialmente implementada (#87 docs + #88 escopo kanbanLookup,
#86 closed); (d) `BAILEYS.md` do workdir ≡ `main`. Contratos comportamentais do
grill seguem válidos; **referências file-level devem ser rechecadas na `main`
antes de qualquer TDD**. `CHAT-UX-VISION.md` e `TOPWEBCHAT-UX-MASTER-SPEC.md`
existem só no workdir `dev` (nunca commitados).

## 7. Auditoria de governança (developer Fase 2, hoje)

Git ok · remote `origin` ok · `AGENTS.md`/`CONTEXT.md` ok · `docs/agents/` ok ·
`docs/adr/` (10) ok · `ORCHESTRATOR-ROADMAP.md` ok (E01–E12, IDs estáveis) ·
skills instaladas ok (após §1). **Fase 0 parcial:** `gh` ausente no host; leitura
via API pública ok, escrita via PAT (`/root/.key_pat`, user `agthinkindigital`) —
caminhos operacionais em `/root/.topweb-ops/paths.md` (0600, fora do repo; nunca
publicar valores). **Evidência GitHub (PAT, 2026-09-11):** renomeadas #77–#81
(colisões [E10]/[E11]/[E12]/[E13]/[E10-S1] eliminadas, nota de governança no corpo);
criada #90 (LGPD tratativas futuras, ligada à E02 #6). Pendente: reconciliar E13
(#80), sync E08/roadmap, drift docs (skeleton/schedulers/0.23.3×0.23.4).
Nada enviado para `main`; branch `dev` preservada com as alterações locais alheias.

## 8. Riscos, verificações e pendências desta etapa

- **Riscos:** instalador oficial por symlinks evitado de propósito (clone `/tmp`
  efêmero); novas skills fora do repo (repo limpo); PHP só em containers
  (aceito como não-risco); `archify` **não** gerencia versões — ferramenta de
  versionamento de PHP/Node/Python segue pendente de indicação correta.
- **Verificações executadas:** releitura `grill-feature-with-docs` + `developer` +
  `roadmap`; rename `orchestrator/`→`developer/` (SKILL.md idêntico, frontmatter
  `orchestrator`); API GitHub alltomatos (`37ae283` = mais recente);
  `npx skills add` (43 softaworks + archify, `--copy`, escopo opencode global);
  44 symlinks em `/root/.opencode/skills/`; `git status` do repo inalterado fora de `docs/SKILL_MAP.md`.
- **Pendências:** sync `dev`↔`main` (35 behind — decidir antes de implementar);
  grill Fase 2 concluída; governance cleanup E13/E08; design system; protótipo A/B +
  ADR; review arquitetural; `/roadmap` → `/to-issues`; smoke real (`topwebchat:smoke`
  já existe na `main`) antes de qualquer `done`.
- **Dúvidas reservadas ao usuário** (não presumir): qualquer comportamento ausente
  de docs e código será perguntado antes de implementar.
