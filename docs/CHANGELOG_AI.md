Não faça refatoração estética. Não renomeie sem necessidade. Não reorganize estrutura inteira. Foque só no objetivo.

## Finalidade
Registro técnico das análises e mudanças conduzidas com apoio de agente.

## Como registrar
Cada entrada deve conter:

- Data
- Tarefa
- Status
- Objetivo
- Arquivos lidos
- Arquivos alterados
- Impacto
- Riscos
- Testes
- Rollback
- Observações

---

### [2026-07-28] Compatibilidade com Setup Orion
**Status:** done

**Objetivo:** alinhar a stack de produção ao Docker Swarm, Portainer EE e Traefik instalados pelo Setup Orion.

**Impacto:**
- Setup Orion registrado como plataforma oficial de produção.
- Rede externa Traefik preservada e backend do CRM mantido em overlay privado.
- Volumes externos com nomes estáveis e afinidade por label de nó.
- Migrations separadas de queue/scheduler por variáveis de fase.
- Espera ativa pelo banco, healthchecks e limites de recursos configuráveis.
- Worker reiniciado pelo Swarm, com `retry_after` e grace period superiores ao timeout.
- MySQL e Redis globais não são reutilizados por segurança e isolamento.

**Riscos:** volumes locais continuam presos ao nó rotulado; rollback de imagem não desfaz migrations; a imagem precisa de registry em Swarm multinó.

**Testes:** parser Docker Compose, build da imagem, healthcheck HTTP e revisão estática especializada.

**Rollback:** restaurar o manifesto anterior sem remover volumes ou secrets externos.

---

### [2026-07-28] Resiliência RyzeAPI e deploy personalizado
**Status:** done

**Objetivo:** tornar mensageria, ambiente local e stack Swarm operacionais.

**Impacto:**
- Reconciliação manual/agendada de instância e catch-up de histórico conhecido.
- Paginação assíncrona do histórico com `from`, `to`, `hasMore` e cursor persistido para backfill.
- Leitura remota assíncrona, status monotônico e classificação de erro de envio.
- Tratamento de `429` com espera pelo reset informado pela RyzeAPI e erros sanitizados.
- Teto configurável de tentativas de envio e distinção entre ACK `sent` e entrega final.
- Proteção contra tomada concorrente e associação ambígua de contatos.
- Autorização e atribuição protegidas por transações com lock antes de qualquer mutação.
- Bloqueio de envio em backend quando a instância estiver desabilitada ou desconectada.
- Contadores inbound monotônicos e preservação de notas internas após exclusão do operador.
- Ingestão webhook atômica e serialização dos escritores concorrentes da timeline.
- Redis, worker e scheduler no compose local; `ext-redis` nas imagens.
- Stack parametrizada para imagem, domínio, rede e SMTP, com secrets externos.

**Riscos:** localhost continua sem descobrir conversas inbound novas sem túnel; grupos/LID ficam pendentes de domínio próprio; imagem local funciona somente no manager definido.

**Testes:** 25 testes focados de contratos RyzeAPI, ACL e dados sensíveis, com 79 asserções; migrações aplicadas; validação dos dois YAMLs; build e healthcheck HTTP 200 da imagem.

**Rollback:** reverter os arquivos do pacote TopwebChat, Docker e documentação desta entrada.

---

### [2026-07-28] Mapa operacional, imagem de produção e chat confiável
**Status:** done

**Objetivo**  
Documentar o fork real, mapear a integração RyzeAPI, criar a primeira imagem/stack de produção e tornar o envio/timeline do TopwebChat mais confiáveis.

**Arquivos lidos**  
- documentos obrigatórios, tarefas de Krayin/Ryze/WhatsApp e snapshots oficiais;
- providers, Concord, Bouncer, entidades centrais, traduções e builds;
- pacote `packages/Webkul/TopwebChat`, testes e infraestrutura Docker.

**Arquivos alterados**  
- `docs/SYSTEM_MAP.md`;
- `docs/TOPWEB_CHAT_RYZEAPI_MAP.md`;
- `docs/DOCKER_IMAGE_BUILD.md`;
- `docs/TOPWEB_CHAT_ARCHITECTURE.md`;
- `docs/tasks/IMPLEMENT_WHATSAPP_INTEGRATION.md`;
- `docker/php/Dockerfile.production` e auxiliares;
- `compose.production.yaml` e `.dockerignore`;
- migration, job, model, services, controllers, rotas, ACL, UI, traduções e testes do TopwebChat.

