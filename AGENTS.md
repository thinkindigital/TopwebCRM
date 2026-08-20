## Mandatory startup protocol
Before doing any analysis, code change, refactor, migration, or implementation, read in this exact order:

1. `docs/AI_CONTEXT.md`
2. `docs/ARCHITECTURE.md`
3. `docs/SECURITY_RULES.md`
4. `docs/PRODUCT_RULES.md` if it exists
5. the task file under `docs/tasks/` that best matches the current request
6. any module-specific documentation near the affected code
7. only then inspect and change source files

If any of these files are missing, state that clearly before proceeding.

## Non-negotiable working rules
- Do not start coding before understanding the real repository structure.
- Do not assume Laravel conventions are enough; verify the actual Krayin implementation.
- Do not invent classes, routes, tables, policies, services or folders without checking the codebase.
- Do not perform broad refactors unless explicitly requested.
- Do not rename or move files for aesthetics.
- Do not duplicate sensitive authorization logic in many places if it can be centralized.
- Do not solve sensitive-data problems only in the frontend.
- Do not treat a UI-only masking solution as complete.
- Do not introduce dependencies without explicit justification.
- Do not use Evo CRM as copy-paste source; use it as functional and architectural reference.

## Required output format for relevant tasks
For any non-trivial task, first return:
1. objective summary
2. files/documents read
3. code areas inspected
4. exact files likely affected
5. implementation plan
6. risks
7. tests to run

Only after that, proceed to implementation unless the user explicitly asked for immediate execution.

## Sensitive data rule
Any task involving customer/contact/company/lead/opportunity data must consider:
- UI visibility
- backend authorization
- API output
- search/filter/autocomplete
- exports
- logs
- notifications
- integrations
- cache if applicable

If any of these surfaces remain unchecked, state that the work is partial.

## Integration rule
For external providers such as WhatsApp APIs:
- keep provider-specific logic behind adapters/services
- keep secrets in env/config
- prefer jobs/queues for asynchronous flows
- plan for retries and error logging
- avoid controller-heavy integration logic

## Documentation update rule
After completing a meaningful task:
- update `docs/CHANGELOG_AI.md`
- update the relevant task document if the understanding changed
- mention any new architectural constraint discovered in the codebase

## When mapping the project
When asked to map Krayin or Evo CRM:
- identify real modules and boundaries
- trace entry -> rule -> persistence -> output
- cite exact files and folders
- distinguish confirmed findings from hypotheses
- list safe extension points
- list dangerous change zones

## Priority order
When choosing between options, prefer:
1. correctness
2. security
3. maintainability
4. low coupling
5. minimal disruption
6. speed

## Stop conditions
Stop and ask for clarification only when the task is truly blocked by missing repository content or conflicting instructions. Otherwise, make the best grounded analysis possible from the available code and docs.

## Agent Skills

Skills disponíveis e quando usar:

### Engenharia
- **orchestrator** — Governança de projeto: roadmap, auditoria, fragmentação em issues, execução, QA. Use no início de ciclos maiores ou para estruturar trabalho complexo.
- **setup-skills** — Provisiona governança documental (AGENTS.md, CONTEXT.md, docs/agents/, docs/adr/). Use na inicialização do projeto.
- **roadmap** — Gerencia `ORCHESTRATOR-ROADMAP.md` com Epics (IDs E##) e links GitHub. Use para planejamento estratégico.
- **grill-with-docs** — Consolida linguagem de domínio e decisões arquiteturais; atualiza CONTEXT.md e ADRs inline. Use quando houver termos ambiguos ou decisões não documentadas.
- **improve-codebase-architecture** — Encontra oportunidades de refatoração, consolida módulos acoplados, torna codebase mais testável/navegável. Use para melhoria arquitetural.
- **diagnose** — Loop disciplinado para bugs/regressões: reproduzir → minimizar → hipótese → instrumentar → fixar → regression-test. Use para bugs difíceis.
- **tdd** — Desenvolvimento orientado a testes (red-green-refactor). Use para features/bugs com testes first.
- **secure-e2e** — Testes E2E e security-first com Playwright. Valida fluxos de usuário e desafia barreiras de segurança. Use para validação de integrações críticas.
- **query-docs** — Resolve documentação de bibliotecas terceiras (Redis, Next.js, shadcn, database SDKs) via Context7. Use ao integrar libs externas.
- **prototype** — Build throwaway para validar design (terminal app para state/logic, ou UI variations). Use para explorar opções antes de commit.
- **zoom-out** — Visão ampla de seção de código desconhecida. Use quando precisar entender contexto maior.

### Qualidade e Processo
- **qa-analyst** — Ciclo completo QA: análise requisitos, planejamento testes, casos de teste, execução (manual/automatizada), relato bugs, melhoria processo. Obrigatório no final de cada DAG.
- **triage** — Máquina de estados para issues (criar, triar, revisar bugs/features, preparar para agente AFK). Use para gestão de issues.
- **to-issues** — Transforma planos/specs/PRDs/Epics em GitHub Issues rastreáveis (IDs estáveis, slices verticais, dependências, critérios de aceite). Use para fragmentar trabalho.
- **to-prd** — Transforma contexto atual em PRD publicado no tracker. Use para formalizar requisitos.

### Produtividade
- **caveman** — Modo ultra-comprimido (~75% menos tokens). Use quando pedir "caveman mode" ou "seja breve".
- **grill-me** — Entrevista implacável sobre plano/design até entendimento compartilhado. Use para stress-testar design próprio.
- **handoff** — Compacta conversa em documento para outro agente continuar. Use para troca de contexto.

### Projeto (TopwebCRM)
- **scaffold-mvp** — Inicializa novo projeto com stack MVP ágil. Use apenas em repositório vazio.
- **setup-pre-commit** — Husky + lint-staged (Prettier), type checking, testes no commit. Use para configurar hooks.

### Documentação
- **edit-article** — Reestrutura, melhora clareza, aperta prosa de artigos. Use para revisar docs.
- **write-a-skill** — Cria novas skills com estrutura correta. Use para gaps não cobertos.
- **obsidian-vault** — Busca/cria/organiza notas no Obsidian com wikilinks. Use para knowledge base pessoal.

### Skills Oficiais Krayin (em `.github/skills/`)
- **crm-package-development** — Padrão oficial para pacotes/extensões Krayin (migrations, models, repositories, routes, controllers, views, config, menu, ACL).
- **pest-testing** — Padrão oficial para testes Pest no Krayin.

### Snapshots Locais (consultar por seção)
- `docs/krayincrm/llms-full.txt` — Contexto arquitetural Krayin v2.2
- `docs/ryzeapi/llms-full.txt` — Contratos RyzeAPI

**Precedência**: código local > docs locais TopwebCRM > llms-full local > documentação online > hipótese
