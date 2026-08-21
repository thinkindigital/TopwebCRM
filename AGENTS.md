# Protocolo de agentes do TopwebCRM

## Leitura obrigatoria

Antes de analisar ou alterar o projeto, leia nesta ordem:

1. `AGENTS.md`
2. `CONTEXT.md`
3. `docs/README.md`
4. `docs/ARCHITECTURE.md`
5. `docs/SECURITY_RULES.md`
6. `docs/PRODUCT_RULES.md`
7. documentacao canonica do modulo afetado
8. ADRs e GitHub Issues aplicaveis
9. somente entao, codigo, migrations, configuracao e testes

Se um arquivo estiver ausente ou contradisser o codigo, registre a divergencia. A ordem de autoridade esta em `docs/README.md`.

## Regras obrigatorias

- Verifique a estrutura real do Krayin; nao presuma convencoes Laravel.
- Nao invente classes, rotas, tabelas, servicos, permissoes ou pastas.
- Prefira a menor mudanca correta; nao faca refatoracao estetica ou ampla sem necessidade.
- Nao duplique autorizacao ou mascaramento em varias camadas.
- Nao considere protecao somente de frontend como concluida.
- Nao introduza dependencia sem justificativa e verificacao.
- Use Evo CRM somente como referencia funcional e arquitetural.
- Preserve alteracoes locais alheias ao trabalho atual.
- Nao altere Scoop, PHP global, PATH ou servicos do host sem autorizacao explicita.

## Dados sensiveis

Toda mudanca envolvendo Pessoa, Organizacao, Lead, Mensagem ou Activity deve verificar:

- interface e URL direta;
- autorizacao de backend;
- API e Resources;
- busca, filtro e autocomplete;
- exportacao e relatorios;
- arquivos e downloads;
- logs, notificacoes e cache;
- webhooks e integracoes.

Se alguma superficie nao for validada, o trabalho e parcial.

## Integracoes externas

- Mantenha logica do provedor em adapter ou normalizador.
- Controllers apenas validam, autorizam e coordenam.
- Segredos ficam criptografados ou em configuracao segura e nunca retornam ao navegador.
- Use jobs para fluxos assincronos e modele idempotencia, timeout, retry e reconciliacao.
- Nao registre payload sensivel integral em logs.
- Cubra contratos externos com testes reproduziveis.

## GitHub e planejamento

- GitHub Issues sao a fonte persistente de escopo, dependencias e aceite.
- `ORCHESTRATOR-ROADMAP.md` resume Epics com IDs estaveis e links diretos.
- `.scratch/` pode apoiar elaboracao temporaria, mas nao substitui o GitHub.
- Uma Epic so e `done` quando codigo, testes, documentacao e evidencia operacional concordam.
- Ao final de uma DAG, execute a revisao de QA prevista pelo Orchestrator.

## Skills

Use a skill especializada quando a tarefa corresponder ao seu contrato:

- `orchestrator`: governanca, roadmap, Issues, execucao e QA.
- `setup-skills`: artefatos de governanca.
- `roadmap`: Epics e links GitHub.
- `grill-with-docs` ou `grill-feature-with-docs`: linguagem e decisoes antes de implementar.
- `to-issues`: decomposicao em Issues rastreaveis.
- `triage`: estado e preparo das Issues.
- `tdd`: implementacao test-first.
- `diagnose`: bugs e regressoes.
- `secure-e2e`: validacao E2E e de seguranca.
- `qa-analyst`: verificacao final obrigatoria da DAG.
- `query-docs`: contrato atual de bibliotecas externas.
- `improve-codebase-architecture`: melhoria arquitetural orientada ao dominio.

Skills oficiais do Krayin ficam em `.github/skills/`. O catalogo do ambiente nao deve ser copiado para a documentacao do produto.

## Criterio de conclusao

Uma tarefa relevante deve registrar objetivo, arquivos afetados, riscos, verificacoes executadas, pendencias e atualizacoes de documentacao. Nao declare testes, rotas ou comportamentos que nao possam ser reproduzidos no checkout atual.
