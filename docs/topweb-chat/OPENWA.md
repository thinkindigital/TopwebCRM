# Contrato OpenWA do TopwebCRM

## Referencia fixada

- Repositorio: https://github.com/rmyndharis/OpenWA
- Versao auditada: `0.23.0`
- Base local padrao: `http://localhost:2785/api`
- Autenticacao: `X-API-Key`
- Swagger, quando habilitado: `/api/docs`
- Respostas de sucesso sao JSON bruto, sem envelope `{data: ...}`.

Este documento lista apenas o subconjunto necessario ao TopwebCRM. A capacidade do OpenWA nao implica implementacao no CRM.

## Sessoes

| Operacao | Endpoint | Uso no CRM |
|---|---|---|
| Health | `GET /api/health` | Testar conexao |
| Listar | `GET /api/sessions` | Descobrir sessoes importaveis |
| Criar | `POST /api/sessions` | Criar sessao pelo Settings |
| Obter | `GET /api/sessions/{sessionId}` | Reconciliar estado |
| Iniciar | `POST /api/sessions/{sessionId}/start` | Carregar engine/gerar QR |
| Parar | `POST /api/sessions/{sessionId}/stop` | Parar mantendo credenciais |
| Logout | `POST /api/sessions/{sessionId}/logout` | Desvincular WhatsApp |
| Excluir | `DELETE /api/sessions/{sessionId}` | Excluir sessao remota com confirmacao |
| QR | `GET /api/sessions/{sessionId}/qr` | Retorna PNG data URL em `qrCode` |
| Pairing | `POST /api/sessions/{sessionId}/pairing-code` | Pareamento por telefone |

Estados wire: `created`, `initializing`, `qr_ready`, `authenticating`, `ready`, `disconnected`, `action_required`, `failed`. Para envio, `ready` e `engineLoaded=true` significam sessao utilizavel.

## Conversas e mensagens

| Operacao | Endpoint | Resposta relevante |
|---|---|---|
| Listar chats | `GET /api/sessions/{sessionId}/chats` | Array bruto de chats |
| Mensagens persistidas | `GET /api/sessions/{sessionId}/messages` | `{messages, total}`; `limit` maximo 100 |
| Historico live | `GET /api/sessions/{sessionId}/messages/{chatId}/history` | Array bruto; sem `hasMore` |
| Enviar texto | `POST /api/sessions/{sessionId}/messages/send-text` | `201 {messageId, timestamp}` |
| Marcar lido | `POST /api/sessions/{sessionId}/chats/read` | Body `{chatId, messageIds?}` |

Texto enviado aceita no maximo 4096 caracteres. `chatId` deve ser o identificador WhatsApp, como `5511999999999@c.us`.

## Webhooks

| Operacao | Endpoint |
|---|---|
| Registrar | `POST /api/sessions/{sessionId}/webhooks` |
| Listar | `GET /api/sessions/{sessionId}/webhooks` |
| Testar | `POST /api/sessions/{sessionId}/webhooks/{webhookId}/test` |

O segredo HMAC deve ser enviado literalmente no campo `secret`. O OpenWA assina o corpo bruto em `X-OpenWA-Signature: sha256=<hex>`.

Eventos iniciais suportados pelo CRM:

- `message.received`
- `message.sent`
- `message.ack`
- `message.failed`
- `session.status`
- `session.authenticated`
- `session.disconnected`

Eventos desconhecidos devem ser preservados como nao suportados; nunca marcados como processados silenciosamente.

## Regras de integracao

- O UUID da sessao, nao o nome, identifica rotas OpenWA.
- API Key e segredo HMAC permanecem criptografados no CRM.
- A mesma string de segredo registra e valida o webhook.
- Timeout de envio nao autoriza retry cego; reconciliar antes de reenviar.
- `201` significa aceito pelo gateway, nao entregue ao destinatario.
- Backfill usa mensagens persistidas; historico live e complementar e limitado.
- Contratos devem ser cobertos por testes HTTP para impedir regressao de formato.