**Resumo técnico**  
Foi criado um mapa navegável do fork e do adapter Ryze. A imagem de produção incorpora código e dependências, usa Apache em 8080, usuário `www-data`, healthcheck e storage restrito. A stack separa app, worker, scheduler, MySQL e Redis, usa secrets externos e não monta volume sobre o código. No chat, cada envio recebe `operation_key`, é persistido como `queued` e entregue por job de tentativa única; timeout externo vira `unknown` para evitar duplicidade por retry cego. A timeline passou a consultar JSON sanitizado a cada cinco segundos, e status fora de ordem não rebaixam mensagens lidas.

**Impacto esperado**  
O repositório passa a ter referência operacional local, base de deploy customizado e atualização de conversas sem reload. Produção deixa de depender de bind mount e `artisan serve`.

**Riscos**  
- histórico Ryze e reconciliação de estados `unknown` ainda não foram implementados;
- contatos inbound ambíguos e grupos ainda exigem quarentena/domínio próprio;
- assets usam manifests versionados enquanto não houver lockfiles npm;
- volumes locais do Swarm exigem placement fixo ou storage compartilhado;
- segredos publicados anteriormente devem ser rotacionados.

**Testes executados/recomendados**  
- Pest: 19 testes e 55 asserções aprovadas em TopwebChat e visibilidade sensível;
- Pint aprovado nos nove arquivos PHP alterados;
- 15 rotas TopwebChat listadas e cache Blade compilado;
- migration validada em `--pretend` e aplicada no banco local;
- `compose.production.yaml` validado;
- imagem `topwebcrm:validation` construída, executada como `www-data`, marcada healthy e respondendo `/up` com HTTP 200;
- ausência de `.env`, docs, testes e presença dos quatro manifests confirmadas dentro da imagem.

**Rollback**  
Retornar à tag anterior da imagem, reverter a migration de rastreamento de entrega e restaurar controller/service/UI anteriores. Preservar `APP_KEY`, banco e `storage/app`.

**Pendências**  
- implementar reconciliação de histórico, mensagem, instância e webhook;
- separar vínculo inbound pendente e domínio de grupos;
- implementar mídia privada e interativos em etapas próprias;
- versionar lockfiles dos quatro builds Vite.

---
## Template
### [AAAA-MM-DD] Nome curto da tarefa
**Status:** analysis | planned | in_progress | done | blocked

**Objetivo**  
Descrever o objetivo técnico real.

**Arquivos lidos**  
- caminho/arquivo1
- caminho/arquivo2

**Arquivos alterados**  
- caminho/arquivo3
- caminho/arquivo4

**Resumo técnico**  
Explicar o que foi decidido e por quê.

**Impacto esperado**  
Descrever comportamento esperado após mudança.

**Riscos**  
- risco 1
- risco 2

**Testes executados/recomendados**  
- teste 1
- teste 2

**Rollback**  
Explicar forma simples de reversão.

**Pendências**  
- pendência 1
- pendência 2

---

## Registro inicial
### [2026-07-13] Inicialização documental do TopwebCRM
**Status:** done

**Objetivo**  
Criar a base documental para orientar agentes e futuras alterações no fork do Krayin CRM.

**Arquivos lidos**  
- docs/AI_CONTEXT.md
- docs/ARCHITECTURE.md
- docs/SECURITY_RULES.md
- AGENTS.md

**Arquivos alterados**  
- docs/AI_CONTEXT.md
- docs/ARCHITECTURE.md
- docs/SECURITY_RULES.md
- docs/CHANGELOG_AI.md
- AGENTS.md
- docs/tasks/MAP_KRAYIN_CRM.md
- docs/tasks/MAP_EVOCRM_REFERENCE.md

**Resumo técnico**  
Foi criada a estrutura documental base para obrigar leitura contextual, arquitetural e de segurança antes de alterações no projeto.

**Impacto esperado**  
Agentes passam a trabalhar com menor risco de alteração difusa, menor improvisação e maior aderência às regras do fork.

**Riscos**  
- documentação ficar desatualizada;
- agente ignorar tarefa específica se prompt do usuário for vago.

**Testes executados/recomendados**  
- validar se o agente lê AGENTS.md antes das tarefas;
- validar se o agente cita os documentos consultados no plano de execução.

