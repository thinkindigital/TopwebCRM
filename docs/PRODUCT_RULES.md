## 1. Finalidade
Este documento define as regras de produto do TopwebCRM.

Ele existe para orientar decisões funcionais, evitar implementação tecnicamente correta porém desalinhada do objetivo do sistema, e servir como base para análise, arquitetura, segurança, UX e priorização.

Toda implementação deve respeitar este documento junto com `AGENTS.md`,
`CONTEXT.md`, `docs/ARCHITECTURE.md` e `docs/SECURITY_RULES.md`.

## 2. Visão do produto
O TopwebCRM é um fork evoluído do Krayin CRM com foco em uso interno, robustez operacional, governança de dados, extensibilidade e integração com canais de atendimento.

O objetivo não é criar um CRM genérico.  
O objetivo é criar um CRM interno mais forte para operação real, com:
- gestão segura de clientes, contatos, leads e oportunidades;
- controle de acesso maduro;
- tratamento rigoroso de dados sensíveis;
- histórico operacional consistente;
- integração de comunicação com WhatsApp;
- base evolutiva para novas automações e módulos.

## 3. Princípios de produto
### 3.1 Segurança antes de conveniência
Nenhuma conveniência operacional justifica vazamento de dado sensível.

### 3.2 Simplicidade operacional
A operação deve ser direta. O usuário deve conseguir trabalhar sem precisar “interpretar o sistema”.

### 3.3 Governança sobre improviso
Toda funcionalidade deve ter comportamento previsível, auditável e coerente.

### 3.4 Incrementalismo
O sistema deve evoluir por camadas, sem reescrita total desnecessária.

### 3.5 Baixo atrito para o time interno
A interface e os fluxos devem reduzir retrabalho, busca manual de informações e dispersão entre ferramentas.

### 3.6 Coerência sistêmica
O mesmo dado deve obedecer à mesma regra em tela, API, busca, exportação e integração.

## 4. Usuários do sistema
Aguardando mapeamento de usuários, mas é notável e importante também que:
### 4.1 Perfis intermediários futuros
O sistema deve permitir evolução para papéis mais granulares, mesmo que o modelo inicial seja mais simples.

## 5. Entidades centrais do produto
As entidades abaixo são centrais para o produto e exigem coerência funcional:
- usuários;
- perfis/papéis/permissões;
- leads;
- contatos;
- clientes;
- empresas;
- oportunidades;
- observações;
- atividades;
- mensagens;
- canais;
- histórico de eventos;
- integrações.

## 6. Regras gerais de experiência do produto
### 6.1 Toda informação exibida deve ser intencional
Nenhum dado deve aparecer apenas porque “já estava vindo no payload”.

### 6.2 O sistema deve reduzir exposição desnecessária
Se o usuário precisa operar sem ver o valor integral, o sistema deve mascarar.

### 6.3 O histórico deve ser útil
Logs ou timelines sem contexto operacional não agregam valor.  
O histórico deve responder:
- o que aconteceu;
- quando;
- com quem;
- por quem;
- em qual entidade;
- com qual impacto.

### 6.4 A integração deve parecer parte do CRM
O WhatsApp integrado não deve parecer ferramenta externa “pendurada”.

## 7. Primeira melhoria real prioritária
A primeira melhoria real prioritária é:

# Ocultação de dados sensíveis por perfil
Essa funcionalidade é obrigatória para elevar o nível de maturidade do sistema.

### 7.1 Objetivo funcional
Garantir que usuários sem concessão individual não tenham acesso integral a dados sensíveis. Acesso administrativo ou contextual não substitui automaticamente essa concessão.

### 7.2 Resultado esperado
- usuários com concessão individual veem o valor integral;
- usuários comuns veem valor mascarado ou nenhum valor;
- a regra vale em todas as superfícies aplicáveis;
- o sistema permanece operacional para o usuário comum.

### 7.3 Requisito de produto
A solução deve equilibrar:
- segurança;
- utilidade operacional;
- legibilidade de interface;
- consistência sistêmica.

## 8. Escopo funcional da ocultação de dados sensíveis
Essa regra precisa alcançar, no mínimo:
- listagens;
- telas de detalhe;
- formulários de edição/visualização;
- buscas;
- filtros;
- autocomplete;
- APIs;
- exportações;
- relatórios;
- logs e trilhas, quando aplicável;
- integrações e payloads internos expostos a usuário.

