## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Domínio PIX WhatsApp (pagamentos via WhatsApp):
1. **Entidades**:
   - `TopwebChatPixTransaction`: `id`, `conversation_id`, `message_id`, `pix_key`, `amount`, `currency`, `status` (pending/paid/failed/expired/refunded), `payload`, `response`, `expires_at`, `paid_at`
2. **Webhook** (OpenWA — se suportar):
   - `message.received` com `type='pix'` → detecta payload PIX
   - Job `ProcessPixMessage` → cria `PixTransaction` + vincula `Message`
2. **Outbound** (enviar cobrança PIX):
   - UI: botão "Solicitar PIX" no composer → modal: chave PIX, valor, descrição
   - `POST /api/sessions/:sessionId/messages/send-pix` (se OpenWA suportar) OU envia mensagem texto com payload PIX copiável
3. **Auditoria**: log estruturado `pix.transaction` com todos campos (LGPD/PCI compliance)
4. **ACL**: `topweb_chat.pix.send`, `topweb_chat.pix.view`, `topweb_chat.pix.audit` (apenas admins/financeiro)

## Acceptance Criteria
- [ ] Entidade `TopwebChatPixTransaction` (migration + model + repository)
- [ ] Inbound: detecta mensagem PIX → cria transação vinculada à Message
- [ ] Outbound: UI "Solicitar PIX" → envia payload PIX (via OpenWA se suportar, senão texto)
- [ ] Auditoria: log imutável com todos campos sensíveis mascarados nos logs
- [ ] ACL granular (send/view/audit)
- [ ] Testes: mock PIX inbound/outbound → assert transação criada + audit log

## Blocked by
- Verificar se OpenWA suporta `send-pix` endpoint (spec não menciona explicitamente)
- ACL financeira separada

## Verification
```bash
# Mock mensagem PIX inbound → verificar PixTransaction criada + audit log
# UI: solicitar PIX → verificar envio
```