**Rollback**  
Remover a camada documental ou simplificar AGENTS.md.

**Pendências**  
- mapear o Krayin real;
- mapear o Evo CRM real;
- registrar product rules específicas do negócio.

---

### [2026-07-13] Bootstrap local do Krayin CRM
**Status:** done

**Objetivo**  
Criar um fork local executável e reproduzível do Krayin CRM 2.2 sem alterar seu código funcional.

**Arquivos lidos**  
- AGENTS.md
- docs/AI_CONTEXT.md
- docs/ARCHITECTURE.md
- docs/SECURITY_RULES.md
- docs/PRODUCT_RULES.md
- docs/tasks/MAP_KRAYIN_CRM.md
- README.md
- composer.json
- package.json
- .env.example

**Arquivos alterados**  
- .dockerignore
- compose.yaml
- docker/php/Dockerfile
- docs/LOCAL_DEVELOPMENT.md
- docs/tasks/SETUP_KRAYIN_LOCAL.md
- docs/CHANGELOG_AI.md

**Resumo técnico**  
O repositório local foi vinculado ao upstream `krayin/laravel-crm` na branch `2.2`. Foi criado um ambiente Docker com PHP 8.3 e MySQL 8.0, instaladas as dependências travadas pelo Composer e executado o instalador oficial. A configuração de módulos sobrescrita pelo `vendor:publish` foi restaurada ao estado upstream.

**Impacto esperado**  
O CRM inicia em `http://localhost:8000` com banco persistente e comandos locais documentados.

**Riscos**  
- desempenho inferior em bind mounts do Docker Desktop no Windows;
- perda do banco ao executar `docker compose down -v`;
- assets versionados exigirem rebuild após futuras mudanças de frontend.

**Testes executados/recomendados**  
- `docker compose config`;
- `composer check-platform-reqs` no container;
- migrations e seeders pelo instalador oficial;
- acesso HTTP à tela de login;
- autenticação real e redirecionamento ao dashboard.

**Rollback**  
Executar `docker compose down -v` e remover os arquivos de infraestrutura adicionados.

**Pendências**  
- criar banco isolado antes de executar a suíte completa;
- definir localização, moeda e dados administrativos definitivos.

---

### [2026-07-14] Visibilidade de dados sensíveis por perfil
**Status:** done

**Objetivo**  
Centralizar a decisão de visibilidade e impedir exposição integral de dados sensíveis para perfis sem autorização nas superfícies administrativas do Krayin.

**Arquivos lidos**  
- AGENTS.md
- docs/AI_CONTEXT.md
- docs/ARCHITECTURE.md
- docs/SECURITY_RULES.md
- docs/PRODUCT_RULES.md
- docs/tasks/IMPLEMENT_SENSITIVE_DATA_VISIBILITY.md
- docs/tasks/MAP_KRAYIN_CRM.md
- .github/skills/crm-package-development/SKILL.md
- .github/skills/pest-testing/SKILL.md
- packages/Webkul/Admin, DataGrid, Contact, Lead, Activity, Email, Quote, Attribute e Automation

