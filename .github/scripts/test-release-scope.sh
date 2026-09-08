#!/usr/bin/env bash
# Testa .github/scripts/release-scope.sh (issue #45).
# Uso: bash .github/scripts/test-release-scope.sh
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CLASSIFIER="$SCRIPT_DIR/release-scope.sh"
fail=0

check() { # $1=descricao $2=esperado $3...=arquivos
    local desc="$1" expected="$2"
    shift 2
    local got=""
    got="$(printf '%s\n' "$@" | bash "$CLASSIFIER")"
    if [ "$got" = "$expected" ]; then
        echo "ok - $desc ($got)"
    else
        echo "FALHOU - $desc (esperado $expected, obteve $got)"
        fail=1
    fi
}

# Caso docs-only: só markdown/docs.
check "docs-only" "docs-only" \
    "docs/operations/DEPLOYMENT.md" \
    "ORCHESTRATOR-ROADMAP.md" \
    "README.md"

# Caso runtime: qualquer arquivo fora de docs dispara publish.
check "runtime (php)" "runtime" \
    "docs/README.md" \
    "packages/Webkul/TopwebChat/src/Jobs/SendMessage.php"

# Caso runtime: manifest raiz afeta deploy e exige imagem? Não — mas código sim.
check "runtime (workflow)" "runtime" \
    ".github/workflows/ci.yml"

# Caso runtime: compose de produção sozinho não muda a imagem, mas muda o deploy;
# decisão conservadora: publica (nunca sub-publicar).
check "runtime (compose)" "runtime" \
    "compose.production.yaml"

exit "$fail"
