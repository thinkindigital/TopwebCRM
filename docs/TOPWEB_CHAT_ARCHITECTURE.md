# Arquitetura do Topweb Chat

## Objetivo

O Topweb Chat é uma extensão nativa do TopwebCRM para atendimento e histórico conversacional WhatsApp dentro do CRM. O provedor externo (OpenWA, Evolution API, etc.) mantém sessão, transporte e entrega; o CRM mantém autorização, vínculo com Pessoa/Lead, fila, atendente, notas internas e histórico operacional.

## Camadas

1. **Transporte (Provider Adapter):** `MessagingProvider` + implementações concretas (`OpenWaProvider`, `EvolutionProvider`, etc.)
2. **Ingestão:** Webhook autenticado (HMAC), evento idempotente (`WebhookEvent`), job assíncrono (`ProcessWebhookEvent`)
3. **Atendimento:** Conversa, Mensagem, Atribuição, Nota Interna
4. **CRM:** Pessoa obrigatória, Lead opcional, Etapa do Pipeline

**Fluxo de saída:** `UI → MessageController → ConversationAccessService → MessageService → Job SendMessage → MessagingProvider → Provider Externo`

**Fluxo de entrada:** `Provider Externo → POST /api/topweb-chat/webhooks/{provider}/{instance} → HMAC + WebhookEvent idempotente → ProcessWebhookEvent → WebhookProcessor → Conversation/Message/Instance`

## Estado Atual (Core Implementado)

- Abertura/reutilização de conversa a partir de Pessoa ou Lead
- Envio de texto via job assíncrono com `operation_key` idempotente local
- Recebimento via webhook HMAC: `message.received`, `message.sent`, `message.ack`, `message.failed`, `message.revoked`, `message.reaction`, `message.edited`, `session.status`, `session.authenticated`, `session.disconnected`, `group.join`, `group.leave`, `group.update`, `call.received`
- Persistência idempotente antes do processamento (`WebhookEvent` por `idempotencyKey`)
- Atualização de status de entrega (monotônico: `delivered`/`read` não rebaixam para `failed`; `failed` é terminal)
- Vínculo da conversa com Pessoa e Lead
- Alteração da etapa do Lead pela conversa
- Atribuição, filas ("meus atendimentos", "sem atendente", "todos" p/ admin), notas internas
- Configuração de instância e webhook pela UI administrativa
- Timeline com polling 5s (JSON sanitizado)
- Proteção contra regressão de status fora de ordem
- Concessão individual de dados sensíveis (`users.can_view_sensitive_data`)

**Pendências Explícitas (Epic E06):**
- Reconciliação automática de drift (webhook ↔ estado local)
- Quarentena para identidade inbound ambígua (múltiplas Pessoas ou nenhuma)
- Mídia privada: download server-side, validação, armazenamento `storage/app/private`, URLs autorizadas
- Interativos: botões, listas, reações, edição de mensagem
- Domínio Grupos (ACL + auditoria própria)
- Domínio PIX (ACL + auditoria financeira)

## Identidade Remota

Não usa `Person.unique_id` como identidade WhatsApp. O módulo normaliza telefone/JID em `RemoteIdentityService` (formato OpenWA: `5511999999999@c.us`), armazena o identificador externo criptografado e usa `remote_jid_key` (SHA256) como hash pesquisável. 

**Privacy IDs (@lid):** OpenWA emite `from` como `@lid` (ex: `12345678901234@lid`) para contas com privacidade. Resolução opcional via:
- `RESOLVE_LID_TO_PHONE=true` no OpenWA → anexa `senderPhone` no webhook `message.received`
- On-demand: `GET /api/sessions/:sessionId/contacts/:contactId/phone` → mapeia `@lid` → telefone

JIDs especiais (`@lid`, grupos `@g.us`, canais `@newsletter`, status `@broadcast`) são preservados no `remote_jid` criptografado.

## Autorização

ACL granular: `topweb_chat.access`, `topweb_chat.view`, `topweb_chat.start`, `topweb_chat.send`, `topweb_chat.note`, `topweb_chat.assign`, `topweb_chat.change_stage`.

- Administradores: veem todas as conversas
- Operadores: veem próprias + não atribuídas
- Botão **Enviar WhatsApp** funciona sem revelar telefone (resolução no backend)
- Dados sensíveis: apenas concessão individual `users.can_view_sensitive_data`
- Tokens, segredo do webhook, payload bruto nunca no navegador

## Pontos de Extensão

Botões em Pessoa/Lead usam eventos nativos Krayin:
- `admin.contact.persons.view.actions.after`
- `admin.leads.view.actions.after`

Pacote em `packages/Webkul/TopwebChat` — não altera controllers/views do núcleo.

## Multi-Provider (Implementado)

O contrato `MessagingProvider` permite troca de provedor sem tocar no domínio:
- **`OpenWaProvider` (primário, implementado)** — OpenWA self-hosted, HMAC webhooks, API Key, multi-sessão nativa
- `EvolutionProvider` (futuro) — mesmo contrato
- **Factory/Selector:** `ProviderFactory::make(Instance $instance)` resolve por `Instance.provider` (`openwa` | `evolution`)
- **Config por instância:** `base_url`, `api_key`, `webhook_secret`, `session_uuid`, `session_name`
- **Webhook route:** `/api/topweb-chat/webhooks/{provider}/{session_uuid}`

---

## Referências

- **Mapa de endpoints OpenWA:** `docs/TOPWEB_CHAT_OPENWA_MAP.md`
- **Operação:** `docs/TOPWEB_CHAT_OPERATIONS.md`
- **Branding/Assets:** `docs/BRANDING.md`
- **ADR da decisão:** `docs/adr/0004-topwebchat-whatsapp-module.md`