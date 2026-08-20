# Referência Oficial do Krayin

## Fontes locais

- `docs/krayincrm/llms-full.txt`: cópia verificada de `https://devdocs.krayincrm.com/llms-full.txt`.

Em 2026-07-16, o arquivo oficial local foi comparado por SHA-256 com sua fonte canônica e estava idêntico.

| Arquivo | SHA-256 |
|---|---|
| `docs/krayincrm/llms-full.txt` | `4E8E1C1734CBE00C598A0B10E719C710B368A852FA28A6ED2E20084C8FA6322B` |

## Uso obrigatório

1. Ler os documentos de inicialização e a tarefa aplicável.
2. Pesquisar no `llms-full.txt` apenas as seções relevantes.
3. Confirmar cada nome e padrão no código do fork antes de implementar.

## Precedência

`código local > docs locais do TopwebCRM > llms-full local > documentação online > hipótese`

O conteúdo oficial descreve padrões recomendados e exemplos. Ele não autoriza inventar arquivos nem copiar literalmente exemplos incompatíveis.

## Divergências conhecidas

- A seção de stack do `llms-full.txt` menciona Laravel 11, enquanto `composer.json` e o `AGENTS.md` upstream da branch 2.2 confirmam Laravel `^12.0`.
- Exemplos do contexto oficial usam `admin`, `app.admin_url` e `bouncer()->can()` em alguns trechos. Este fork deve reconfirmar middleware, config e método reais antes de copiar qualquer exemplo.
- A REST API completa é descrita como pacote opcional `krayin/rest-api`; não presumir seus endpoints instalados apenas porque aparecem na documentação.

## Atualização

Não sobrescrever snapshots silenciosamente. Quando houver atualização:

1. baixar a fonte canônica;
2. comparar hash e diff;
3. revisar divergências com o fork;
4. atualizar este documento e `docs/CHANGELOG_AI.md`;
5. validar novamente agentes e skills.
