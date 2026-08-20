# TopwebCRM

Fork evoluído do Krayin CRM para uso interno com foco em robustez operacional, governança de dados, extensibilidade e integração WhatsApp.

## Language

**Fork**: TopwebCRM — nome do fork, da futura stack, da futura imagem Docker e referência interna para branding técnico.
_Avoid_: Projeto, sistema, aplicação

**Base Principal**: Krayin CRM (krayin/laravel-crm v2.2) — núcleo operacional estável do CRM.
_Avoid_: Upstream, original

**Referência Comparativa**: Evo CRM Community — benchmarking funcional e arquitetural, não base de código.
_Avoid_: Modelo, template

**Entidade Comercial**: Lead, Pessoa (Person), Organização (Organization) — entidades centrais do domínio CRM.
_Avoid_: Cliente, contato, conta (termos ambíguos)

**Pessoa (Person)**: Indivíduo com telefones e e-mails em arrays JSON, pode pertencer a Organização e ter Leads, Atividades, Tags.
_Avoid_: Contato, usuário

**Organização (Organization)**: Empresa que possui Pessoas, pertence opcionalmente a um Usuário.
_Avoid_: Empresa, conta, cliente

**Lead (Lead/Oportunidade)**: Oportunidade comercial vinculada a Pessoa, Usuário, Pipeline e Etapa; pode ter Atividades, Produtos, E-mails, Cotações, Tags.
_Avoid_: Oportunidade, negócio, deal

**Pipeline**: Sequência de Etapas por onde o Lead percorre.
_Avoid_: Funil, fluxo

**Etapa (Stage)**: Estado do Lead no Pipeline.
_Avoid_: Status, fase

**Usuário (User)**: Conta de acesso ao painel administrativo, pertence a Role.
_Avoid_: Operador, atendente, admin

**Role (Perfil/Papel)**: Define permissões e escopo; pode ter `permission_type=all` (acesso total) ou permissões granulares via ACL.
_Avoid_: Perfil, grupo, nível

**Permissão (Permission)**: Ação autorizada (ex: `sensitive_data.view`, `topweb_chat.access`). Agrupadas em árvore ACL por módulo.
_Avoid_: Acesso, direito, regra

**Concessão Individual**: Campo `users.can_view_sensitive_data` — exceção pontual para visualização integral de dados sensíveis, independente da Role.
_Avoid_: Exceção, override, flag

**Dado Sensível**: Todo dado que exponha informação privada, permita contato indevido, tenha relevância financeira/estratégica, gere risco comercial/jurídico/operacional ou seja explorável por usuários fora do perfil adequado.
_Avoid_: Dado privado, informação confidencial

**Classes de Sensibilidade**:
- **Pública Interna**: Visível a usuários autenticados do domínio adequado (ex: nome comercial, estágio operacional)
- **Restrita**: Visível a perfis com permissão específica (ex: telefone, e-mail, documento, endereço, observações estratégicas)
- **Altamente Sensível**: Apenas administradores ou perfis explicitamente autorizados (ex: documento completo, telefone completo, e-mail completo, dados financeiros, credenciais, tokens, chaves de API, metadados sensíveis de conversação)

**Mascaramento**: Transformação padronizada de saída para operação parcial sem exposição integral (ex: telefone → últimos 2-4 dígitos, e-mail → parte local + domínio parcial, documento → trecho final).
_Avoid_: Ofuscação, ocultação visual

**Provedor de Mensageria (Messaging Provider)**: Abstração para integração WhatsApp (OpenWA, Evolution API, futuros). Implementa `MessagingProvider` + adapter concreto (`OpenWaProvider`, `EvolutionProvider`).
_Avoid_: Integração, API, conector

