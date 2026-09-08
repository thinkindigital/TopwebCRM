# Projeto de evolucao do TopwebChat

Este documento organiza a evolucao do chat como canal operacional de CRM. Ele nao substitui `ORCHESTRATOR-ROADMAP.md`; detalha o comportamento esperado do modulo TopwebChat.

## Objetivo

Transformar o TopwebChat em uma caixa de atendimento comercial confiavel: mensagens, midias, pessoas, leads, atribuicoes e historico devem formar um fluxo unico, auditavel e simples para corretor, gerente e administrador.

## Estado entregue no ciclo atual

1. Conversas abertas sem `assigned_user_id` aparecem na aba **Sem atendente**.
2. Administrador pode desatribuir qualquer conversa pelo painel lateral.
3. Agente responsavel pode devolver sua propria conversa para a fila sem atendente.
4. O primeiro agente que responder assume a conversa dentro da transacao que cria a mensagem.
5. Envio de midia preserva erro operacional em `last_error`, expoe o erro no polling e permite retry quando seguro.
6. Exclusao em massa de Pessoas bloqueia registros com conversas vinculadas e informa o motivo.

## Principios de produto

1. O chat pertence ao ciclo comercial, nao a uma tela isolada.
2. Conversa, Pessoa e Lead devem ser observados juntos sempre que houver vinculo confiavel.
3. Mensagem recebida sem responsavel deve virar trabalho visivel para a equipe, nao ficar perdida.
4. Assumir conversa deve ser concorrente e atomico; dois agentes nao podem atender o mesmo lead por acidente.
5. Midia e dado sensivel devem permanecer privados, com rota autenticada e autorizada.
6. Historico importado nao deve gerar atividade comercial retroativa nem inflar nao lidos.

## Fluxo alvo de atendimento

1. Mensagem inbound chega pelo OpenWA e cria ou atualiza a Conversa local.
2. Se nao houver atendente, a Conversa aparece em **Sem atendente**.
3. Agente abre a conversa, responde e assume automaticamente.
4. Conversa sai da fila sem atendente para os demais agentes no proximo refresh.
5. Atendimento gera ou renova Activity WhatsApp vinculada a Pessoa/Lead.
6. Gerente ou administrador pode reatribuir ou devolver para sem atendente.
7. Encerramento por inatividade fecha o atendimento, mas preserva historico e vinculos.

## Proximos incrementos recomendados

### P1 - Smoke test real de midia

Validar, em producao ou staging conectado ao OpenWA real:

1. imagem pequena `jpeg/png` enviada pelo CRM;
2. documento PDF enviado pelo CRM;
3. falha por sessao nao `ready`;
4. falha por arquivo ausente no storage;
5. retry somente quando `provider_message_id` ainda estiver vazio.

### P1 - Contadores e prioridade da fila

Adicionar contadores por aba: **Meus atendimentos**, **Sem atendente**, **Todos**. A fila sem atendente deve ordenar por mensagens nao lidas e `last_message_at`.

### P1 - Excluir conversa com UX administrativa

Expor botao administrativo nas telas de Pessoa/Conversa para excluir conversas vinculadas antes da exclusao da Pessoa. Backend ja possui rota de exclusao por Pessoa; falta acoplamento visual onde o administrador trabalha.

### P2 - Observabilidade operacional

Criar painel simples de saude do chat: instancia, queue, scheduler, ultimos erros de envio, ultimos webhooks e idade do ultimo polling bem-sucedido.

### P2 - Auditoria de atribuicao

Persistir eventos de atribuicao, desatribuicao, captura automatica e reatribuicao. Hoje o estado final fica em `assigned_user_id`, mas nao ha trilha historica propria.

### P2 - Busca e contexto CRM

Unificar busca de Conversas, Pessoas e Leads com mascaramento consistente. O agente precisa encontrar o lead pelo nome, telefone mascarado quando permitido e titulo da oportunidade.

### P3 - Distribuicao automatica

Depois da fila sem atendente estabilizada, implementar roleta de distribuicao justa e auditavel por equipe, disponibilidade, carga e regras do pipeline.

## Criterios de aceite futuros

1. Nenhuma mensagem inbound aberta fica invisivel para todos os agentes.
2. Duas respostas concorrentes em conversa sem atendente resultam em apenas um responsavel.
3. Midia enviada pelo CRM aparece no WhatsApp real e na timeline local com status coerente.
4. Falhas de midia apresentam motivo legivel e nao geram duplicidade por retry indevido.
5. Pessoa com conversa vinculada nunca e apagada por mass delete sem aviso claro.
6. Toda mudanca de responsavel relevante pode ser auditada.
