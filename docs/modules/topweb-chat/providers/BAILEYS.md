---
doc_id: topwebchat-engine-baileys
type: provider-delta
status: active
authority: canonical
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [openwa-version, baileys-engine, adapter-contract]
related: [docs/modules/topweb-chat/providers/OPENWA.md, docs/modules/topweb-chat/STATE.md]
---

# Delta da engine Baileys

Baileys é uma engine do gateway OpenWA, não um segundo gateway. O código atual
usa `BaileysProvider` como adapter específico, apesar desse nome poder sugerir
multi-provider.

| Capacidade | whatsapp-web.js | Baileys |
|---|---|---|
| Processo | Puppeteer/Chrome | Node.js/WebSocket |
| Mídia image/video/audio | workaround `send-document` | rotas nativas |
| Histórico | cursor `after` | cursor `after` |
| Marcar lida | chat | aceita `messageIds[]` |
| Estado adicional | básico | archived/pinned/muted |

Auth state fica no volume persistente do OpenWA. Não há migração automática de
sessão entre engines; novo pareamento é necessário. Webhooks, HMAC, proxy e API
key seguem o contrato comum em [OPENWA](OPENWA.md).

CURRENT: a engine do OpenWA e `TOPWEB_CHAT_ENGINE` são configurações manuais
independentes. Divergência não é detectada; validar no deploy. Auto-detecção e
migração assistida pertencem à Epic E13, não a este contrato.
