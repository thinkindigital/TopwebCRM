# BaileysProvider — Engine Alternativa para OpenWA

## Visão Geral

O `BaileysProvider` implementa o contrato `MessagingProvider` usando a engine **Baileys** (pure Node.js/WebSocket) em vez do `whatsapp-web.js` (Puppeteer/Chrome headless).

## Por que Baileys?

| Aspecto | whatsapp-web.js | Baileys |
|---------|-----------------|---------|
| **Arquitetura** | Puppeteer + Chrome headless | Pure Node.js + WebSocket |
| **Memória** | Alta (~500MB+/sessão) | Baixa (~50-100MB/sessão) |
| **Multi-device** | Não (single device) | Nativo (multi-device) |
| **Estabilidade** | Média (browser crash) | Alta |
| **Envio mídia** | Bug "No LID for user" | ✅ Nativo |
| **Startup** | Lento (Chrome init) | Rápido |

## Ativação

### 1. OpenWA (compose.openwa.production.yaml)

```yaml
environment:
  ENGINE_TYPE: baileys
  # Opcional para contas grandes
  PUPPETEER_PROTOCOL_TIMEOUT_MS: 30000
```

### 2. CRM (.env)

```dotenv
TOPWEB_CHAT_ENGINE=baileys
```

### 3. Reinicialização

```bash
# OpenWA
docker service update --force openwa_openwa_api

# CRM (se alterar .env)
docker service update --force topwebcrm_topwebcrm_app
docker service update --force topwebcrm_topwebcrm_queue
docker service update --force topwebcrm_topwebcrm_scheduler
```

### 4. Verificação

```bash
# OpenWA health
curl -H "X-API-Key: <key>" https://openwa.<dominio>/api/health

# CRM - conferir engine ativo
docker exec <app> php artisan tinker --execute="
echo 'Engine: ' . config('topweb-chat.engine');
echo 'Provider: ' . get_class(app(\Webkul\TopwebChat\Providers\Contracts\MessagingProvider::class));
"
```

## Implementação

### Arquivo: `packages/Webkul/TopwebChat/src/Providers/BaileysProvider.php`

Implementa `MessagingProvider` com 100% da superfície da API OpenWA 0.23.4+ para engine Baileys.

**Diferenças chave vs OpenWaProvider:**

| Método | whatsapp-web.js | Baileys |
|--------|-----------------|---------|
| `sendMedia` | Bug LID em image/video/audio | ✅ Nativo (send-image/video/audio/document) |
| `history` | Offset-based | Keyset cursor (`after`) |
| `markChatRead` | Todos ou nada | `messageIds[]` individual |
| `getChats` | Básico | `archived`, `pinned`, `muted`, `muteExpiration` |
| `checkContact` | `500` on page death | `503` declarado |
| `sendSticker` | Sem mentions | ✅ mentions (0.23+) |
| `editMessage` | ✅ | ✅ (0.23+) |

## Storage de Auth State

O Baileys persiste estado de autenticação em `SESSION_DATA_PATH` (padrão `/app/data/sessions`). Em produção, este caminho é um volume persistente (`openwa_data`). Não há migração automática de sessão whatsapp-web.js → Baileys; nova sessão deve ser criada.

## Limitações Conhecidas

1. **Migração de sessão:** Não há migração automática whatsapp-web.js → Baileys. Nova sessão = novo QR/pairing.
2. **Proxy:** Configuração de proxy por sessão (`/proxy` endpoint) funciona igual nos dois engines.
3. **Webhooks:** Mesma estrutura de eventos e assinatura HMAC.
4. **Rate limits:** Baileys respeita rate limits do WhatsApp de forma mais previsível.

## Troubleshooting

| Sintoma | Causa | Solução |
|---------|-------|---------|
| Sessão não conecta | Credenciais antigas | `docker exec openwa_openwa_api rm -rf /app/data/sessions/<uuid>` + restart |
| QR não gera | Sessão já autenticada | Stop/start session ou force-kill |
| Media falha | Mimetype não suportado | Verificar `send-document` como fallback |
| Rate limit excessivo | Baileys mais agressivo | Aumentar `TOPWEB_CHAT_SEND_MAX_ATTEMPTS` |

## Roadmap

- [ ] Migração assistida whatsapp-web.js → Baileys (preservar histórico)
- [ ] Auto-detecção de engine baseada em recursos disponíveis
- [ ] Testes de integração Baileys no CI
- [ ] Documentação de migração para clientes existentes