#!/bin/sh
set -eu

load_secret() {
    variable="$1"
    file_variable="${variable}_FILE"
    eval "file_path=\${$file_variable:-}"

    if [ -n "$file_path" ]; then
        if [ ! -r "$file_path" ]; then
            echo "Secret file for $variable is not readable." >&2
            exit 1
        fi

        value="$(cat "$file_path")"
        export "$variable=$value"
        unset "$file_variable"
    fi
}

for variable in API_MASTER_KEY API_KEY_PEPPER DATABASE_PASSWORD REDIS_PASSWORD; do
    load_secret "$variable"
done

exec /usr/local/bin/docker-entrypoint.sh "$@"
