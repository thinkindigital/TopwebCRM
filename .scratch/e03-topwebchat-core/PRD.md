# Epic E03: Módulo WhatsApp Nativo (TopwebChat) — Core

## Objetivo
Pacote `packages/Webkul/TopwebChat` com domínio base, persistência, ACL, UI operacional, provider adapter OpenWA (primário).

## Critérios de Sucesso
- [ ] Instância OpenWA cadastrável (session_uuid, base_url, api_key, webhook_secret HMAC)
- [ ] Webhook HMAC idempotente (`X-OpenWA-Signature`)
- [ ] Envio texto em job com outbox local (`operation_key`)
- [ ] Recebimento via webhook: eventos oficiais OpenWA
- [ ] Abertura/reutilização conversa por Pessoa/Lead
- [ ] Atribuição, filas ("meus", "sem atendente", "todos" p/ admin), notas internas
- [ ] Alteração etapa Lead pela conversa
- [ ] Concessão individual dados sensíveis; traduções en/pt_BR

## Estado
done

## Slices
- [ ] #13 - Entidades: Instance, Conversation, Message, InternalNote, WebhookEvent (Concord)
- [ ] #14 - Provider: `MessagingProvider` + `OpenWaProvider` (adapter desacoplado)
- [ ] #15 - Webhook: HMAC SHA256, idempotência, job ProcessWebhookEvent → WebhookProcessor
- [ ] #16 - Envio: MessageController → ConversationAccessService → MessageService → Job SendMessage → Provider
- [ ] #17 - ACL: access, view, start, send, note, assign, change_stage
- [ ] #18 - UI: filas, timeline polling 5s, status monotônico, botões Pessoa/Lead via eventos nativos
- [ ] #19 - Migration aplicada, rotas registradas, testes acesso/provider passando