**Arquivos alterados**  
- config/sensitive-data.php
- config/filesystems.php
- bootstrap/app.php
- app/Console/Commands/MigrateSensitiveAttachments.php
- app/Services/SensitiveDataService.php
- app/Services/SensitiveFileService.php
- app/Providers/AppServiceProvider.php
- resources/lang/en/sensitive-data.php
- resources/lang/pt_BR/sensitive-data.php
- packages/Webkul/DataGrid/src/Column.php
- packages/Webkul/DataGrid/src/DataGrid.php
- packages/Webkul/Admin/src/DataGrids/Activity/ActivityDataGrid.php
- packages/Webkul/Admin/src/DataGrids/Contact/OrganizationDataGrid.php
- packages/Webkul/Admin/src/DataGrids/Contact/PersonDataGrid.php
- packages/Webkul/Admin/src/DataGrids/Lead/LeadDataGrid.php
- packages/Webkul/Admin/src/DataGrids/Mail/EmailDataGrid.php
- packages/Webkul/Admin/src/DataGrids/Quote/QuoteDataGrid.php
- packages/Webkul/Admin/src/Http/Controllers/Activity/ActivityController.php
- packages/Webkul/Admin/src/Http/Controllers/Contact/OrganizationController.php
- packages/Webkul/Admin/src/Http/Controllers/Contact/Persons/PersonController.php
- packages/Webkul/Admin/src/Http/Controllers/DashboardController.php
- packages/Webkul/Admin/src/Http/Controllers/Lead/LeadController.php
- packages/Webkul/Admin/src/Http/Controllers/Lead/QuoteController.php
- packages/Webkul/Admin/src/Http/Controllers/Mail/EmailController.php
- packages/Webkul/Admin/src/Http/Controllers/Quote/QuoteController.php
- packages/Webkul/Admin/src/Http/Resources/ActivityResource.php
- packages/Webkul/Admin/src/Http/Resources/EmailResource.php
- packages/Webkul/Admin/src/Http/Resources/LeadResource.php
- packages/Webkul/Admin/src/Http/Resources/OrganizationResource.php
- packages/Webkul/Admin/src/Http/Resources/PersonResource.php
- packages/Webkul/Admin/src/Http/Resources/QuoteResource.php
- packages/Webkul/Admin/src/Http/Resources/StageResource.php
- packages/Webkul/Admin/src/Resources/views/components/activities/actions/*.blade.php
- packages/Webkul/Admin/src/Resources/views/components/attributes/*.blade.php
- packages/Webkul/Admin/src/Resources/views/components/layouts/header/{desktop,mobile}/mega-search.blade.php
- packages/Webkul/Admin/src/Resources/views/contacts/persons/view/organization.blade.php
- packages/Webkul/Admin/src/Resources/views/dashboard/index.blade.php
- packages/Webkul/Admin/src/Resources/views/leads/**/*.blade.php
- packages/Webkul/Admin/src/Resources/views/quotes/index.blade.php
- packages/Webkul/Activity/src/Models/File.php
- packages/Webkul/Activity/src/Repositories/ActivityRepository.php
- packages/Webkul/Email/src/Models/Attachment.php
- packages/Webkul/Email/src/Repositories/AttachmentRepository.php
- tests/Feature/SensitiveDataVisibilityTest.php
- docs/tasks/IMPLEMENT_SENSITIVE_DATA_VISIBILITY.md
- docs/FUTURE.md
- docs/CHANGELOG_AI.md

**Resumo técnico**  
Foi criada a permissão `sensitive_data.view`. Roles com `permission_type=all` ou roles customizadas com essa permissão recebem dados integrais; demais perfis recebem máscaras ou campos nulos. `SensitiveDataService` classifica campos exatos, tipos de atributos customizados e padrões documentais, aplica máscaras e remove campos protegidos de updates não autorizados. Resources, grids, formulários, buscas, ordenação, filtros, exportações, dashboard, atividades, anexos, cotações e payloads Vue foram ajustados. Dados persistidos e histórico permanecem íntegros; a ocultação acontece na saída por perfil. Anexos novos de e-mail e atividade usam o disco privado configurado; suas URLs apontam para controladores autorizados. O comando `sensitive-data:migrate-attachments` move arquivos legados e apaga a cópia pública somente após validar tamanho e destino.

**Impacto esperado**  
Usuários comuns continuam criando registros, mas não leem nem sobrescrevem valores protegidos existentes. Administradores mantêm os fluxos completos. A mesma abstração pode ser usada por resources e adaptadores futuros da RyzeAPI.

**Riscos**  
- webhooks e workflows são uma fronteira confiável e ainda podem usar valores integrais configurados por administradores;
- eventos de extensão Blade recebem modelos crus e exigem revisão de qualquer customização futura;
- autorização por registro/ownership continua sendo uma camada distinta e possui lacunas legadas fora deste escopo;
- histórico legado permanece integral no banco, embora seja ocultado no `ActivityResource` para perfis comuns;
- campos de contato de warehouses não entraram no escopo inicial de entidades comerciais.
- instalações com anexos legados devem executar primeiro `php artisan sensitive-data:migrate-attachments --dry-run` e depois o comando sem `--dry-run`; até isso ocorrer, arquivos antigos continuam no disco público.

**Testes executados/recomendados**  
- executado `git diff --check` sem erros;
- executadas varreduras estáticas de serialização, respostas JSON, downloads, logs e cache;
- criado `tests/Feature/SensitiveDataVisibilityTest.php` para roles, máscaras, atributos customizados, resources de pessoa/atividade, exportabilidade e migração de anexos;
- executado Pest com 10 testes e 34 asserções aprovadas;
- executado Pint com aprovação dos 37 arquivos modificados e dos 5 arquivos PHP novos validados explicitamente;
- executado `php artisan view:cache` com sucesso;
- validados `/up` e `/admin/login` com HTTP 200 e `docker compose config --quiet` sem erros.

