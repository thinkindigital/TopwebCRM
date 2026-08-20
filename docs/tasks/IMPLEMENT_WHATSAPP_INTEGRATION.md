## Objetivo
Implementar no TopwebCRM um módulo de integração WhatsApp com foco em uso operacional interno, histórico conversacional por entidade do CRM e desacoplamento do provedor.

**Provedor Primário**: OpenWA (https://github.com/rmyndharis/OpenWA) — self-hosted, 100% open source, multi-sessão, HMAC webhooks, API Key auth, Docker/PostgreSQL/Redis/S3, Swagger docs. Deploy em KVM próprio.
**Provedor Futuro**: Evolution API (mesmo contrato `MessagingProvider`)

---

## Decisões Confirmadas

- Persistir localmente conversas, mensagens, notas internas, atribuições e status.
- Integrar instâncias OpenWA previamente criadas (session_name, base_url, api_key, webhook_secret).
- Conceder visualização integral de dados sensíveis individualmente por usuário (`users.can_view_sensitive_data`), negação por padrão.
- Não redigir automaticamente texto livre de mensagens ou notas.
- Exibir conversas sem atendente em fila separada para todos os operadores autorizados.
- Atribuir a conversa ao operador na tomada ou primeira resposta.
- Permitir que administradores atribuam e reatribuam livremente.
- Exibir notas internas a todos os operadores com acesso à conversa.
- Usar `TopwebChat`, label **Topweb Chat** e prefixo `TOPWEB_CHAT_`.
- Arquitetura provider-agnostic: `MessagingProvider` + adapters concretos (`OpenWaProvider`, `EvolutionProvider`)

O desenho consolidado está em `docs/TOPWEB_CHAT_ARCHITECTURE.md` e `docs/adr/0004-topwebchat-whatsapp-module.md`.

---

## Estado Atual (Core Implementado)

O pacote `packages/Webkul/TopwebChat` já implementa:
- Instância OpenWA cadastrável (provider=`openwa`)
- Webhook HMAC autenticado e idempotente
- Envio de texto em job assíncrono com outbox local (`operation_key`)
- Recebimento via webhook: `message.received`, `message.sent`, `message.status`, `session.status`, `message.edited`, `message.reacted`, `chat.created`
- Abertura/reutilização de conversa por Pessoa ou Lead
- Atribuição, filas ("meus", "sem atendente", "todos" p/ admin), notas internas
- Alteração de etapa do Lead pela conversa
- ACL granular + concessão individual de dados sensíveis
- Traduções `en`/`pt_BR`

**Pendências Explícitas (Epic E06):**
- Reconciliação automática de drift (webhook ↔ estado local OpenWA)
- Quarentena para identidade inbound ambígua
- Mídia privada: download server-side via OpenWA, validação, `storage/app/private`, URLs autorizadas
- Interativos: reações, edição, botões, listas
- Domínio Grupos (ACL + auditoria própria)
- Domínio PIX/Chamadas/Canais (domínios separados)

---

## Atualização Operacional

- Webhook persistente é canal autoritativo para conversas novas; histórico REST (`GET /api/v1/sessions/{session}/messages`) só para catch-up de conversas conhecidas
- Estado de instância, histórico e leitura reconciliados por jobs Redis (scheduler)
- Localhost sem túnel não recebe inbound desconhecido (Cloudflare Tunnel para dev)
- Grupos, LIDs, newsletters sem correlação segura não criam Pessoas automaticamente
- Recursos avançados adiados até normalização, identidade e mídia privada consolidadas

---

## Progresso da Implementação

- ✅ Concluído: pacote nativo, domínio base, persistência, ACL, filas, atribuição, notas, cadastro instância OpenWA, exceção individual dados sensíveis
- ✅ Validado: rotas registradas, SQL migration `--pretend`, lint, Pint, testes acesso/provider
- ✅ Concluído: contrato `MessagingProvider`, `OpenWaProvider`, webhook HMAC, job entrada, envio texto enfileirado, recebimento, status monotônico
- 🔄 Pendente (E06): reconciliação automática, quarentena identidade, mídia privada, interativos, grupos, PIX

---

## Instrução Principal
Não implementar integração "direta na tela" nem acoplamento forte com provider.

A solução deve ser módulo extensível, seguro e rastreável, com separação clara entre:
1. Domínio de conversa/mensagem
2. Integração com provider (adapter)
3. Persistência
4. Processamento assíncrono (jobs)
5. Interface do CRM

---

## Leitura Obrigatória Antes de Agir
Leia nesta ordem:
1. `AGENTS.md`
2. `CONTEXT.md`
3. `docs/ARCHITECTURE.md`
4. `docs/SECURITY_RULES.md`
5. `docs/PRODUCT_RULES.md`
6. `docs/adr/0004-topwebchat-whatsapp-module.md`
7. `docs/TOPWEB_CHAT_OPENWA_MAP.md`
8. `docs/TOPWEB_CHAT_OPERATIONS.md`
9. Documentação local do módulo afetado

Se algum arquivo não existir, declarar explicitamente antes de prosseguir.

---

## Objetivo Funcional de Produto
O usuário do CRM deve conseguir:
- Visualizar conversas relacionadas a uma entidade do CRM
- Identificar rapidamente o contato ligado à conversa
- Enviar mensagens a partir do CRM
- Receber mensagens via webhook do provider
- Consultar histórico e status
- Operar sem sair do sistema
- Integrar com funil do sistema
- Respeitar regras de perfil e visibilidade

---

## Resultado Funcional Esperado

### Admin
- Configurar integração (instâncias OpenWA), inspecionar falhas, visualizar histórico integral quando permitido

### Usuário Operacional Autorizado
- Operar conversas e responder mensagens dentro do escopo permitido

### Usuário Sem Autorização
- Não acessar telas, payloads, histórico ou dados do módulo além do explicitamente permitido

---

## Premissas Obrigatórias
1. WhatsApp integrado = recurso nativo operacional do CRM
2. Provider externo abstraído por adapter/service (`MessagingProvider`)
3. Integração suporta troca futura de provider (OpenWA ↔ Evolution)
4. Conversas vinculadas a entidades reais do CRM (Pessoa obrigatória, Lead opcional)
5. Fluxo prevê webhook entrada + envio saída
6. Filas/jobs para eventos assíncronos
7. Logs úteis sem vazar segredos/dados sensíveis

---

## Tarefa em Fases

### Fase 1 — Análise Arquitetural (concluída para core)
Entregar: onde Krayin estender, entidades CRM ↔ conversas, arquivos reais, melhor local módulo, estratégia provider adapter, superfícies UI, eventos assíncronos, riscos acoplamento/regressão.

### Fase 2 — Desenho do Domínio (concluído para core)
Estruturas: Conversa, Mensagem, Participante/Contato, Canal/Provider, Status, WebhookEvent, Falha/Retry, Anexo/Mídia, Vínculo CRM.

### Fase 3 — Persistência (concluída para core)
Migrations, models, repositories para: Conversation, Message, Instance, InternalNote, WebhookEvent. Relacionamentos: Conversation→Instance, Person, Lead, Assignee; Message→Conversation.

### Fase 4 — Adapter/Provider Layer (OpenWaProvider em desenvolvimento)
- Contrato `MessagingProvider` (interface)
- `OpenWaProvider` implementando: auth (API Key), sendText, sendMedia, webhook HMAC validation, session status, history, markRead
- Config por instância: `base_url`, `api_key`, `webhook_secret`, `session_name`
- Factory/selector por `Instance.provider`
- Tratamento falha, logs estruturados, rate limit respect

### Fase 5 — Entrada por Webhook (implementado core)
- Validação HMAC SHA256 (`X-Webhook-Signature`)
- Parsing + normalização `remote_jid`
- Identificação contato ou vínculo pendente (quarentena E06)
- Persistência `Message` inbound + `WebhookEvent` idempotente
- Atualização status/metadata + despacho jobs

### Fase 6 — Envio de Mensagens (implementado core texto)
- Seleção entidade/conversa → `MessageService` → Job `SendMessage`
- `OpenWaProvider::sendText()` → `POST /api/v1/sessions/{session}/messages/text`
- Persistência tentativa + `operation_key` + `provider_message_id`
- Atualização status via webhook `message.status` (monotônico)
- Tratamento falha: `unknown` sem retry cego; rate limit backoff

### Fase 7 — Interface (implementado core)
- Listar conversas (filas), abrir conversa, histórico (polling 5s), responder, ver status, vínculo Pessoa/Lead, indicar falhas

### Fase 8 — Autorização e Segurança (implementado core)
- ACL granular (`topweb_chat.*`)
- Concessão individual `can_view_sensitive_data`
- Tokens/secrets criptografados, nunca no navegador
- Mídia futura: `SensitiveFileService` + disco privado

### Fase 9 — Revisão Final (por Epic)
- Acoplamento excessivo, duplicação, vazamento dados, webhook inseguro, segredo exposto, falha associação, regressão

### Fase 10 — Documentação
- `docs/CHANGELOG_AI.md`
- `docs/adr/0004-topwebchat-whatsapp-module.md` (se decisão irreversível)
- Task doc se entendimento evoluir

---

## Requisitos Funcionais Mínimos (Core ✅)
1. Vincular conversa a contato/lead/cliente
2. Receber mensagem entrada (webhook HMAC)
3. Persistir mensagem
4. Exibir histórico
5. Enviar resposta (texto)
6. Atualizar status básico (monotônico)
7. Operar com provider inicial (OpenWA)
8. Manter expansão futura (Evolution API)

---

## Requisitos Técnicos Mínimos
- Migrations, Models/Entidades, Services/Adapters, Config/Env
- Controllers/Handlers/Webhooks, Jobs/Queues
- UI mínima operacional, Autorização, Logs, Tratamento erro, Documentação

---

## Provider Strategy (Multi-Provider Ready)
**Regra**: Nunca colocar lógica específica de OpenWA ou Evolution API espalhada pelo código do CRM.

**Responsabilidades**:
- Camada aplicação: decide o que enviar (`MessageService`)
- Adapter/Provider: sabe como enviar naquele provedor (`OpenWaProvider`, `EvolutionProvider`)
- Normalizer: transforma payload externo em padrão interno (`WebhookProcessor`)
- Persistência: armazena padrão interno (`Message`, `Conversation`)

---

## OpenWA — Endpoints Chave (Referência)

| Operação | Endpoint | Auth |
|----------|----------|------|
| Send Text | `POST /api/v1/sessions/{session}/messages/text` | `X-API-Key` |
| Send Media | `POST /api/v1/sessions/{session}/messages/media` | `X-API-Key` |
| Session List | `GET /api/v1/sessions` | `X-API-Key` |
| History | `GET /api/v1/sessions/{session}/messages` | `X-API-Key` |
| Mark Read | `POST /api/v1/sessions/{session}/chats/{chatId}/read` | `X-API-Key` |
| React | `POST /api/v1/sessions/{session}/messages/{messageId}/react` | `X-API-Key` |
| Edit | `POST /api/v1/sessions/{session}/messages/{messageId}/edit` | `X-API-Key` |
| Groups | `GET/POST /api/v1/sessions/{session}/groups` | `X-API-Key` |

**Webhook Events** (configurar no OpenWA):
`message.received`, `message.sent`, `message.status`, `session.status`, `message.edited`, `message.reacted`, `chat.created`, `call.received`

**HMAC**: Header `X-Webhook-Signature` = SHA256(payload, webhook_secret)

---

## Dados Sensíveis do Módulo
Considerar sensíveis: telefone completo, conteúdo mensagem (quando aplicável), anexos, identificadores externos, tokens/credenciais, payloads brutos, status/metadados entrega, associação conversa↔cliente.

Obedecer: `docs/SECURITY_RULES.md`, `docs/PRODUCT_RULES.md`, `docs/adr/0003-sensitive-data-visibility-by-role.md`

---

## Restrições Obrigatórias
- Não acoplar provider direto em controller UI
- Não salvar segredo em código-fonte
- Não logar token/segredo completo
- Não fazer tela "bonita" antes de resolver domínio/persistência
- Não implementar só envio sem recebimento
- Não implementar só recebimento sem vínculo entidade
- Não ignorar status/falhas
- Não criar arquitetura grande demais para primeira versão
- Não refatorar partes amplas do Krayin sem necessidade

---

## Entregáveis Obrigatórios (por etapa)
1. Objetivo da etapa
2. Arquivos/documentos lidos
3. Arquivos inspecionados
4. Arquivos alterados
5. Arquitetura adotada
6. Fluxo implementado
7. Riscos
8. Testes recomendados/executados
9. Pendências
10. Atualização no changelog

---

## Critério de Aceite
Tarefa concluída se:
- Base real domínio conversas/mensagens
- Provider desacoplado (`MessagingProvider`)
- Fluxo mínimo entrada (webhook HMAC) + saída (job + adapter)
- Conversa vinculada a entidade CRM
- Controle de acesso (ACL + concessão individual)
- Sem exposição óbvia segredos/payloads indevidos
- Documentado

---

## Ordem Recomendada Pós-Core (Epic E06+)
1. Reconciliação automática drift webhook (OpenWA)
2. Quarentena identidade ambígua (fila admin)
3. Mídia privada (download server-side, validação, disco privado)
4. Interativos: reações, edição, botões, listas
5. Grupos (domínio separado, ACL, auditoria)
6. PIX/Chamadas/Canais (domínios separados)
7. Auditoria trilha operacional (E07)
8. Multi-provider Evolution API (E09)