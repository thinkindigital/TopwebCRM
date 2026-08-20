# Issue Tracker: Local Markdown (.scratch/)

Issues e PRDs deste repositório vivem como arquivos markdown em `.scratch/`.

## Convenções

- Uma feature por diretório: `.scratch/<feature-slug>/`
- PRD: `.scratch/<feature-slug>/PRD.md`
- Issues de implementação: `.scratch/<feature-slug>/issues/<NN>-<slug>.md`, numerados a partir de `01`
- Estado de triage registrado como linha `Status:` no topo de cada issue (ver `triage-labels.md`)
- Comentários e histórico de conversa anexados ao final do arquivo sob heading `## Comments`

## Quando uma skill diz "publicar no issue tracker"

Criar novo arquivo em `.scratch/<feature-slug>/` (criar diretório se necessário).

## Quando uma skill diz "buscar ticket relevante"

Ler arquivo no caminho referenciado. Usuário normalmente passa o caminho ou número da issue diretamente.