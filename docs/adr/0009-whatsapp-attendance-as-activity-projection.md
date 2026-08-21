# ADR 0009: Atendimento WhatsApp como projeção em Activity

- Status: aceito
- Data: 2026-08-21

## Contexto

Copiar cada mensagem para `activities` duplicaria conteúdo e criaria inconsistência. Registrar somente um resumo perderia a janela operacional e não permitiria encerramento confiável.

## Decisão

- Cada janela de atendimento gera uma Activity nativa vinculada a Pessoa e Lead.
- Uma tabela auxiliar do TopwebChat vincula Activity, Conversation, sequência, último evento real e encerramento.
- A primeira mensagem enviada por usuário do CRM abre `Atendimento WhatsApp`.
- Mensagens reais de ambos os lados renovam a janela de 24 horas.
- Reações e eventos técnicos não alteram a janela.
- O fechamento é automático e idempotente.
- Nova mensagem enviada por usuário do CRM após fechamento abre `Atendimento Continuado`.
- `Activity.comment` guarda um relato textual único.
- Histórico importado não cria Activities retroativas.

## Consequências

- Mensagens continuam sendo a fonte da timeline detalhada do chat.
- Fechamento e reabertura exigem lock e testes de concorrência.
- O serviço do TopwebChat deve anexar explicitamente a Activity à Pessoa e ao Lead.
