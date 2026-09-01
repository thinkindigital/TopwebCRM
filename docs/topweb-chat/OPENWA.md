# Contrato OpenWA consumido pelo TopwebCRM

Este documento registra apenas o subconjunto da API externa usado pelo adapter. A existência de um endpoint no OpenWA não significa que exista uma ação correspondente na interface do CRM.

## Baseline

- Repositório: https://github.com/rmyndharis/OpenWA
- imagem de produção fixada no manifest: `0.23.3`;
- URL base: origem sem `/api`, por exemplo `http://openwa_openwa_api:2785`;
- autenticação: header `X-API-Key`;
- respostas: JSON direto, sem envelope `{data: ...}`;
- documentação interativa, quando habilitada: `/api/docs`.

O adapter acrescenta `/api` a cada rota. Alterar a versão da imagem requer executar os testes de contrato e o smoke test com sessão real.

## Sessões

| Operação | Endpoint |
|---|---|
| saúde | `GET /api/health` |
| listar | `GET /api/sessions` |
| criar | `POST /api/sessions` |
| obter/reconciliar | `GET /api/sessions/{sessionId}` |
| iniciar | `POST /api/sessions/{sessionId}/start` |
| parar | `POST /api/sessions/{sessionId}/stop` |
| logout | `POST /api/sessions/{sessionId}/logout` |
| encerramento forçado | `POST /api/sessions/{sessionId}/force-kill` |
| QR | `GET /api/sessions/{sessionId}/qr` |
| pairing code | `POST /api/sessions/{sessionId}/pairing-code` |
| ler/alterar configuração | `GET` ou `PATCH /api/sessions/{sessionId}/config` |

O UUID da sessão identifica todas as rotas. Para envio, o CRM considera `ready` e `engineLoaded=true` como estado utilizável.

## Mensagens e histórico

O contrato prevê envio de texto, mídias, localização, contato, reação, edição, remoção, encaminhamento, sticker, enquete e lote. O caminho funcional coberto pelo fluxo principal atual é o envio de texto enfileirado; as demais operações exigem auditoria do contrato e teste próprio antes de serem expostas como disponíveis.

| Operação central | Endpoint |
|---|---|
| enviar texto | `POST /api/sessions/{sessionId}/messages/send-text` |
| histórico persistido | `GET /api/sessions/{sessionId}/messages?chatId=...&limit=...` |
| marcar chat como lido | `POST /api/sessions/{sessionId}/chats/read` |
| baixar mídia | `GET /api/sessions/{sessionId}/messages/{chatId}/{messageId}/media` |
| resolver telefone/WhatsApp ID | `GET /api/sessions/{sessionId}/contacts/check/{number}` |

`chatId` é o identificador WhatsApp, por exemplo `5511999999999@c.us`. A resposta de aceite do gateway não confirma entrega ao destinatário.

## Webhooks

| Operação | Endpoint |
|---|---|
| registrar | `POST /api/sessions/{sessionId}/webhooks` |
| listar/obter | `GET /api/sessions/{sessionId}/webhooks[/{webhookId}]` |
| testar | `POST /api/sessions/{sessionId}/webhooks/{webhookId}/test` |
| atualizar | `PUT /api/sessions/{sessionId}/webhooks/{webhookId}` |
| remover | `DELETE /api/sessions/{sessionId}/webhooks/{webhookId}` |

O segredo é enviado no campo `secret`. O OpenWA assina o corpo bruto em `X-OpenWA-Signature: sha256=<hex>`; o CRM calcula o HMAC antes de interpretar o payload.

Os eventos assinados configurados pelo módulo estão em `packages/Webkul/TopwebChat/src/Config/topweb-chat.php`. A lista inclui eventos de mensagem, sessão, grupo, chamada e status. O processador atual só projeta no domínio mensagens recebidas/enviadas, ACK/falha e estado de sessão; os demais continuam persistidos no log de eventos, mas são marcados como processados sem efeito funcional adicional.

## Invariantes

- API key e segredo HMAC permanecem criptografados no CRM.
- A mesma string de segredo usada no cadastro valida a assinatura.
- Timeout após uma operação não idempotente exige reconciliação antes de retry.
- Backfill é limitado e não deve disparar busca profunda não controlada.
- Toda mudança de versão do OpenWA deve ser protegida por testes HTTP do adapter.
