# ORCHESTRATOR-ROADMAP.md

Bússola estratégica do TopwebCRM. Epics com IDs estáveis (E##) e milestones entregáveis.

---

## Epics

### **[E01] Fundação Documental e Governança** `done`
- **Objetivo**: Estabelecer base documental obrigatória (AGENTS.md, AI_CONTEXT.md, ARCHITECTURE.md, SECURITY_RULES.md, PRODUCT_RULES.md, CONTEXT.md, ADRs, docs/agents/)
- **Critérios de sucesso**: Todos os docs obrigatórios existem, glossário consolidado em CONTEXT.md, ADRs para decisões-chave, skills mapeadas
- **Status**: done
- **Milestones**:
  - [x] Docs obrigatórias criadas/atualizadas
  - [x] CONTEXT.md com glossário único
  - [x] 6 ADRs documentando decisões arquiteturais
  - [x] docs/agents/ configurado (issue-tracker, triage-labels, domain)
  - [x] AGENTS.md com seção de skills

---

### **[E02] Visibilidade de Dados Sensíveis por Perfil** `done`
- **Objetivo**: Centralizar decisão de visibilidade (SensitiveDataService + permissão `sensitive_data.view` + concessão individual `users.can_view_sensitive_data`), cobrir todas as superfícies
- **Critérios de sucesso**: Admins/perfis com permissão veem integral; usuários comuns veem mascarado/oculto; coberto em listagens, detalhes, formulários, APIs, Resources, busca, autocomplete, filtros, export, relatórios, anexos (disco privado + rotas autorizadas), migração legada idempotente
- **Status**: done
- **Milestones**:
  - [x] Configuração declarativa `config/sensitive-data.php`
  - [x] Serviço central `SensitiveDataService` + `SensitiveFileService`
  - [x] Permissão na árvore ACL + concessão individual no User
  - [x] Resources, DataGrids, formulários, busca, export, dashboard, atividades, anexos, cotações ajustados
  - [x] Comando `sensitive-data:migrate-attachments` (dry-run + execução)
  - [x] Testes Pest (10 testes, 34 asserções) + Pint + Blade cache + healthcheck

---

### **[E03] Módulo WhatsApp Nativo (TopwebChat) — Core** `done`
- **Objetivo**: Pacote `packages/Webkul/TopwebChat` com domínio base, persistência, ACL, UI operacional, provider adapter **OpenWA** (primário) / Evolution API (futuro)
- **Critérios de sucesso**: Instância OpenWA cadastrável (session_name, base_url, api_key, webhook_secret HMAC); webhook HMAC idempotente; envio texto em job com outbox local (`operation_key`); recebimento via webhook; abertura/reutilização conversa por Pessoa/Lead; atribuição (fila "meus", "sem atendente", "todos" p/ admin); notas internas; alteração etapa Lead; concessão individual dados sensíveis; traduções en/pt_BR
- **Status**: done
- **Milestones**:
  - [x] Entidades: Instance, Conversation, Message, InternalNote, WebhookEvent (Concord contracts + proxies + migrations)
  - [x] Provider: `MessagingProvider` + `OpenWaProvider` (adapter desacoplado, multi-provider ready)
  - [x] Webhook: autenticação HMAC SHA256, idempotência, job ProcessWebhookEvent → WebhookProcessor
  - [x] Envio: MessageController → ConversationAccessService → MessageService → Job SendMessage → Provider
  - [x] ACL: access, view, start, send, note, assign, change_stage
  - [x] UI: filas, timeline polling 5s, status monotônico, botões Pessoa/Lead via eventos nativos
  - [x] Migration aplicada, rotas registradas, testes acesso/provider passando

---

### **[E04] Infraestrutura de Produção (Setup Orion)** `done`
- **Objetivo**: Stack Docker Swarm parametrizada com imagem imutável, secrets externos, healthchecks, isolamento
- **Critérios de sucesso**: `Dockerfile.production` (código, deps, assets, Apache 8080, www-data, healthcheck); `compose.production.yaml` (app, worker, scheduler, MySQL, Redis, secrets, rede Traefik, volumes estáveis); build validado, healthcheck HTTP 200, ausência .env/docs/testes na imagem
- **Status**: done
- **Milestones**:
  - [x] Dockerfile.production + auxiliares + .dockerignore
  - [x] compose.production.yaml parametrizado (imagem, domínio, rede, SMTP)
  - [x] Secrets externos, volumes com placement fixo
  - [x] Migrations separadas por fase, espera ativa DB, healthchecks
  - [x] Imagem `topwebcrm:validation` buildada, healthy, respondendo /up

---

### **[E05] Confiabilidade Operacional do Chat (Resiliência OpenWA)** `done`
- **Objetivo**: Reconciliação instância/histórico, catch-up assíncrono, status monotônico, tratamento rate limit, proteção concorrência
- **Critérios de sucesso**: Reconciliação manual/agendada instância; catch-up histórico conhecido paginado (from/to/hasMore/cursor); leitura remota assíncrona; status monotônico (delivered/read/played não rebaixam para failed); rate limit OpenWA respeita `Retry-After` + teto configurável; locks transacionais em atribuição/associação/ingestão; contadores inbound monotônicos; preservação notas internas; ingestão webhook atômica
- **Status**: done
- **Milestones**:
  - [x] Comando `topweb-chat:reconcile --history` + scheduler (estado 1min, histórico 5min/20 conv)
  - [x] Outbox local com operation_key, tentativa única, unknown sem retry cego
  - [x] Timeline JSON sanitizado polling 5s
  - [x] 25 testes contratos/ACL/dados sensíveis (79 asserções)
  - [x] Documentação: TOPWEB_CHAT_OPENWA_MAP.md, TOPWEB_CHAT_OPERATIONS.md, TOPWEB_CHAT_ARCHITECTURE.md

---

### **[E06] Reconciliação Completa e Domínios Pendentes** `todo` [[#1](https://github.com/thinkindigital/TopwebCRM/issues/1)]
- **Objetivo**: Completar gaps operacionais do chat
- **Critérios de sucesso**: Reconciliação automática drift webhook; fila administrativa identidade ambígua (quarentena); mídia privada (download server-side, validação, disco privado); respostas/botões/listas normalizadas; domínio Grupos (ACL + auditoria própria); PIX (ACL + auditoria financeira); LIDs/Newsletters
- **Status**: todo
- **Milestones**:
  - [ ] Job reconciliação automática webhook drift
  - [ ] Fila admin para identidade inbound ambígua (não criar Pessoa silenciosamente)
  - [ ] Mídia: download privado + validação + armazenamento disco privado + URLs autorizadas
  - [ ] Interativos: botões, listas, respostas com normalização retorno
  - [ ] Grupos: domínio separado com ACL + auditoria
  - [ ] PIX: domínio separado com ACL + auditoria financeira

---

### **[E07] Auditoria e Trilha Operacional** `todo` [[#2](https://github.com/thinkindigital/TopwebCRM/issues/2)]
- **Objetivo**: Trilha de auditoria para ações sensíveis (visualização dados sensíveis, exportação, alteração permissões, mudança integração, ações mensageria)
- **Critérios de sucesso**: Log estruturado imutável; quem/quando/o que/entidade/impacto; consulta/filtro por admin; retenção configurável
- **Status**: todo
- **Milestones**:
  - [ ] Modelo/entidade AuditLog
  - [ ] Listeners/observers nos pontos críticos (SensitiveDataService, export, ACL, TopwebChat)
  - [ ] UI admin para consulta/filtro
  - [ ] Testes de cobertura

---

### **[E08] Melhorias de UX Operacional** `todo` [[#3](https://github.com/thinkindigital/TopwebCRM/issues/3)]
- **Objetivo**: Reduzir atrito interno (busca, timeline, Kanban, dashboard, autocomplete)
- **Critérios de sucesso**: Busca global unificada respeitando sensibilidade; timeline conversacional em contexto (Lead/Person/Org); Kanban Lead com drag-drop estável; dashboard com métricas relevantes; autocomplete server-side com autorização
- **Status**: todo
- **Milestones**:
  - [ ] Busca global (meilisearch ou DB otimizado) com autorização
  - [ ] Timeline unificada (Atividades + Emails + Chat + Notas)
  - [ ] Kanban Lead estável (persistência ordem, validação transições)
  - [ ] Dashboard operacional (SLA chat, conversas abertas, leads por etapa)

---

### **[E09] Multi-provider e Evolution API** `todo` [[#4](https://github.com/thinkindigital/TopwebCRM/issues/4)]
- **Objetivo**: Suporte a Evolution API como provider alternativo/intercambiável
- **Critérios de sucesso**: Adapter `EvolutionApiProvider` implementando `MessagingProvider`; config por env; testes de contrato; documentação migração
- **Status**: todo
- **Milestones**:
  - [ ] Adapter Evolution API (autenticação, envio texto/mídia, webhook, instâncias)
  - [ ] Factory/selector de provider por instância
  - [ ] Testes de contrato compartilhados
  - [ ] Docs de migração RyzeAPI ↔ Evolution

---

## Milestones Agregados

| Milestone | Epics | Target |
|-----------|-------|--------|
| **M1: Fundação Sólida** | E01, E02, E03, E04, E05 | Concluído |
| **M2: Completude Operacional Chat** | E06 | Próximo |
| **M3: Governança e Auditoria** | E07 | Pós-M2 |
| **M4: Experiência Operacional** | E08 | Contínuo |
| **M5: Ecossistema Multi-provider** | E09 | Futuro |

---

## Próximas Ações Imediatas (E06)

1. **Reconciliação automática webhook drift** — Job que detecta divergência entre estado local e OpenWA (instância, mensagens unknown, conversas órfãs)
2. **Quarentena identidade ambígua** — Fila admin para inbound sem vínculo seguro (múltiplas Pessoas ou nenhuma); revisão humana antes de criar/associar
3. **Mídia privada** — Download server-side via OpenWA (`GET /api/v1/sessions/{session}/messages/{id}/media`), validação tipo/tamanho, armazenamento `storage/app/private`, rota autorizada para download

---

## Notas

- Epics E01–E05 já entregues e validados (ver `docs/CHANGELOG_AI.md` para evidências: testes, lint, build, healthcheck, migrações)
- Este roadmap vive localmente; para rastreabilidade em equipe, publicar cada Epic como Issue no GitHub (usar `/to-issues`)
- **Issues GitHub criadas**: E06 [#1](https://github.com/thinkindigital/TopwebCRM/issues/1), E07 [#2](https://github.com/thinkindigital/TopwebCRM/issues/2), E08 [#3](https://github.com/thinkindigital/TopwebCRM/issues/3), E09 [#4](https://github.com/thinkindigital/TopwebCRM/issues/4)
- IDs E## são estáveis — não renumerar. Novos Epics usam próximo número disponível.
- **Provedor WhatsApp**: OpenWA (primário, self-hosted) → Evolution API (futuro). RyzeAPI descontinuada (paga, sem acesso teste).