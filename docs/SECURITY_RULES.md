Não faça refatoração estética. Não renomeie sem necessidade. Não reorganize estrutura inteira. Foque só no objetivo.

## 1. Finalidade
Este documento define regras normativas de segurança para toda alteração no TopwebCRM.
Nenhuma implementação pode contrariar este documento sem justificativa explícita.

## 2. Princípios obrigatórios
1. **Menor privilégio**
2. **Negação por padrão**
3. **Exposição mínima**
4. **Centralização de autorização**
5. **Mascaramento consistente**
6. **Auditoria quando necessário**
7. **Proteção em todas as superfícies**
8. **Segurança antes de conveniência**

## 3. Definição de dado sensível
Dado sensível, neste projeto, é todo dado que:
- exponha informação privada do cliente/lead/contato/empresa;
- permita contato direto indevido;
- tenha relevância financeira, estratégica ou contratual;
- possa gerar risco comercial, jurídico ou operacional;
- possa ser explorado por usuários internos fora do perfil adequado.

## 4. Classes de sensibilidade
### 4.1 Pública interna
Pode ser vista por usuários autenticados do domínio adequado.
Exemplos:
- nome comercial;
- estágio operacional permitido;
- dados básicos não restritos.

### 4.2 Restrita
Pode ser vista por perfis com permissão específica.
Exemplos:
- telefone;
- e-mail;
- documento;
- endereço;
- observações estratégicas;
- origem de lead quando necessário.

### 4.3 Altamente sensível
Deve ser vista apenas por administradores ou perfis explicitamente autorizados.
Exemplos:
- documento completo;
- telefone completo;
- e-mail completo;
- dados financeiros;
- credenciais;
- tokens;
- integrações;
- anotações sigilosas;
- chaves de API;
- metadados sensíveis de conversação.

## 5. Regras gerais de visualização
### 5.1 Admin
- pode acessar os registros do seu escopo administrativo;
- só vê dado sensível integral quando a concessão individual ou uma exceção contextual documentada autorizar;
- nunca recebe credenciais ou segredos de integração no navegador.

### 5.2 Usuário comum
- não deve ver dado integral altamente sensível;
- quando aplicável, deve ver versão mascarada;
- quando não aplicável, não deve ver o campo;
- não deve conseguir recuperar o valor por inspeção indireta.

### 5.3 Regra obrigatória
A decisão de exibir, mascarar ou ocultar não pode existir só na camada visual.

## 6. Superfícies obrigatórias de proteção
Toda regra de sensibilidade deve cobrir:

- listagens;
- detalhes;
- formulários;
- APIs;
- resources/transformers/serializers;
- buscas;
- autocomplete;
- exportações;
- importações, quando afetarem leitura posterior;
- logs;
- notificações;
- webhooks;
- cache;
- relatórios.

## 7. Regras de mascaramento
### 7.1 Objetivo
O mascaramento serve para operação parcial sem exposição integral.

### 7.2 Regras
- o padrão deve ser centralizado;
- o formato deve ser consistente;
- o valor original não deve “vazar” por atributo alternativo;
- mascaramento não substitui autorização.

### 7.3 Exemplos de padrão
- telefone: últimos 2 a 4 dígitos visíveis, conforme regra definida;
- e-mail: mostrar apenas parte local e domínio parcial;
- documento: apenas trecho final;
- endereço: resumo parcial, se necessário.

## 8. Regras de autorização
### 8.1 Obrigação
Toda autorização deve ser preferencialmente centralizada em:
- policies;
- gates;
- middlewares;
- serviço próprio de autorização;
- escopos de leitura seguros.

### 8.2 Proibição
É proibido depender apenas de:
- ifs repetidos em blade;
- checagem visual isolada;
- esconder coluna sem tratar backend;
- bloqueio apenas em JavaScript.

## 9. Exportações
### 9.1 Regra
Exportações são superfícies críticas de vazamento.

### 9.2 Obrigação
Toda exportação deve:
- verificar permissão;
- respeitar mascaramento ou exclusão de campos;
- registrar auditoria quando apropriado;
- impedir exportação integral por usuários não autorizados.

## 10. Buscas, filtros e autocomplete
### 10.1 Regra
Não basta ocultar o campo na tela se o usuário ainda consegue:
- buscar pelo valor;
- descobrir existência do valor;
- inferir valor por autocomplete;
- receber o valor em payload.

### 10.2 Obrigação
Busca e autocomplete devem obedecer às mesmas regras de visibilidade.

## 11. APIs e integrações
### 11.1 APIs internas/externas
Toda API deve aplicar os mesmos critérios de autorização e visibilidade.

### 11.2 Webhooks
- validar origem quando aplicável;
- registrar falhas;
- não expor segredos em logs;
- sanitizar payloads quando necessário.

### 11.3 Tokens e chaves
- nunca expor em resposta de usuário comum;
- nunca logar valor completo sem necessidade extrema;
- manter em env/config segura.

## 12. Logs e observabilidade
### 12.1 Regras
- não registrar payload sensível integral sem justificativa;
- preferir logs estruturados;
- mascarar campos críticos;
- distinguir erro operacional de dado de negócio;
- registrar falhas de autorização relevantes;
- registrar eventos críticos de integração.

Issues, Pull Requests e comentários públicos devem seguir a política de sanitização e encerramento definida em `docs/agents/issue-tracker.md`.

## 13. Auditoria
Auditoria é recomendada para:
- visualização de dado altamente sensível;
- exportação;
- alteração de permissões;
- mudança de integração;
- acesso administrativo excepcional;
- ações de mensageria relevantes.

## 14. Módulo de WhatsApp
### 14.1 Dados sensíveis adicionais
Considerar sensíveis:
- conteúdo de mensagens;
- mídia;
- telefone completo;
- identificadores externos;
- status e metadados;
- tokens do provedor;
- webhooks brutos.

### 14.2 Regras
- acesso à conversa vinculado ao dono do Lead;
- administradores veem todas as conversas e conversas sem responsável;
- usuários comuns não acessam conversas sem responsável;
- histórico rastreável;
- anexos controlados;
- provedor desacoplado;
- falha/retry seguros;
- cuidado extra com logs.

### 14.3 Conteúdo e anexos
- o responsável pelo Lead pode ler o conteúdo operacional da conversa;
- documentos, telefones, e-mails e cartões de contato detectados continuam sujeitos a mascaramento;
- sem análise de conteúdo, todo anexo de documento é sensível para usuário sem concessão;
- imagens não são classificadas automaticamente nesta fase;
- autorização deve ser verificada na rota de download, não apenas na interface;
- histórico importado não pode criar Pessoa nem Activity silenciosamente.

## 15. Critérios mínimos de segurança por implementação
Toda mudança relevante deve responder:
1. Onde o dado entra?
2. Onde o dado é salvo?
3. Onde o dado é exibido?
4. Onde o dado pode vazar?
5. Quem pode ver?
6. Quem pode exportar?
7. Quem pode buscar?
8. Como auditar?
9. Como testar?
10. Como reverter?

## 16. Regra de bloqueio
Se uma implementação cumprir UX mas não cumprir segurança em backend, API, export ou busca, ela deve ser considerada **incompleta** e não concluída.
