#!/usr/bin/env bash
# Classifica escopo de release a partir da lista de arquivos (stdin, um por linha).
# Saída: "docs-only" (só markdown/docs) ou "runtime" (qualquer outro arquivo).
# Usado por publish-production-image.yml (issue #45). Conservador por desenho:
# na dúvida, publica (nunca sub-publicar).
set -euo pipefail

runtime=0
while IFS= read -r file || [ -n "$file" ]; do
    [ -z "$file" ] && continue
    case "$file" in
        *.md|docs/*) ;;
        *) runtime=1; break ;;
    esac
done

if [ "$runtime" = "1" ]; then
    echo "runtime"
else
    echo "docs-only"
fi