**Rollback**  
Reverter os arquivos listados, remover os registros dos singletons/configuração ACL e retirar a permissão `sensitive_data.view` das roles customizadas. Não há migration de banco nem dependência nova. Arquivos já movidos ao disco privado precisam ser preservados ou devolvidos ao disco anterior antes do rollback.

**Pendências**  
- executar e conferir `php artisan sensitive-data:migrate-attachments --dry-run` antes de migrar qualquer instalação com dados;
- definir política de confiança para webhooks, notificações e o adapter da RyzeAPI;
- auditar autorização por registro em e-mails, cotações, leads e atividades;
- decidir se dados de warehouses entram na mesma classificação.

---

### [2026-07-14] Documentação operacional, build Docker e agentes especializados
**Status:** done

**Objetivo**  
Documentar a concessão da visualização integral de dados sensíveis, registrar o estado real da imagem Docker e preparar agentes customizados para trabalhos especializados em KrayinCRM e RyzeAPI.

**Documentos e código inspecionados**  
- `AGENTS.md` e documentos obrigatórios em `docs/`;
- tarefas de dados sensíveis, setup local, RyzeAPI e WhatsApp;
- ACL, roles, usuários, `SensitiveDataService` e `AppServiceProvider`;
- `compose.yaml`, `docker/php/Dockerfile`, `.dockerignore`, Composer, Vite e assets;
- documentação oficial do Codex sobre subagentes, skills e `AGENTS.md`.

**Arquivos alterados**  
- `docs/SENSITIVE_DATA_VISIBILITY.md`;
- `docs/DOCKER_IMAGE_BUILD.md`;
- `docs/CODEX_MULTIAGENTS.md`;
- `docs/LOCAL_DEVELOPMENT.md`;
- `docs/tasks/IMPLEMENT_SENSITIVE_DATA_VISIBILITY.md`;
- `docs/tasks/SETUP_KRAYIN_LOCAL.md`;
- `docs/tasks/MAP_RYZEAPI.md`;
- `docs/FUTURE.md`;
- `.codex/config.toml`;
- `.codex/agents/krayincrm-specialist.toml`;
- `.codex/agents/ryzeapi-specialist.toml`;
- `tests/Feature/SensitiveDataVisibilityTest.php` (ordenação de imports indicada pelo Pint);
- `docs/CHANGELOG_AI.md`.

**Resumo técnico**  
Foi confirmado que o Krayin mantém permissões na role e associa cada conta por `role_id`. A permissão `sensitive_data.view` pode ser marcada na árvore ACL de uma role customizada e a conta recebe a capacidade quando essa role é atribuída. Não foi criada uma segunda fonte de autorização por usuário; exceções individuais usam role dedicada. O Dockerfile atual foi documentado como ambiente de desenvolvimento dependente de bind mount, e o guia de build define o contrato necessário para uma futura imagem imutável do Portainer. Dois agentes customizados foram adicionados em `.codex/agents`, com paralelismo limitado a quatro threads e profundidade um.

**Riscos e limites**  
- roles com `permission_type=all` sempre visualizam dados integrais;
- a futura imagem de produção ainda não existe e não deve ser simulada com o Dockerfile atual;
- agentes paralelos aumentam consumo e podem conflitar se receberem escrita sobre os mesmos arquivos;
- a documentação RyzeAPI continua sendo fonte externa e deve ser consultada antes de cada decisão versionável.

**Validação**  
- TOML validado com `tomllib`, incluindo campos obrigatórios dos dois agentes;
- `git diff --check` executado sem erros;
- Pest aprovado com 10 testes e 34 asserções;
- Pint aprovado nos arquivos modificados e novos;
- Blade compilado com sucesso;
- `/up` e `/admin/login` retornaram HTTP 200;
- Compose validado com `docker compose config --quiet`.

**Rollback**  
Remover os novos documentos e arquivos em `.codex/agents`, restaurar `.codex/config.toml` e reverter as referências adicionadas às tarefas. Nenhuma migration, dependência ou mudança de banco foi introduzida nesta etapa.

---