**Instância (Instance)**: Sessão WhatsApp previamente criada no provedor externo (ex: OpenWA), identificada por **`session_uuid`** (UUID retornado no `POST /api/sessions`), com credenciais (API Key, Webhook Secret HMAC, Base URL, Session Name) criptografadas no banco. Campo `provider` identifica o provedor (`openwa`, `evolution`).
_Avoid_: Conexão, sessão, conta WhatsApp

**Conversa (Conversation)**: Vincula Instância, identidade WhatsApp (criptografada/hash), Pessoa obrigatória, Lead opcional, Atendente opcional, fila e contadores.
_Avoid_: Chat, atendimento, thread

**Mensagem (Message)**: Direção (in/out), tipo (text/media), conteúdo, ID do provedor, status (queued/sent/delivered/read/failed/unknown), metadata criptografada.
_Avoid_: Msg, comunicação

**Identidade Remota (Remote Identity)**: Telefone/JID normalizado, armazenado criptografado; `remote_jid_key` como hash pesquisável. Não usa `Person.unique_id`.
_Avoid_: JID, número, contato WhatsApp

**Webhook Event**: Inbox idempotente do webhook (payload criptografado, estado de processamento). Eventos OpenWA: `message.received`, `message.sent`, `message.ack`, `message.failed`, `message.revoked`, `message.reaction`, `message.edited`, `session.status`, `session.authenticated`, `session.disconnected`, `session.reconnect_loop`, `session.restriction`, `group.join`, `group.leave`, `group.update`, `group.join_request`, `call.received`, `call.accepted`, `call.rejected`, `call.missed`, `status.received` (opt-in), `presence.update` (Baileys).
_Avoid_: Evento, callback, notificação

**Job (Job/Queue)**: Processamento assíncrono via Redis (envio, webhook, reconciliação, catch-up histórico, status instância).
_Avoid_: Task, worker, processo

**Outbox Local**: Persistência prévia da mensagem como `queued` com `operation_key` idempotente antes da chamada externa. Evita duplicidade por retry cego.
_Avoid_: Fila local, buffer

**Reconciliação**: Jobs agendados que sincronizam estado da instância, histórico conhecido e mensagens `unknown` com o provedor.
_Avoid_: Sincronização, sync

**Catch-up Histórico**: Paginação assíncrona do histórico REST com `from`, `to`, `hasMore` e cursor persistido para backfill.
_Avoid_: Backfill, importação histórica

**ACL (Access Control List)**: Árvore de permissões por módulo (ex: `topweb_chat.access`, `topweb_chat.send`, `topweb_chat.assign`). Resolvida por `Bouncer` + middleware.
_Avoid_: Permissão, regra de acesso

**Contrato (Contract)**: Interface Concord para entidades do pacote (ex: `ConversationContract`, `MessageContract`).
_Avoid_: Interface, modelo

**Proxy (Proxy/Concord)**: Classe que implementa o Contract e permite sobrescrita via configuração.
_Avoid_: Wrapper, delegate

**Repository (Repository)**: Fronteira preferida para persistência de regras de negócio (estende `Webkul\Core\Eloquent\Repository`).
_Avoid_: DAO, data access

**DataGrid**: Listagem, filtro, ordenação, exportação e importação de entidades no painel admin.
_Avoid_: Grid, tabela, listagem

**Resource (Resource/Transformer)**: Serialização JSON para APIs internas/AJAX (ex: `PersonResource`, `LeadResource`, `ConversationResource`).
_Avoid_: API resource, serializer, transformer

**Disco Privado (Private Disk)**: `storage/app/private` — armazenamento de anexos sensíveis (e-mail, atividade, futuro WhatsApp) com URLs resolvidas por rotas autorizadas.
_Avoid_: Storage privado, disco seguro

**Setup Orion**: Base operacional de produção (Docker Swarm, Portainer Business Edition, Traefik, ferramentas auxiliares). Rede Traefik confirmada; bancos/caches globais não reutilizados sem análise de isolamento.
_Avoid_: Infra, servidor, deploy

