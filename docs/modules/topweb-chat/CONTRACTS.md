---
doc_id: topwebchat-contracts
type: module-contract
status: active
authority: canonical
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [conversation-authorization, queue, claim, ownership, attendance, retry]
related: [docs/policies/AUTHORIZATION_POLICY.md, docs/architecture/adr/0012-lead-ownership-enforcement.md]
---

# TopwebChat Contracts

## Ownership e fila

Onde há Lead, seu dono controla conversa, busca, contadores, polling, histórico,
exportação e arquivos. `assigned_user_id` é projeção. Antes do claim, agente
elegível vê na fila A3 apenas tempo de espera e ação; nome, preview, Lead e links
permanecem ocultos. Claim por botão ou primeiro outbound é atômico. Perdedor da
corrida recebe conflito sem ganhar acesso.

Sem Lead, vale a transição legada registrada em `STATE.md`; não a documentar
como estado final. Administrador tem escopo global, sujeito às policies de dado
sensível.

## Mensagem e falha

Outbound persiste intenção e `operation_key` antes da chamada externa. Timeout
ambíguo vira `unknown` e nunca recebe retry cego. Resposta de aceite do gateway
não prova entrega. Webhook usa HMAC do corpo bruto e chave idempotente.

## Histórico, Attendance e mídia

Mensagem permanece em `Message`; não vira Activity individual. Primeiro
outbound humano abre Atendimento WhatsApp. Mensagem real renova 24 horas;
evento técnico ou histórico importado não abre nem renova. Mídia inbound ao vivo
vinculada projeta um único objeto privado no Lead/Pessoa.

## Sessão e conta

CURRENT: Instance concentra conta, sessão e credenciais; exclusão é destrutiva.
DECIDED: Conta WhatsApp é duradoura, Sessão OpenWA é descartável e arquivável,
com no máximo uma ativa por conta e correlação por telefone confirmado.
