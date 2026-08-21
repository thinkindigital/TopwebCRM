# Documentacao do TopwebCRM

Este indice define onde cada tipo de informacao deve viver. Um comportamento so pode ser descrito como implementado quando estiver confirmado no codigo e coberto por verificacao reproduzivel.

## Ordem de autoridade

1. Codigo, migrations, configuracao e testes executaveis.
2. ADRs aceitos e nao superados.
3. `CONTEXT.md`, `docs/PRODUCT_RULES.md` e `docs/SECURITY_RULES.md`.
4. Arquitetura, mapas e documentacao do modulo.
5. Runbooks operacionais.
6. `ORCHESTRATOR-ROADMAP.md` e GitHub Issues.
7. Historico e referencias externas fixadas.

## Documentos canonicos

| Assunto | Documento |
|---|---|
| Protocolo dos agentes e skills | `AGENTS.md` |
| Linguagem do dominio | `CONTEXT.md` |
| Regras de produto | `docs/PRODUCT_RULES.md` |
| Regras de seguranca | `docs/SECURITY_RULES.md` |
| Arquitetura geral | `docs/ARCHITECTURE.md` |
| Mapa do codigo | `docs/SYSTEM_MAP.md` |
| TopwebChat | `docs/topweb-chat/README.md` |
| Contrato OpenWA usado pelo CRM | `docs/topweb-chat/OPENWA.md` |
| Operacao do TopwebChat | `docs/topweb-chat/OPERATIONS.md` |
| Desenvolvimento local | `docs/operations/LOCAL_DEVELOPMENT.md` |
| Deploy e rollback | `docs/operations/DEPLOYMENT.md` |
| Decisoes arquiteturais | `docs/adr/` |
| Tracker e triage | `docs/agents/` |
| Planejamento estrategico | `ORCHESTRATOR-ROADMAP.md` |

## Estados documentais

- **Implementado**: confirmado no codigo atual e verificavel.
- **Decidido**: contrato aprovado, ainda que a implementacao esteja pendente.
- **Planejado**: escopo de roadmap ou Issue, sem garantia de disponibilidade.
- **Historico**: registro que nao descreve o comportamento atual.
- **Referencia externa**: material de outro projeto, sem autoridade sobre o TopwebCRM.

## Regras de manutencao

- Nao copiar catalogos completos de APIs externas para `docs/`.
- Nao registrar progresso em varios documentos; o GitHub Issue e a fonte detalhada e o roadmap e o resumo.
- Nao usar task concluida ou changelog como especificacao atual.
- Atualizar documentacao e testes no mesmo slice da mudanca funcional.
- Registrar em ADR apenas decisoes dificeis de reverter, surpreendentes e resultantes de trade-off real.
