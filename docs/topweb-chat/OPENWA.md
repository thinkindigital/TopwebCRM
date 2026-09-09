# Contrato OpenWA consumido pelo TopwebCRM

Este documento registra apenas o subconjunto da API externa usado pelo adapter. A existência de um endpoint no OpenWA não significa que exista uma ação correspondente na interface do CRM.

## Baseline

- Repositório: https://github.com/rmyndharis/OpenWA
- imagem de produção fixada no manifest: `0.23.4`;
- URL base: origem sem `/api`, por exemplo `http://openwa_openwa_api:2785`;
- autenticação: header `X-API-Key`;
- respostas: JSON direto, sem envelope `{data: ...}`;
- documentação interativa, quando habilitada: `/api/docs`.

O adapter acrescenta `/api` a cada rota. Alterar a versão da imagem requer executar os testes de contrato e o smoke test com sessão real.

## Engines Suportadas

O OpenWA 0.23.4+ suporta duas engines:

| Engine | Status | Observações |
|--------|--------|-------------|
| `whatsapp-web.js` | Padrão | Baseada em Puppeteer/Chrome headless; uso de memória maior; `send-image/video/audio` com bug "No LID for user" conhecido |
| `baileys` | Disponível (0.23.4+) | Pure Node.js/WebSocket; multi-device nativo; menor memória; envio de mídia nativo funcional |

A escolha é feita via variável `ENGINE_TYPE` no OpenWA (`whatsapp-web.js` | `baileys`) e espelhada no CRM via `TOPWEB_CHAT_ENGINE` (`whatsapp-web.js` | `baileys`). O provider no CRM (`OpenWaProvider` vs `BaileysProvider`) é resolvido automaticamente pela feature flag `TOPWEB_CHAT_ENGINE`.

## Sessões

| Operação | Endpoint (whatsapp-web.js) | Endpoint (Baileys) |
|---|---|---|
| saúde | `GET /api/health` | `GET /api/health` |
| listar | `GET /api/sessions` | `GET /api/sessions` |
| criar | `POST /api/sessions` | `POST /api/sessions` |
| obter/reconciliar | `GET /api/sessions/{sessionId}` | `GET /api/sessions/{sessionId}` |
| iniciar | `POST /api/sessions/{sessionId}/start` | `POST /api/sessions/{sessionId}/start` |
| parar | `POST /api/sessions/{sessionId}/stop` | `POST /api/sessions/{sessionId}/stop` |
| logout | `POST /api/sessions/{sessionId}/logout` | `POST /api/sessions/{sessionId}/logout` |
| encerramento forçado | `POST /api/sessions/{sessionId}/force-kill` | `POST /api/sessions/{sessionId}/force-kill` |
| QR | `GET /api/sessions/{sessionId}/qr` | `GET /api/sessions/{sessionId}/qr` |
| pairing code | `POST /api/sessions/{sessionId}/pairing-code` | `POST /api/sessions/{sessionId}/pairing-code` |
| ler/alterar configuração | `GET` ou `PATCH /api/sessions/{sessionId}/config` | `GET` ou `PATCH /api/sessions/{sessionId}/config` |
| proxy egresso | `GET`/`PATCH /api/sessions/{sessionId}/proxy` | `GET`/`PATCH /api/sessions/{sessionId}/proxy` |

O UUID da sessão identifica todas as rotas. Para envio, o CRM considera `ready` e `engineLoaded=true` como estado utilizável.

Em `0.23.4`, as respostas de listagem e obtenção de sessão expõem `phone`, `pushName`, `archived`, `pinned`, `muted`, `muteExpiration`. O engine `whatsapp-web.js` preenche `phone` a partir de `client.info.wid.user` quando fica pronto. O OpenWA não expõe o WID serializado completo, e `pushName` é editável; portanto, `phone` é o único correlator público disponível para reconhecer a mesma Conta WhatsApp entre UUIDs de sessão diferentes. Antes da autenticação ele pode ser `null`.

## Mensagens e histórico

O contrato prevê envio de texto, mídias, localização, contato, reação, edição, remoção, encaminhamento, sticker, enquete e lote.

