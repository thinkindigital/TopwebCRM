# ADR 0007: Acesso sensível individual e contextual

- Status: aceito; supera o ADR 0003 onde houver conflito
- Data: 2026-08-21

## Contexto

O código atual usa `users.can_view_sensitive_data`, enquanto documentos anteriores afirmavam que Role, ACL e administração concediam visão integral automaticamente. No chat, o responsável precisa ler a conversa para atender, mas isso não deve liberar todos os dados sensíveis do CRM.

## Decisão

- A concessão individual continua sendo a autorização para visão integral de dados sensíveis.
- Acesso contextual à conversa permite ao dono do Lead ler conteúdo operacional necessário ao atendimento.
- O acesso contextual não libera automaticamente documentos, telefones, e-mails, cartões de contato ou outros dados classificados.
- Administradores podem acessar todas as conversas; credenciais e segredos permanecem fora da interface.
- Toda proteção deve existir no backend e nas rotas de arquivos.

## Consequências

- Role ou `permission_type=all` não substitui silenciosamente a concessão individual fora das exceções documentadas.
- Texto do chat exige mascaramento seletivo.
- Sem análise de conteúdo, anexos de documento ficam bloqueados para usuários sem concessão; imagens permanecem visíveis nesta fase.
- O ADR 0003 permanece histórico, mas não é a especificação atual.
