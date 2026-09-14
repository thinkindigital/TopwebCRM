---
doc_id: adr-0010
type: adr
status: active
authority: canonical
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [account-session-lifecycle, decision-superseded]
related: [docs/modules/topweb-chat/CONTRACTS.md]
---

# ADR 0010: Separar Conta WhatsApp de Sessão OpenWA

- Status: aceito
- Data: 2026-09-08

## Contexto

O modelo atual usa `Instance` simultaneamente como configuração local, sessão remota e namespace duradouro do histórico. Excluir a instância remove conversas e mensagens em cascata; trocar o UUID perde continuidade; parear outro número no mesmo registro pode misturar dados. A decisão de exclusão destrutiva registrada na Issue #66 não atende mais ao requisito de backup local.

## Decisão

- Conta WhatsApp será a identidade duradoura dona das Conversas e do histórico local.
- Sessão OpenWA será uma conexão substituível, vinculada à Conta WhatsApp somente após o OpenWA confirmar `phone`.
- Uma nova sessão com o mesmo `phone` substituirá e arquivará a sessão anterior; somente uma ficará ativa por Conta WhatsApp.
- Um `phone` diferente sempre criará outra Conta WhatsApp e não herdará histórico. Mudança de número também não autoriza mescla automática.
- “Excluir sessão” significará logout remoto e arquivamento local. Falha remota não bloqueará o arquivamento: o logout ficará pendente e será retentado de forma idempotente.
- O fluxo normal não terá hard delete de Conversas, Mensagens, notas, mídias ou Activities.
- O histórico antigo continuará consultável apenas no CRM e não será reenviado ao OpenWA quando uma sessão for substituída.
- Somente administrador poderá importar manualmente de 1 a 100 mensagens, padrão 50, escolhendo Conta WhatsApp, Lead e telefone explicitamente.
- Histórico importado será deduplicado, não contará como não lido, não alterará Atendimento nem criará Activity retroativa. Mídias serão baixadas em background.
- A reconciliação automática continuará independente da importação manual e poderá ser habilitada por Conta WhatsApp, com padrão habilitado.

## Consequências

- `Conversation` e as chaves de deduplicação deixam de depender da vida útil de uma sessão OpenWA.
- A migração precisa criar Contas WhatsApp sem inventar identidade para instâncias que ainda não tenham `phone` confirmado.
- Webhooks e envios de sessões arquivadas devem ser rejeitados ou reconciliados sem reativar a conexão.
- O OpenWA `0.23.3` não permite desabilitar a sincronização interna do `whatsapp-web.js`; o toggle local controla somente reconciliações iniciadas pelo CRM.
- Purge definitivo e eventual mescla de contas exigem decisões e fluxos separados.
