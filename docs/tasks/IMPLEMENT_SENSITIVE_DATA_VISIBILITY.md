## Objetivo
Implementar no TopwebCRM a primeira melhoria estratégica real:

# Visibilidade de dados sensíveis por perfil
O sistema deve permitir que administradores visualizem dados integrais e que usuários comuns visualizem apenas dados mascarados ou ocultos, conforme a regra definida.

---
## Instrução principal
Você deve analisar e implementar esta funcionalidade de forma segura, incremental e rastreável.

Não faça solução superficial de interface.  
A implementação deve cobrir a regra de negócio de forma consistente.

---
## Leitura obrigatória antes de agir
Leia nesta ordem:
1. `AGENTS.md`
2. `docs/AI_CONTEXT.md`
3. `docs/ARCHITECTURE.md`
4. `docs/SECURITY_RULES.md`
5. `docs/PRODUCT_RULES.md`
6. `docs/tasks/MAP_KRAYIN_CRM.md`
7. qualquer documentação local do módulo afetado

Se algum desses arquivos não existir, declarar explicitamente.

---
## Resultado funcional esperado

### Admin
- vê o valor integral dos campos sensíveis autorizados.

### Usuário comum
- vê valor mascarado ou nenhum valor, conforme a natureza do campo e a regra definida.

### Regra obrigatória
Essa proteção precisa valer em múltiplas superfícies e não apenas em blade/view.

---
## Objetivos técnicos
Entregar uma solução que:
1. centralize a regra de visibilidade;
2. minimize duplicação;
3. preserve o comportamento já estável do Krayin;
4. cubra superfícies críticas;
5. permita expansão futura para novos perfis/permissões;
6. seja documentável e auditável.

---
## Escopo mínimo obrigatório
A implementação deve cobrir, no mínimo, os seguintes pontos onde os dados sensíveis aparecerem:
- listagens;
- telas de detalhe;
- formulários;
- APIs;
- resources/serializers/transformers;
- busca;
- filtros;
- autocomplete;
- exportações;
- relatórios, se existentes;
- logs/payloads expostos ao operador, quando aplicável.

---
## Tarefa em fases
### Fase 1 — análise confirmatória
Antes de alterar qualquer arquivo, entregar:
1. campos sensíveis identificados;
2. entidades afetadas;
3. arquivos reais afetados;
4. superfície de exposição por campo;
5. modelo atual de autorização encontrado no Krayin;
6. proposta de arquitetura para a solução;
7. ordem de implementação;
8. riscos.

### Fase 2 — base de autorização/visibilidade
Implementar a base central da decisão de visibilidade, preferencialmente usando os mecanismos mais adequados encontrados no Krayin, como:
- policies;
- gates;
- services;
- presenters;
- traits;
- resources;
- scopes;
- middlewares;
- abstrações equivalentes.

### Fase 3 — saída segura
Aplicar a regra em:
- visualizações;
- APIs;
- listagens;
- detalhes;
- demais saídas relevantes.

### Fase 4 — superfícies paralelas
Aplicar a regra em:
- exportações;
- busca;
- autocomplete;
- filtros;
- payloads auxiliares.

### Fase 5 — revisão
Revisar a implementação procurando:
- vazamento residual;
- duplicação de lógica;
- quebra de UX;
- quebra de consulta;
- regressão operacional.

### Fase 6 — documentação
Registrar no `docs/CHANGELOG_AI.md` com base no modelo instituído:
- arquivos alterados;
- estratégia adotada;
- riscos remanescentes;
- próximos passos no `docs/FUTURE.md`.

---
## Campos sensíveis iniciais a procurar
Mapear e classificar, no mínimo, se existirem:
- telefone;
- celular;
- e-mail;
- documento pessoal;
- documento empresarial;
- endereço;
- observações internas sensíveis;
- origem de lead, quando sensível;
- metadados de integração;
- identificadores externos;
- outros campos equivalentes encontrados no código.

Se houver diferença entre nome funcional e nome real da coluna/campo, registrar ambos.

---
## Regra de decisão funcional
Para cada campo sensível, definir uma destas saídas:
1. **mostrar integral**
2. **mostrar mascarado**
3. **ocultar totalmente**

Essa decisão deve ser justificada por:
- valor operacional;
- risco de exposição;
- perfil do usuário;
- superfície em que o dado aparece.

---
## Restrições obrigatórias
- Não fazer apenas ocultação visual em frontend.
- Não espalhar `if admin` no sistema.
- Não duplicar regra em múltiplas telas sem centralização.
- Não assumir estrutura do Krayin sem verificar.
- Não criar dependência nova sem forte justificativa.
- Não fazer refatoração ampla não relacionada.
- Não alterar comportamentos periféricos sem documentar impacto.