### [2026-07-14] Especialização profunda e orquestração obrigatória dos agentes
**Status:** done

**Objetivo**  
Transformar os agentes KrayinCRM e RyzeAPI em especialistas baseados em evidência, criar skills oficiais com mapas carregáveis e tornar obrigatória a orquestração por domínio.

**Arquivos alterados**  
- `AGENTS.md`;
- `.codex/agents/krayincrm-specialist.toml`;
- `.codex/agents/ryzeapi-specialist.toml`;
- `.agents/skills/topwebcrm-krayin/`;
- `.agents/skills/topwebcrm-ryzeapi/`;
- `docs/CODEX_MULTIAGENTS.md`;
- `docs/tasks/MAP_KRAYIN_CRM.md`;
- `docs/tasks/MAP_RYZEAPI.md`;
- `docs/CHANGELOG_AI.md`.

**Resumo técnico**  
Os agentes passaram a operar em modo somente-leitura e a entregar evidências ao Codex principal, que permanece como único integrador e escritor de arquivos compartilhados. A skill Krayin registra stack, pacotes, autenticação, ACL, entidades, relações, superfícies sensíveis, pontos de extensão e zonas de risco confirmadas no fork. A skill RyzeAPI registra convenções, endpoints centrais, eventos, diferenças entre webhook e WebSocket, grupos, histórico e regras de segurança verificadas no `llms-full.txt` em 2026-07-14. O `AGENTS.md` agora exige Krayin, Ryze ou ambos conforme o domínio e define fallback explícito quando subagentes não estiverem disponíveis.

**Riscos e limites**  
- a RyzeAPI é externa e deve ser revalidada antes de cada decisão versionável;
- o upstream Krayin pode divergir deste fork, portanto o código local sempre prevalece;
- skills extensas foram divididas em referências para limitar consumo de contexto;
- especialistas somente-leitura não implementam isoladamente, reduzindo conflitos e mantendo a síntese no agente principal.

**Validação**  
- skills validadas com o `quick_validate.py` oficial;
- TOML dos agentes validado por parser;
- paths, nomes obrigatórios e links revisados estaticamente;
- `git diff --check` executado para os arquivos desta tarefa.

**Rollback**  
Reverter os arquivos listados e remover `.agents/skills/topwebcrm-krayin` e `.agents/skills/topwebcrm-ryzeapi`. Nenhum código de aplicação, migration, dependência ou banco foi alterado.

---

### [2026-07-16] Incorporação das skills e contextos oficiais do Krayin
**Status:** done

**Objetivo**  
Incorporar formalmente as skills oficiais do Krayin 2.2 e os snapshots locais `llms-full.txt` à cadeia obrigatória dos especialistas e aos mapas arquiteturais do TopwebCRM.

**Fontes verificadas**  
- `https://devdocs.krayincrm.com/2.2/introduction/skills.html`;
- `https://devdocs.krayincrm.com/llms-full.txt`;
- `https://github.com/krayin/laravel-crm/blob/2.2/AGENTS.md`;
- `.github/skills/crm-package-development/SKILL.md`;
- `.github/skills/pest-testing/SKILL.md`;
- `docs/krayincrm/llms-full.txt`;
- `docs/ryzeapi/llms-full.txt`.

**Arquivos alterados**  
- `AGENTS.md`;
- `docs/AI_CONTEXT.md`;
- `docs/ARCHITECTURE.md`;
- `docs/krayincrm/REFERENCE_POLICY.md`;
- `.codex/agents/krayincrm-specialist.toml`;
- `.codex/agents/ryzeapi-specialist.toml`;
- `.agents/skills/topwebcrm-krayin/SKILL.md`;
- `.agents/skills/topwebcrm-krayin/references/architecture-map.md`;
- `.agents/skills/topwebcrm-ryzeapi/SKILL.md`;
- `.agents/skills/topwebcrm-ryzeapi/references/api-map.md`;
- `docs/tasks/MAP_KRAYIN_CRM.md`;
- `docs/tasks/MAP_RYZEAPI.md`;
- `docs/CODEX_MULTIAGENTS.md`;
- `docs/CHANGELOG_AI.md`.