## 9. Comportamentos esperados para dados sensíveis
### 9.1 Ver
O usuário só vê o que seu perfil permite.
### 9.2 Buscar
O usuário não deve descobrir valor integral por mecanismos de busca se não tiver permissão.
### 9.3 Exportar
O usuário não deve exportar valor integral se não tiver permissão.
### 9.4 Inferir
O sistema não deve permitir inferência trivial de valor protegido por UI, API ou autocomplete.

## 10. Diretriz de produto para mascaramento
O mascaramento deve ser:
- consistente;
- legível;
- útil para contexto;
- insuficiente para exposição integral;
- centralizado como padrão de produto.

Exemplo de intenção:
- ver que existe um telefone sem ver o número completo (Ex: 11 9XXXX-XX18 | 48 2XXX-XX20);
- identificar o contato certo sem expor e-mail completo (Ex: `le**********@outlook.com`);
- Verificar documentos (Ex: CPF: 460-XXX-XXX.79 | RG: 56.XXX.XXX-7);
- permitir conferência operacional sem liberar o dado integral.

## 11. Regras para módulo de comunicação/WhatsApp
O módulo de WhatsApp é prioridade alta após a base de dados sensíveis.
### 11.1 Objetivo funcional
Permitir atendimento e histórico conversacional dentro do CRM.

### 11.2 Requisitos de produto
- vincular conversa a contato/lead/cliente;
- exibir histórico em contexto;
- permitir resposta operacional;
- registrar eventos relevantes;
- Integrar com o funil do CRM;
- manter segurança e controle por perfil.

### 11.3 Propriedade e visibilidade
- o dono do Lead é a fonte de verdade para acesso à conversa vinculada;
- administradores podem consultar todas as conversas;
- conversas sem responsável ficam restritas a administradores;
- transferências de Lead devem atualizar o acesso ao chat de forma atômica;
- usuários responsáveis leem o conteúdo operacional, mas dados detectados como sensíveis continuam protegidos.

### 11.4 Histórico operacional
- cada mensagem permanece no histórico do chat, sem virar uma Activity duplicada;
- a primeira mensagem enviada por usuário do CRM abre uma Activity de Atendimento WhatsApp;
- mensagens reais renovam a janela de 24 horas;
- após inatividade, a Activity é encerrada e pode receber relato textual;
- nova mensagem enviada pelo usuário do CRM abre Atendimento Continuado;
- reações e eventos técnicos não abrem nem renovam atendimento.

### 11.5 Sessões
- o CRM deve descobrir, importar e criar múltiplas sessões OpenWA;
- uma sessão padrão atende novas conversas;
- cada conversa permanece vinculada à sessão escolhida.

### 11.6 Regra crítica
Mensagens e metadados também devem obedecer a visibilidade por perfil quando aplicável.

## 12. Benchmark com Evo CRM
O Evo CRM será referência para:
- organização de fluxos conversacionais;
- acoplamento entre contatos e conversas;
- experiência de atendimento;
- padrão de mensageria e histórico;
- inspiração para evolução funcional.

O Evo não deve ser tratado como modelo a ser copiado integralmente.

## 13. Critérios de aceite de produto
Uma funcionalidade só está pronta se:
1. resolve o problema real;
2. está coerente com as regras de segurança;
3. funciona de forma consistente;
4. não cria vazamento por rota paralela;
5. não exige conhecimento oculto do operador;
6. é sustentável para manutenção futura.

## 14. Critérios de priorização
Ao decidir o que entra antes, priorizar:
1. segurança e controle de acesso;
2. estabilidade do fluxo central;
3. visibilidade correta de dados;
4. histórico operacional;
5. integrações críticas;
6. refinamentos de UX.

## 15. O que não é prioridade agora
Neste estágio, não são prioridade:
- reescrita ampla da interface;
- migração total de arquitetura;
- microservicização do Krayin;
- refatoração estética massiva;
- recursos cosméticos sem impacto operacional;
- múltiplos canais simultâneos antes de consolidar WhatsApp.

## 16. Definição de pronto para melhorias estratégicas
Uma melhoria estratégica só deve ser considerada concluída se entregar:
- comportamento funcional coerente;
- segurança consistente;
- impacto documentado;
- testes recomendados ou executados;
- registro no changelog técnico.

## 17. Regra final
Se houver conflito entre “ficar bonito rápido” e “funcionar com segurança e coerência”, a segunda opção sempre prevalece.