---
## Riscos obrigatórios a verificar
Verificar e reportar risco de vazamento em:
- endpoints;
- payloads AJAX;
- autocomplete;
- exportações;
- grids/listagens;
- relatórios;
- logs;
- notificações;
- serialização;
- cache;
- debug acidental.

---
## Estratégia preferencial
A estratégia preferencial deve combinar:
- decisão central de autorização;
- transformação segura de saída;
- mascaramento padronizado;
- proteção em API/UI/export/search;
- estrutura reaproveitável para outros campos e módulos.

---
## Entregáveis obrigatórios
Ao final, entregar:
1. resumo do problema;
2. arquivos/documentos lidos;
3. arquivos inspecionados;
4. arquivos alterados;
5. explicação da arquitetura aplicada;
6. superfícies cobertas;
7. superfícies ainda pendentes;
8. riscos remanescentes;
9. testes recomendados/executados;
10. atualização do changelog.

---
## Critério de aceite
A tarefa só será considerada concluída se:
- a regra estiver centralizada ou claramente organizada;
- usuários comuns não enxergarem valor integral fora do permitido;
- admins continuarem operando normalmente;
- não houver vazamento óbvio por API, busca, export ou UI paralela;
- a mudança estiver documentada.

---

## Estado da implementação — 2026-07-14
**Status:** implementado e validado no container local.

### Decisão de autorização
- `permission_type=all`: visão integral;
- role customizada com `sensitive_data.view`: visão integral;
- demais roles: saída mascarada ou oculta.

### Operação administrativa
- a permissão aparece na árvore ACL de criação e edição de roles como **Dados sensíveis → Visualizar dados sensíveis completos**;
- a conta recebe a capacidade ao ser associada à role em **Configurações → Usuários**;
- exceções individuais usam uma role customizada dedicada, preservando a role como fonte única de autorização;
- o procedimento completo está em `docs/SENSITIVE_DATA_VISIBILITY.md`.

### Classificação aplicada
- máscara: e-mail, telefone, endereço e atributos documentais (`cpf`, `cnpj`, `rg`, `documento`, `tax_id`, `vat`);
- ocultação: descrição e origem do lead, motivo de perda, atividades, mensagens, anexos e identificadores externos;
- proteção financeira: valor do lead, produtos associados e totais/descontos/impostos de cotações.

### Arquitetura confirmada
- configuração declarativa em `config/sensitive-data.php`;
- decisão, máscaras e sanitização em `app/Services/SensitiveDataService.php`;
- permissão adicionada à árvore ACL no `AppServiceProvider`;
- resources como fronteira principal de API/AJAX;
- grids com campos sensíveis não pesquisáveis, filtráveis, ordenáveis ou exportáveis;
- formulários de edição sem valor cru e backend impedindo overwrite por requisição forjada;
- histórico persistido sem destruição e saída protegida por `ActivityResource`.
- anexos gravados em disco privado, URLs resolvidas por rotas autorizadas e migração legada idempotente por comando Artisan.

### Superfícies cobertas
- contatos, organizações, leads, atividades, e-mails e cotações;
- listagens, detalhes, formulários, autocomplete, busca global e Kanban;
- filtros, ordenação e exportação dos grids;
- dashboard e relatórios financeiros existentes;
- downloads de anexos, PDF/envio de cotações e payloads Vue serializados.
- armazenamento de anexos novos e migração segura dos arquivos antigos do disco público.

### Validação
- teste Pest focado criado em `tests/Feature/SensitiveDataVisibilityTest.php`, incluindo migração do armazenamento público para o privado;
- `git diff --check` executado com sucesso;
- Pest executado com 10 testes e 34 asserções aprovadas;
- Pint aprovado nos 37 arquivos modificados e nos 5 arquivos PHP novos validados explicitamente;
- compilação Blade concluída com `php artisan view:cache`;
- `/up` e `/admin/login` retornaram HTTP 200;
- `docker compose config --quiet` validou o Compose.

### Riscos remanescentes
- webhooks/workflows precisam de política explícita de confiança por integração;
- extensões que usam `view_render_event` podem renderizar modelos crus;
- ownership por registro deve ser auditado separadamente;
- dados legados permanecem integrais no banco e backups;
- anexos legados permanecem publicamente alcançáveis até a execução de `sensitive-data:migrate-attachments` no ambiente que contém os arquivos;
- warehouses e futuros payloads da RyzeAPI devem aderir ao mesmo serviço antes de exposição ao operador.