**Resumo técnico**  
O especialista Krayin agora deve combinar a skill específica do fork com as skills oficiais `crm-package-development` e `pest-testing`, conforme o tipo de trabalho. O snapshot `docs/krayincrm/llms-full.txt` tornou-se referência local obrigatória por seção, com a URL canônica reservada para atualização e dúvidas versionáveis. A mesma política local-first foi aplicada ao RyzeAPI. Foi documentada a precedência entre código e documentação e registradas divergências conhecidas, incluindo Laravel 11 no contexto textual versus Laravel `^12.0` no fork, exemplos de middleware/config/ACL diferentes e a natureza opcional do pacote `krayin/rest-api`.

**Validação**  
- hashes SHA-256 das duas skills e do contexto Krayin locais idênticos às fontes oficiais;
- TOML e YAML validados;
- skills validadas com o validador oficial;
- referências e caminhos obrigatórios auditados;
- `git diff --check` executado.

**Impacto e rollback**  
Não houve mudança de runtime, dependência, migration ou banco. Para rollback, reverter os arquivos listados e remover `docs/krayincrm/REFERENCE_POLICY.md`; os snapshots oficiais podem permanecer sem efeito sobre a aplicação.

---

### [2026-07-16] Topweb Chat — domínio, ACL e UI operacional inicial
**Status:** in_progress

**Objetivo**  
Criar a extensão nativa `TopwebChat` sem alterar o domínio-base do Krayin, preparando persistência local, filas operacionais, notas internas, atribuição e integração futura com instâncias RyzeAPI previamente criadas.

**Arquivos lidos**  
- `AGENTS.md`;
- `docs/AI_CONTEXT.md`;
- `docs/ARCHITECTURE.md`;
- `docs/SECURITY_RULES.md`;
- `docs/PRODUCT_RULES.md`;
- `docs/tasks/IMPLEMENT_WHATSAPP_INTEGRATION.md`;
- mapas Krayin, EvoCRM e RyzeAPI;
- snapshots `docs/krayincrm/llms-full.txt` e `docs/ryzeapi/llms-full.txt`;
- skills locais e oficiais aplicáveis.

**Arquivos alterados**  
- registros em `composer.json`, `bootstrap/providers.php` e `config/concord.php`;
- novo pacote `packages/Webkul/TopwebChat`;
- `packages/Webkul/User/src/Models/User.php`;
- `app/Services/SensitiveDataService.php`;
- `config/sensitive-data.php`;
- `.env.example`;
- `tests/Feature/SensitiveDataVisibilityTest.php`;
- `tests/Feature/TopwebChatAccessTest.php`;
- `docs/TOPWEB_CHAT_ARCHITECTURE.md`;
- `docs/BRANDING.md`;
- `docs/SENSITIVE_DATA_VISIBILITY.md`;
- `docs/tasks/IMPLEMENT_WHATSAPP_INTEGRATION.md`.

**Resumo técnico**  
O pacote adiciona entidades de instância, conversa, mensagem, nota interna e evento de webhook com contratos Concord, proxies, repositories, migrations e campos externos criptografados. A UI possui filas “meus atendimentos”, “sem atendente” e “todos” para administradores, visualização do histórico persistido, notas internas e atribuição. Operadores podem assumir somente conversas sem responsável; administradores podem atribuir ou reatribuir qualquer conversa. Configurações e concessões individuais são bloqueadas no controller para roles que não usam `permission_type=all`. A antiga capacidade `sensitive_data.view` foi desativada: dados integrais dependem exclusivamente de `users.can_view_sensitive_data`, inclusive para administradores.

**Impacto esperado**  
O menu aparece somente para usuários com ACL do Topweb Chat. Conversas sem atendente ficam disponíveis em fila separada, conversas atribuídas ficam restritas ao operador responsável e dados estruturados permanecem mascarados salvo exceção individual explícita.

**Riscos**  
- a migration ainda não foi aplicada ao banco;
- envio, recebimento, webhook, jobs, mídia e status externos ainda não existem;
- texto livre de mensagens e notas não sofre redação, conforme decisão de produto;
- o `composer dump-autoload` otimizado é lento no bind mount do Docker Desktop e deve ser executado na preparação da imagem ou em filesystem Linux;
- payloads externos futuros exigem normalização e sanitização antes da persistência e dos logs.

**Testes executados/recomendados**  
- lint PHP aprovado para todos os arquivos do pacote e testes;
- Pint aprovado em 41 arquivos, com uma correção automática;
- 13 testes e 43 asserções aprovados;
- 8 rotas do módulo registradas;
- migration validada com `php artisan migrate --pretend`, sem alteração do banco;
- autoload otimizado regenerado com 9.987 classes;
- package discovery e compilação das views concluídos;
- `git diff --check` aprovado após o registro documental final.

