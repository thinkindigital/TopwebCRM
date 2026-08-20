# Desenvolvimento Local

O TopwebCRM usa o Krayin CRM `2.2`, PHP 8.3 e MySQL 8. O ambiente local roda em Docker para não exigir PHP ou Composer instalados no Windows.

## Primeira instalação

```powershell
docker compose build app
docker compose up -d mysql
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan krayin-crm:install --skip-env-check --skip-admin-creation
docker compose up -d app
```

Acesse `http://localhost:8000/admin/login` com:

- E-mail: `admin@example.com`
- Senha: `admin123`

## Uso diário

```powershell
docker compose up -d
docker compose logs -f app
docker compose down
```

O MySQL é exposto opcionalmente em `localhost:3307`. Os dados permanecem no volume `topwebcrm_mysql` após `docker compose down`.

## Comandos úteis

```powershell
docker compose exec app php artisan test --compact
docker compose exec app ./vendor/bin/pint --test
docker compose exec app php artisan optimize:clear
```

O instalador executa `migrate:fresh`. Não o rode novamente sobre um banco que contenha dados importantes.

## Imagem de produção

O Dockerfile atual é exclusivo do ambiente local com bind mount. Consulte `docs/DOCKER_IMAGE_BUILD.md` antes de criar ou publicar a imagem personalizada para Portainer.
