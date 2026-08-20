Não faça refatoração estética. Não renomeie sem necessidade. Não reorganize estrutura inteira. Foque só no objetivo.

# ARCHITECTURE.md — Princípios e Padrões (Resumo)

> **Fontes autoritativas:** `CONTEXT.md` (glossário), `docs/adr/0001–0006` (decisões), `docs/krayincrm/llms-full.txt` (Krayin internals)

---

## Finalidade

Orientar mudanças arquiteturais no TopwebCRM. Não inventa arquitetura — registra padrões confirmados no código e decisões documentadas.

---

## Contexto do Fork

| Item | Definição |
|------|-----------|
| **Base Principal** | Krayin CRM v2.2 — núcleo operacional estável |
| **Referência Comparativa** | Evo CRM Community — benchmark funcional/arquitetural (não base de código) |
| **Modelo Alvo** | CRM com núcleo estável, regras centralizadas, auth consistente, dados seguros, integrações desacopladas, histórico rastreável, baixo acoplamento, filas/eventos preparados |

---

## Precedência de Fontes (Krayin)

1. Código, migrations, config do TopwebCRM
2. `docs/` obrigatórios + tasks
3. `docs/krayincrm/llms-full.txt` (consultar por seção)
4. Documentação online v2.2 / upstream

> Policy detalhada: `docs/krayincrm/REFERENCE_POLICY.md`  
> Divergências conhecidas: Laravel 12 no fork vs 11 no snapshot; middleware/ACL diferentes; REST API opcional (`krayin/rest-api`)

---

## Camadas de Mapeamento (Checklist)

Ao analisar qualquer módulo Krayin, localizar:

| Camada | O que Procurar |
|--------|----------------|
| **Entrada** | Rotas web/API, middlewares, request validation, policies/gates |
| **Aplicação** | Controllers, actions, services, repositories, jobs, listeners, observers, events, commands |
| **Domínio** | Entities, models, business rules, relationships, traits, states/transitions |
| **Persistência** | Migrations, seeders, factories, queries, scopes, repositories concretos, contracts |
| **Apresentação** | Blades/views, componentes, DataGrids, formulários, Resources/Transformers |
| **Integração** | Webhooks, HTTP clients, providers, notificações, messaging, config/env, queues/workers |

---

## Padrões de Evolução (Preferenciais)

| Área | Preferir | Evitar |
|------|----------|--------|
| **Autorização** | Policies, gates, middlewares, auth services, scopes controlados, resources seguros | `if role==admin` duplicado, checagem só em view/controller/query |
| **Dados Sensíveis** | Classificação formal, serviço central (`SensitiveDataService`), transformação na saída, bloqueio backend, controle export/busca | Esconder só no frontend, mascarar visual, valor íntegro em API |
| **Integrações Externas** | Adapters, contratos claros, config/env, filas assíncronas, failure/retry, logs estruturados | Chamadas diretas no código, UI acoplada a API externa, lógica provedor no controller |
| **Novos Módulos** | Isolamento por responsabilidade, nomes explícitos, baixo acoplamento, docs do módulo, eventos cross-context | Regras novas em arquivos caóticos, mistura UI+regra+integração |

---

## Extensão Segura (Pontos Confirmados)

| Objetivo | Ponto de Extensão |
|----------|-------------------|
| Novo domínio | Pacote `packages/Webkul/<Nome>` (Concord: contracts, proxies, providers, repositories) |
| Ações em telas Admin | Eventos `view_render_event` (não copiar views) |
| Integração externa | `MessagingProvider` + adapter concreto + service + job; controller só valida/coordena |
| Dados sensíveis | `SensitiveDataService` na saída + auth backend; **nunca** só máscara visual |
| Arquivos sensíveis | `SensitiveFileService` + disco `private` (`storage/app/private`) |

---

## Zonas de Risco (Alto Impacto)

| Arquivo/Área | Risco |
|--------------|-------|
| `bootstrap/providers.php`, `config/concord.php`, `composer.json` | Erro impede bootstrap do módulo |
| Bouncer, ACL, DataGrid, Resources | Afetam múltiplas telas, APIs, exports |
| Atributos EAV / JSON de contato | Filtros/updates ingênuos apagam/revelam dados |
| Migrations de pacote | Não controlar colunas globais de outro domínio |
| Eventos Blade | Recebem models crus → reautorizar/sanitizar em extensões |
| `storage/app/private`, `APP_KEY`, campos criptografados | Não perder entre releases |

---

## Módulo WhatsApp (TopwebChat)

**Arquitetura:** Provider-agnostic via `MessagingProvider`  
**Provedor Primário:** OpenWA (self-hosted, HMAC webhooks, API Key)  
**Detalhes:** `docs/adr/0004` + `docs/TOPWEB_CHAT_ARCHITECTURE.md` + `docs/TOPWEB_CHAT_OPENWA_MAP.md` + `docs/TOPWEB_CHAT_OPERATIONS.md`

**Requisitos Mínimos:**
- Provedor desacoplado (adapter)
- Identificação confiável entidade ↔ conversa
- Persistência mensagens + status
- Associação Pessoa/Lead/Cliente
- Anexos/mídia (disco privado)
- Webhooks entrada + jobs processamento
- Failure/retry trail

**Regra Obrigatória:** Nunca acoplar provedor direto na UI — sempre service/adapter.

---

## Estratégia Evo CRM

Usar para responder: **"Como esse problema costuma ser resolvido em CRM moderno?"**  
**Não** para: "Como replicar exatamente esse sistema?"

Comparar: conversas, contatos, inboxes, mensageria, auth/roles, estrutura modular, integração canais, organização frontend/backend/processing.

---

## Modo de Análise Obrigatório (Antes de Alterar)

1. Identificar módulo/domínio envolvido
2. Localizar entrada → regra → persistência → saída
3. Listar **arquivos reais** afetados
4. Identificar permissão + impacto colateral
5. Propor ponto de extensão mais seguro
6. Definir testes
7. Implementar em etapas pequenas
8. Registrar em `docs/CHANGELOG_AI.md`

---

## Critérios de Aceite Arquitetural

Solução aceitável se: resolve o problema + não cria atalho inseguro + não duplica regra sensível + baixo acoplamento + compreensível manutenção futura + reversível + documentada.

---

## Resultado Esperado do Fork

TopwebCRM reconhecível como fork estável do Krayin, evoluído com: segurança madura, governança de dados, canais comunicação integrados, capacidade operacional ampliada, base técnica sustentável.