| Operação central | Endpoint (whatsapp-web.js) | Endpoint (Baileys) |
|---|---|---|
| enviar texto | `POST /api/sessions/{sessionId}/messages/send-text` | `POST /api/sessions/{sessionId}/messages/send-text` |
| enviar imagem | `POST /api/sessions/{sessionId}/messages/send-image` | `POST /api/sessions/{sessionId}/messages/send-image` |
| enviar vídeo | `POST /api/sessions/{sessionId}/messages/send-video` | `POST /api/sessions/{sessionId}/messages/send-video` |
| enviar áudio | `POST /api/sessions/{sessionId}/messages/send-audio` | `POST /api/sessions/{sessionId}/messages/send-audio` |
| enviar documento | `POST /api/sessions/{sessionId}/messages/send-document` | `POST /api/sessions/{sessionId}/messages/send-document` |
| enviar sticker | `POST /api/sessions/{sessionId}/messages/send-sticker` | `POST /api/sessions/{sessionId}/messages/send-sticker` |
| enviar localização | `POST /api/sessions/{sessionId}/messages/send-location` | `POST /api/sessions/{sessionId}/messages/send-location` |
| enviar contato | `POST /api/sessions/{sessionId}/messages/send-contact` | `POST /api/sessions/{sessionId}/messages/send-contact` |
| enviar reação | `POST /api/sessions/{sessionId}/messages/react` | `POST /api/sessions/{sessionId}/messages/react` |
| editar mensagem | `POST /api/sessions/{sessionId}/messages/edit` | `POST /api/sessions/{sessionId}/messages/edit` |
| deletar mensagem | `POST /api/sessions/{sessionId}/messages/delete` | `POST /api/sessions/{sessionId}/messages/delete` |
| encaminhar | `POST /api/sessions/{sessionId}/messages/forward` | `POST /api/sessions/{sessionId}/messages/forward` |
| enviar sticker | `POST /api/sessions/{sessionId}/messages/send-sticker` | `POST /api/sessions/{sessionId}/messages/send-sticker` |
| enviar enquete | `POST /api/sessions/{sessionId}/messages/send-poll` | `POST /api/sessions/{sessionId}/messages/send-poll` |
| enviar em lote | `POST /api/sessions/{sessionId}/messages/send-bulk` | `POST /api/sessions/{sessionId}/messages/send-bulk` |
| histórico | `GET /api/sessions/{sessionId}/messages?chatId=...&limit=...&after=...` | `GET /api/sessions/{sessionId}/messages?chatId=...&limit=...&after=...` |
| marcar chat como lido | `POST /api/sessions/{sessionId}/chats/read` | `POST /api/sessions/{sessionId}/chats/read` (suporta `messageIds[]`) |
| baixar mídia | `GET /api/sessions/{sessionId}/messages/{chatId}/{messageId}/media` | `GET /api/sessions/{sessionId}/messages/{chatId}/{messageId}/media` |
| resolver telefone/WhatsApp ID | `GET /api/sessions/{sessionId}/contacts/check/{number}` | `GET /api/sessions/{sessionId}/contacts/check/{number}` |
| resolver identidade privada `@lid` | `GET /api/sessions/{sessionId}/contacts/{contactId}/phone` | `GET /api/sessions/{sessionId}/contacts/{contactId}/phone` |

**Diferenças importantes de mídia:**
- **whatsapp-web.js (0.23.4):** `send-image`, `send-video`, `send-audio` falham com erro "No LID for user" (bug conhecido no wawebjs). `send-document` funciona para todos os tipos.
- **Baileys:** Todas as rotas de mídia funcionam nativamente (image, video, audio, document, sticker).

**Workaround whatsapp-web.js:** O CRM usa `send-document` para todos os tipos de mídia quando a engine é `whatsapp-web.js`. O WhatsApp Web aceita documentos com mimetype de imagem/vídeo/áudio, mas eles aparecem como anexo (ícone de documento), não como preview inline.

`chatId` é o identificador WhatsApp, por exemplo `5511999999999@c.us`. Um identificador `@lid` não contém telefone e nunca deve ser convertido por heurística: o CRM consulta o endpoint `/contacts/{contactId}/phone` e mantém a identidade pendente de revisão se o OpenWA não conhecer o vínculo. A resposta de aceite do gateway não confirma entrega ao destinatário.

O OpenWA não filtra histórico pelo conceito de Lead do CRM. Importação seletiva por Lead, escolha da Conta WhatsApp, telefone e limite deve ser coordenada pelo TopwebCRM antes de chamar o endpoint de mensagens.

A versão `0.23.4` com engine `whatsapp-web.js` também não expõe configuração por sessão para desabilitar a sincronização interna de histórico do engine. Uma chave de sincronização no CRM controla apenas as consultas e reconciliações iniciadas pelo CRM.

No recebimento de imagem, áudio, vídeo ou documento, o CRM usa o `chatId` e o ID da mensagem exatamente como entregues pelo webhook para buscar os bytes. O arquivo é copiado para o disco privado do CRM; token do OpenWA e URL interna não chegam ao navegador.

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

- Rotas de mídia são singulares (`send-image`, nunca `send-send-image`): o template de URL já carrega o prefixo `send-`, então o roteamento por mimetype retorna só o tipo (`image`, `video`, `audio`, `document`, `sticker`).
- API key e segredo HMAC permanecem criptografados no CRM.
- A mesma string de segredo usada no cadastro valida a assinatura.
- Timeout após uma operação não idempotente exige reconciliação antes de retry.
- Backfill é limitado e não deve disparar busca profunda não controlada.
- `phone` só pode vincular uma sessão a uma Conta WhatsApp depois de confirmado pelo OpenWA; mudança de número não autoriza mescla automática.
- Toda mudança de versão do OpenWA deve ser protegida por testes HTTP do adapter.
