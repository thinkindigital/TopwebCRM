# ADR 0008: Dono do Lead controla acesso à conversa

- Status: aceito
- Data: 2026-08-21

## Contexto

Lead e Conversation possuem atribuições independentes no código atual. Essa duplicidade permite divergência, acesso indevido e comportamento imprevisível durante transferência e futura distribuição automática.

## Decisão

- O dono do Lead é a fonte de verdade para acesso à conversa vinculada.
- Administradores veem todas as conversas.
- Conversas sem Lead ou responsável ficam visíveis somente para administradores.
- Transferência do Lead atualiza o acesso às conversas na mesma transação e gera auditoria.
- A regra cobre listagens, URLs diretas, polling, histórico, busca, exportação, anexos e downloads.

## Consequências

- `assigned_user_id` da Conversation não pode criar autoridade divergente do Lead.
- A roleta futura deve atribuir Lead e conversas como uma única operação concorrente.
- A fila compartilhada de conversas não atribuídas deixa de existir para usuários comuns.
