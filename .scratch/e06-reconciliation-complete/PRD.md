# Epic E06: Reconciliação Completa e Domínios Pendentes

## Objetivo
Completar gaps operacionais do chat: reconciliação automática drift, quarentena identidade, mídia privada, interativos, domínios Grupos/PIX.

## Critérios de Sucesso
- [ ] Reconciliação automática: instance status, message status, unknown messages, conversation drift, webhook delivery failures
- [ ] Quarentena identidade: zero matches, múltiplos matches, @lid sem phone — resolução humana
- [ ] Mídia privada: download server-side (inline/omitted), upload outbound, download operador, retenção TTL, MinIO futuro
- [ ] Interativos: reações, edição, botões, listas
- [ ] Grupos: domínio separado com ACL + auditoria
- [ ] PIX: domínio separado com ACL + auditoria financeira

## Estado
todo

## Slices (Ordem de Dependência)

### Reconciliação Automática
- [ ] #30 - Instance status reconciliation (everyMinute)
- [ ] #31 - Message status reconciliation (everyFiveMinutes)
- [ ] #32 - Unknown messages reconciliation
- [ ] #33 - Conversation drift reconciliation (unread_count)
- [ ] #34 - Webhook delivery failures monitoring + manual reprocess UI

### Quarentena Identidade
- [ ] #35 - Quarentena: zero matches (no_match) + botão "Checar WhatsApp"
- [ ] #36 - Quarentena: múltiplos matches (ambiguous) + seleção Lead principal
- [ ] #37 - Quarentena: @lid sem phone (lid_unresolved) + resolução on-demand
- [ ] #38 - Fila "Quarentena Identidade" UI + resolução (vincular Pessoa/Lead)
- [ ] #39 - Botão "Checar WhatsApp" no Lead/Pessoa (contacts/check)

### Mídia Privada
- [ ] #40 - Media download inbound: inline (base64 ≤ 1MiB)
- [ ] #41 - Media download inbound: omitted (> 1MiB via GET /media)
- [ ] #42 - Media upload outbound: UI → media_token (JWT/signed URL) → base64 ou url
- [ ] #43 - Media download operador: rota autenticada `/api/topweb-chat/media/{messageId}`
- [ ] #44 - Media retention policy: TTL configurável, path pattern
- [ ] #45 - Media MIME validation: permitidos/bloqueados + stickers
- [ ] #46 - MinIO/S3 integration futuro: bucket próprio, configuração no Krayin

### Interativos & Domínios Separados
- [ ] #47 - Reações (message.reaction webhook + POST /react)
- [ ] #48 - Edição mensagem (message.edited webhook + POST /edit)
- [ ] #49 - Botões/Lists (se OpenWA suportar)
- [ ] #50 - Domínio Grupos: ACL + auditoria própria
- [ ] #51 - Domínio PIX: ACL + auditoria financeira
- [ ] #52 - Domínio Chamadas/Canais: call.received, status.received

## Dependências
- #30-34 independentes (podem paralelos)
- #35-39 dependem de #30 (instance status para saber se sessão ready)
- #40-45 dependem de #30-34 (reconciliação funcionando)
- #46 depende de #40-45
- #47-52 dependem de #40-45 (mídia base funcionando)