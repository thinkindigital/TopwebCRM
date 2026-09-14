---
doc_id: topwebchat-state
type: module-state
status: active
authority: canonical
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [model, route, flow, provider, ui, feature-flag]
related: [docs/modules/topweb-chat/CONTRACTS.md, tests/Feature/TopwebChat]
---

# TopwebChat State

## IMPLEMENTED

- Persistência de Instance, Conversation, Message, InternalNote, WebhookEvent,
  Attendance e MediaProjection.
- Inbox/timeline, texto e mídia, retry seguro, notas, etapa do Lead e polling.
- Webhook HMAC, eventos idempotentes, resolução oficial de `@lid`, mídia privada
  e projeção idempotente em Lead/Pessoa.
- Attendance agregadora com janela de 24 horas e fechamento agendado.
- Fila A3 cega e claim transacional; `assigned_user_id` ainda participa da
  autorização e da listagem.
- OpenWA 0.23.4; seleção manual global de engine por configuração do CRM.
- Renderer por fragmento de servidor e workspace E14, exceto V-06.

## DECIDED_NOT_IMPLEMENTED

- `Lead.user_id` como autoridade única em todas as superfícies (E10/D04).
- Conta WhatsApp duradoura separada da Sessão OpenWA e arquivamento sem hard
  delete (ADR 0010).
- V-06 busca segura, bloqueada por E10; estado explícito/SLA, quarentena,
  importação manual e roleta.

## DEPRECATED

- Exclusão local destrutiva de Instance e seu histórico. Existe no controller,
  mas não orienta evolução nova.
- Uso de `Conversation.assigned_user_id` como autoridade independente quando há
  Lead.

## EXPERIMENTAL

- Engine Baileys via OpenWA. Adapter e flag existem; drift entre engine do
  gateway e configuração do CRM ainda não é bloqueado automaticamente.

## REMOVED

- RyzeAPI não é provider suportado.

## Divergências abertas

- Código legado permite acesso a conversa sem responsável; o contrato atual só
  permite exposição cega antes do claim.
- A interface/adapter formal de provider não descreve toda chamada de saúde
  usada por Settings. Tratar em E03, sem anunciar paridade inexistente.