**Rollback**  
Remover o provider, módulo Concord, namespace PSR-4 e pacote `Webkul/TopwebChat`; reverter o campo individual no model e a mudança do serviço de dados sensíveis. Se a migration tiver sido aplicada, executar rollback controlado antes de remover o pacote.

**Pendências**  
- implementar provider contract e adapter RyzeAPI;
- autenticar e processar webhook por jobs idempotentes;
- implementar envio e recebimento reais;
- tratar mídia privada e status de entrega;
- adicionar auditoria das mudanças de atribuição e concessão sensível;
- executar migration e validação visual somente em ambiente autorizado.

---

### [2026-07-16] Aplicação da migration do Topweb Chat
**Status:** done

**Objetivo**  
Corrigir a falha `SQLSTATE[42S02]` ao acessar o Topweb Chat no ambiente Docker local.

**Resumo técnico**  
A migration `2026_07_16_000000_create_topweb_chat_tables` estava registrada como pendente porque a etapa anterior havia executado apenas `migrate --pretend`. Foi aplicado `php artisan migrate --force` no container ativo, criando as cinco tabelas `topweb_chat_*` e a coluna `users.can_view_sensitive_data`.

**Validação**  
- migration registrada no batch 2;
- cinco tabelas confirmadas diretamente no MySQL;
- coluna individual confirmada com default `0`;
- consulta da fila `mine` executada pelo repository e retornou `0`;
- rota `/admin/topweb-chat` respondeu e redirecionou corretamente ao login para sessão anônima.

**Rollback**  
Se necessário, executar rollback controlado da migration antes de remover o pacote. O rollback elimina as tabelas do Topweb Chat e a coluna de concessão individual, portanto não deve ser usado após existirem conversas ou configurações reais sem backup.
---

### [2026-07-16] Topweb Chat — abertura pelo CRM, envio, webhook e funil
**Status:** done

**Objetivo**  
Completar o primeiro fluxo útil de atendimento: iniciar conversa pela Pessoa ou Lead, enviar e receber mensagens, acompanhar status e mover o Lead no pipeline sem expor dados sensíveis.

**Resumo técnico**  
Foram adicionados o contrato de mensageria e o adapter RyzeAPI, normalização de telefone/JID, resolução de contato, criação/reabertura de conversa, persistência de saída antes da chamada externa, webhook autenticado e idempotente, job de processamento, status de entrega e estado da instância. Os cards de Pessoa e Lead recebem o botão **Enviar WhatsApp** pelos eventos nativos do Krayin. A conversa vinculada a Lead permite alterar sua etapa usando o repository e os eventos de atualização do domínio.

A ACL foi separada em visualizar, iniciar, enviar, anotar, atribuir e alterar etapa. O telefone permanece mascarado na UI, enquanto o backend autorizado usa o valor salvo. A interface foi migrada para traduções `en` e `pt_BR`; novos locales podem ser adicionados no diretório de idioma do pacote.

**Configuração operacional**  
O recebimento exige `TOPWEB_CHAT_PUBLIC_URL` com HTTPS público e a ação **Configurar webhook** na tela administrativa. `localhost` não é alcançável pela RyzeAPI. Mensagens anteriores à ativação do webhook não são retroativamente importadas.

**Validação**  
- 14 rotas do módulo registradas;
- lint PHP aprovado para todos os arquivos do pacote;
- testes específicos de acesso e provider aprovados;
- payload e autenticação do envio validados com `Http::fake`;
- identidade canônica, URL pública e idempotência dos status cobertas por teste;
- documentação atualizada em `docs/TOPWEB_CHAT_ARCHITECTURE.md` e `docs/TOPWEB_CHAT_OPERATIONS.md`.

**Riscos residuais**  
- busca inicial de Pessoa por telefone percorre registros e deve ganhar índice de identidade em escala;
- mídia ainda não possui download privado autorizado;
- histórico anterior ao webhook não é sincronizado;
- produção deve usar fila persistente em vez de `sync`;
- a entrega externa real depende de URL pública e credencial RyzeAPI válidas.

**Rollback**  
Reverter controllers, services, rotas, ACL, templates de extensão e configuração de webhook deste incremento. Não há migration nova neste conjunto.
