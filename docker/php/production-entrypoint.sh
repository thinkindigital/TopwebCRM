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

for variable in APP_KEY DB_PASSWORD MAIL_PASSWORD ADMIN_PASSWORD; do
    load_secret "$variable"
done

run_as_web_user() {
    if [ "$(id -u)" = "0" ]; then
        gosu www-data "$@"
    else
        "$@"
    fi
}

wait_for_database() {
    timeout="${DB_WAIT_TIMEOUT:-120}"
    elapsed=0

    until php -r '
        try {
            new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    getenv("DB_HOST"),
                    getenv("DB_PORT") ?: "3306",
                    getenv("DB_DATABASE")
                ),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD")
            );
        } catch (Throwable) {
            exit(1);
        }
    '; do
        if [ "$elapsed" -ge "$timeout" ]; then
            echo "Database was not ready after ${timeout}s." >&2
            exit 1
        fi

        sleep 5
        elapsed=$((elapsed + 5))
    done
}

mkdir -p \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ "${WAIT_FOR_DATABASE:-false}" = "true" ]; then
    wait_for_database
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    run_as_web_user php artisan migrate --force --no-interaction
fi

if [ "${RUN_INITIAL_INSTALL:-false}" = "true" ] && [ ! -f storage/installed ]; then
    run_as_web_user php artisan db:seed \
        --class='Webkul\Installer\Database\Seeders\DatabaseSeeder' \
        --force \
        --no-interaction
    run_as_web_user php /usr/local/share/topwebcrm/initialize-production.php
    run_as_web_user php artisan storage:link --force
    run_as_web_user php artisan optimize:clear
fi

unset ADMIN_PASSWORD ADMIN_PASSWORD_FILE

if [ "$(id -u)" = "0" ] && [ "${1:-}" = "php" ]; then
    exec gosu www-data "$@"
fi

exec "$@"