**Stack de Produção**: `compose.production.yaml` — app, worker, scheduler, MySQL, Redis, secrets externos, imagem imutável, healthcheck, usuário não-root.
_Avoid_: Deploy, compose, infra

**Imagem de Produção**: `docker/php/Dockerfile.production` — incorpora código, dependências Composer, assets compilados, Apache 8080, usuário `www-data`, healthcheck, storage restrito.
_Avoid_: Dockerfile, build, container

**Snapshot Oficial (llms-full.txt)**: Cópia verificada da documentação arquitetural oficial (Krayin: `docs/krayincrm/llms-full.txt`). Consultar por seção; revalidar URL canônica (`https://devdocs.krayincrm.com/llms-full.txt`) para decisões versionáveis.
_Avoid_: Docs oficial, documentação upstream

**OpenWA**: API WhatsApp self-hosted (https://github.com/rmyndharis/OpenWA) — multi-sessão, HMAC webhooks, API Key auth, Docker/PostgreSQL/Redis/S3, Swagger docs. Provedor primário planejado para TopwebChat.
_Avoid_: WhatsApp API, gateway

**HMAC Webhook**: Assinatura SHA256 no header `X-OpenWA-Signature: sha256=<hex>` para validação de integridade/origem do payload OpenWA (computado sobre **raw JSON body**).
_Avoid_: Assinatura webhook, signature

**Provider Factory**: Seletor de adapter `MessagingProvider` por `Instance.provider` (`openwa` → `OpenWaProvider`, `evolution` → `EvolutionProvider`).
_Avoid_: Factory, seletor

**Idempotency Key (OpenWA)**: Chave content-derived no header `X-OpenWA-Idempotency-Key` (ex: `msg_{sessionUuid}_{messageId}`) — estável across retries, usado para deduplicação consumer-side.
_Avoid_: Chave idempotência, dedup key

**Delivery ID (OpenWA)**: Header `X-OpenWA-Delivery-Id` (`dlv_<uuid>`) — fresh per delivery (diferente por retry e por webhook), para tracing.
_Avoid_: Delivery ID, trace ID

**Retry Count (OpenWA)**: Header `X-OpenWA-Retry-Count` (0 = first attempt) — para observabilidade de tentativas.
_Avoid_: Contador retry

**Session Status (OpenWA)**: Valores lowercase wire: `created` | `initializing` | `qr_ready` | `authenticating` | `ready` | `disconnected` | `action_required` | `failed`. `FAILED` é terminal (não adotado por takeover).
_Avoid_: Status sessão, state

**Media Download (OpenWA)**: `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` — **obrigatório** para persistir mídia; URLs do OpenWA são temporárias/assinadas.
_Avoid_: Media URL, download URL

**Contacts Check (OpenWA)**: `GET /api/sessions/:sessionId/contacts/check/:number` — pré-valida se número está no WhatsApp antes de enviar (evita 201 para não-registrado).
_Avoid_: Verificação contato

**Pairing Code (OpenWA)**: `POST /api/sessions/:sessionId/pairing-code` — código 8 chars via telefone, alternativa ao QR.
_Avoid_: Código pareamento

**Quarentena Identidade (Identity Quarantine)**: Estado de uma `Conversation` quando o `remote_jid` (telefone/JID) do webhook `message.received` **não resolve univocamente para uma Pessoa** no CRM. Três cenários:
- **Zero matches**: `contacts/check` retorna `exists=false` OU número não encontrado no CRM → `Conversation.person_id=NULL`, `status='quarantined'`, `quarantine_reason='no_match'`. Botão "Enviar WhatsApp" indisponível no Lead/Pessoa; botão "Checar WhatsApp" habilitado para revalidação via `contacts/check`.
- **Múltiplos matches**: Mesmo telefone/JID vinculado a 2+ Pessoas → `Conversation.person_id=NULL`, `status='quarantined'`, `quarantine_reason='ambiguous'`, `candidate_person_ids` (JSON array). Requer seleção manual do Lead/Pessoa principal por operador com permissão `topweb_chat.quarantine_resolve`.
- **@lid sem phone**: `RESOLVE_LID_TO_PHONE=false` e `GET /contacts/:contactId/phone` retorna `null` → `status='quarantined'`, `quarantine_reason='lid_unresolved'`, `needs_lid_resolution=true`. Tentativa on-demand de resolução antes de quarentenar.

**Resolução de Quarentena**: Operador com permissão `topweb_chat.quarantine_resolve` acessa fila "Quarentena Identidade" → busca/vincula Pessoa/Lead → `Conversation.person_id` preenchido, `status='active'`, `quarantine_reason=NULL`. Mensagens persistidas durante quarentena ficam ocultas do operador comum; tornam-se visíveis após resolução.
_Avoid_: Quarentena, isolamento, bloqueio

**Checar WhatsApp (WhatsApp Check)**: Ação disparada pelo botão "Checar WhatsApp" no Lead/Pessoa quando em quarentena (zero matches) ou sob demanda. Executa `GET /api/sessions/:sessionId/contacts/check/:number` no OpenWA → se `exists=true` e `whatsappId` compatível, habilita botão "Enviar WhatsApp"; se `exists=false`, mantém quarentena. Disponível para operadores com permissão `topweb_chat.access`.
_Avoid_: Verificar WhatsApp, validar WhatsApp

**Drift Webhook (Webhook Drift)**: Divergência entre estado local (TopwebCRM) e estado real no OpenWA (WhatsApp). Causas: entregas perdidas, reordenação, duplicatas, crash entre recebimento e processamento. OpenWA garante at-least-once + idempotency keys + reconciliação automática 60s + tabela `webhook_delivery_failures`.

**Tipos de Drift Detectados/Reconciliados Automaticamente**:
| Tipo | Detecção | Ação Automática |
|------|----------|-----------------|
| **Instance status** | Scheduler `everyMinute`: `GET /api/sessions/:sessionId` → comparar `status`, `engineLoaded`, `restriction`, `lastActive` | Atualizar `Instance` local; se `status='failed'` → alerta + não adotar (INV-7) |
| **Message status** | Scheduler `everyFiveMinutes`: msgs `status IN ('sent','delivered')` > 10min sem ack → `GET /api/sessions/:sessionId/messages/:chatId/history?limit=50` | Atualizar `Message.status` (monotônico); se remoto=`failed` → `failed` + `last_error` |
| **Unknown messages** | Mensagens locais `status='unknown'` → consultar histórico remoto | Se remoto=`delivered`/`read` → atualizar; se `failed` → `failed`; se não encontrado → manter `unknown` + `reconcile_attempts++` |
| **Conversation drift** | `unread_count` dessincronizado vs inbound não lidas | Recalcular `unread_count` = count `Message` inbound `status != 'read'` |
| **Webhook delivery failures** | `GET /api/webhooks/delivery-failures` (ADMIN) → deliveries esgotados | Log estruturado + alerta; re-dispatch manual via botão "Reprocessar" |

**Job de Reconciliação**: `topweb-chat:reconcile --state|--history|--full`
- `--state` (everyMinute): instance status
- `--history` (everyFiveMinutes, limit 20): message status + conversation drift
- `--full` (daily, opcional): backfill completo

**Não Reconciliado Automaticamente** (intervenção humana):
- `Instance.status='failed'` — terminal (INV-7)
- Webhook deliveries esgotados — reprocessamento manual
- Quarentena identidade — resolução humana

**Terminologia**:
- **Drift** = divergência detectada (substantivo)
- **Reconciliação** = processo de correção (verbo/ação)
- **Job de Reconciliação** = comando `topweb-chat:reconcile`
_Avoid_: Sincronização, sync (termo genérico; usar "reconciliação" para correção de drift)

**Mídia Privada (Private Media)**: Mídia WhatsApp (imagem, vídeo, áudio, documento, sticker) armazenada em `storage/app/private` (disco `private` / `SENSITIVE_DATA_DISK`) com acesso via rota autenticada. **Obrigatório** para toda mídia inbound — URLs OpenWA são temporárias/assinadas; outbound URL-based sends não são persistidos pelo gateway.

**Fluxo Inbound** (webhook `message.received` com `hasMedia=true`):
- **Inline** (`media.data` base64 ≤ 1MiB): Job `DownloadMedia` (queue `topweb_chat_media`) decodifica base64 → salva em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}`
- **Omitted** (`media.omitted=true`, `sizeBytes > 1MiB`): Job `DownloadMedia` → `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` (stream) → salva mesmo path
- **Metadados persistidos** em `Message.metadata` (criptografado): `mimetype`, `filename`, `sizeBytes`, `sha256`, `storage_path`, `downloaded_at`

**Fluxo Outbound** (envio pelo operador):
- **Upload via UI**: Frontend → `POST /api/topweb-chat/media/upload` → salva temporário em `storage/app/private/temp/{operator_id}/{conversation_id}/` → retorna `media_token` (JWT/signed URL) → envio usa `base64` (se ≤ 50MB) **OU** `url` (rota assinada temporária no MinIO/S3 futuro)
- **URL externa**: Operador cola link → envio usa `url` (OpenWA faz fetch server-side com SSRF guard)

**Download pelo Operador**:
```
GET /api/topweb-chat/media/{messageId}?token={media_token}
→ ConversationAccessService valida permissão
→ Serve do `storage/app/private/...` com `Content-Disposition: attachment`
→ Headers: `X-Content-Type-Options: nosniff`, `Content-Type` conservador
```

**Configuração de Disco e Retenção**:
- **Disco**: `SENSITIVE_DATA_DISK=private` (compartilhado com anexos e-mail/atividade)
- **Path pattern**: `topweb_chat/{conversation_id}/{message_id}.{ext}` (futuro MinIO: bucket `topweb-chat`, prefixo `{conversation_id}/`)
- **Retenção**: TTL configurável (política de retenção de dados/mensagens); sem auto-purge padrão
- **Archive OpenWA**: Futuro — opção em Configurações TopwebChat → `CHAT_MEDIA_ARCHIVE_ENABLED=true` + `CHAT_MEDIA_ARCHIVE_OUTBOUND=true` no OpenWA (MinIO/S3 bucket próprio, regras de criação de bucket/pastas)

**Tipos MIME Permitidos**:
- `image/*` (jpg, png, webp, gif) + **stickers** (`image/webp`)
- `video/*` (mp4, 3gp, mov)
- `audio/*` (ogg/opus, mp3, m4a, aac) — voice notes: `audio/ogg; codecs=opus` + `ptt=true`
- `application/pdf`
- `application/vnd.openxmlformats-officedocument.*` (docx, xlsx, pptx)
- `application/msword`, `application/vnd.ms-excel`, `application/vnd.ms-powerpoint`
**Bloqueados**: `application/x-executable`, `application/x-msdownload`, `application/x-sh`, `application/x-php`, qualquer `application/*` não listado

**Terminologia Mídia**:
- **Mídia Privada** = mídia WhatsApp em `storage/app/private` com acesso autorizado
- **Media Download Job** = job assíncrono `DownloadMedia` (queue `topweb_chat_media`)
- **Media Token** = token temporário (JWT/signed URL) para download via rota autenticada
- **Inline Media** = base64 no webhook payload (≤ 1MiB)
- **Omitted Media** = marker `{omitted:true, sizeBytes}` no webhook (> 1MiB)
_Avoid_: Media URL, download URL, media pública

**Precedência de Fontes**: `código local > docs locais do TopwebCRM > llms-full local > documentação online > hipótese`
_Avoid_: Fonte de verdade, documentação