# Roadmap do TopwebCRM

O GitHub Issue de cada Epic e a fonte detalhada. Este arquivo resume objetivo, estado e ordem estrategica. IDs `E##` sao estaveis e nunca sao reutilizados.

## Epics

### [**[E01] Fundacao Documental e Governanca**](https://github.com/thinkindigital/TopwebCRM/issues/5) - `in_progress`

Consolidar fontes de autoridade, glossario, ADRs, tracker e documentacao sem duplicidade.

### [**[E02] Visibilidade de Dados Sensiveis**](https://github.com/thinkindigital/TopwebCRM/issues/6) - `in_progress`

Revalidar a protecao em UI, backend, APIs, busca, exportacao, arquivos e midias. Evidencias anteriores nao sao reproduziveis no checkout atual.

### [**[E03] TopwebChat Core OpenWA**](https://github.com/thinkindigital/TopwebCRM/issues/7) - `in_progress`

Substituir os contratos RyzeAPI residuais, tornar sessoes, QR, webhook, envio e recebimento OpenWA funcionais de ponta a ponta e separar a Conta WhatsApp duradoura da Sessao OpenWA descartavel. Slices de ciclo duravel: #68 e #69.

### [**[E04] Infraestrutura de Producao**](https://github.com/thinkindigital/TopwebCRM/issues/8) - `in_progress`

Revalidar imagem, stack, secrets, conectividade OpenWA, healthchecks, backup e rollback.

### [**[E05] Confiabilidade Operacional do Chat**](https://github.com/thinkindigital/TopwebCRM/issues/9) - `in_progress`

Corrigir reconciliacao, historico, estados monotonicos, retry seguro, idempotencia, concorrencia e observabilidade, preservando a timeline entre sessoes da mesma Conta WhatsApp e permitindo importacao manual seletiva por Lead. Slices: #70, #71 e #72.

### [**[E06] Reconciliacao Completa e Dominios Pendentes**](https://github.com/thinkindigital/TopwebCRM/issues/1) - `in_progress`

Implementar quarentena de identidade, midia privada, falhas de webhook e recursos interativos depois da base OpenWA estar funcional.

### [**[E07] Auditoria e Trilha Operacional**](https://github.com/thinkindigital/TopwebCRM/issues/2) - `todo`

Registrar acoes sensiveis, configuracoes, mensagens e atribuicoes em trilha imutavel, consultavel e com retencao definida.

### [**[E08] Melhorias de UX Operacional**](https://github.com/thinkindigital/TopwebCRM/issues/3) - `in_progress`

Entregar Atendimento WhatsApp em Activities, busca autorizada, timeline cronologica e rolavel, Kanban e metricas confiaveis.

### [**[E09] Multi-provider e Evolution API**](https://github.com/thinkindigital/TopwebCRM/issues/4) - `todo`

Adicionar Evolution API somente apos contratos compartilhados e OpenWA estabilizado. Nao manter compatibilidade RyzeAPI.

### [**[E10] Propriedade e Distribuicao de Leads**](https://github.com/thinkindigital/TopwebCRM/issues/10) - `todo`

Aplicar o dono do Lead como fronteira de acesso e, depois, implementar ingestao externa idempotente e roleta concorrente, justa e auditavel. n8n, Meta e Google sao dependencias externas informativas, verificadas somente por seus contratos de API.

### [**[E11] Instalador Automatico TopwebCRM**](https://github.com/thinkindigital/TopwebCRM/issues/49) - `todo`

Instalador guiado estilo SetupOrion para qualquer servidor com Portainer + Traefik: escopo completo parametrizado, imagem publica, SHA do main via API anonima, secrets sem vazamento, deploy via API do Portainer, update e rollback. Slices: #50, #51, #52, #53, #54.

### [**[E12] Instalador Multi-Ferramentas Compartilhadas**](https://github.com/thinkindigital/TopwebCRM/issues/55) - `todo`

Catalogo de ferramentas com resolvedor de dependencias estilo SetupOrion: traefik, portainer (+update), mysql e redis compartilhados (um root, um banco por ferramenta), phpmyadmin; CRM com suporte a DB/Redis externo; apps PHP convencionais fora do Swarm; multi-CRM por sufixo; comandos operacionais + atualizador. Slices: #56, #57, #58, #59, #60, #61, #62, #63, #64.

## Marcos

| Marco | Epics | Saida verificavel |
|---|---|---|
| M1 Governanca reproduzivel | E01, E02, E04 | Docs, seguranca e deploy alinhados a evidencias atuais |
| M2 WhatsApp funcional | E03, E05 | Sessao, QR, envio, recebimento e historico OpenWA testados |
| M3 Seguranca operacional | E06, E07, E10 | Quarentena, midia, auditoria e isolamento por Lead |
| M4 Experiencia integrada | E08 | Atendimento em Activities, busca e metricas |
| M5 Provedores alternativos | E09 | Contrato compartilhado e Evolution validada |

## Ordem de execucao

1. Concluir E01 e restaurar baseline de testes.
2. Executar E03 e E05 em slices verticais pequenos.
3. Implementar a fronteira de autorizacao de E10 antes de expor historico amplo.
4. Executar fundamentos de auditoria de E07.
5. Avancar E06: reconciliacao, quarentena e midia.
6. Integrar Atendimento WhatsApp e UX em E08.
7. Especificar e implementar a roleta de E10.
8. Considerar E09 somente depois da estabilizacao do OpenWA.

## Criterio de done

Uma Epic so pode ser marcada `done` quando:

- criterios do GitHub Issue estao satisfeitos;
- testes e comandos citados existem e passam;
- autorizacao e dados sensiveis foram verificados;
- documentacao canônica reflete o codigo;
- QA final registrou evidencias;
- nao ha dependencia critica pendente.
