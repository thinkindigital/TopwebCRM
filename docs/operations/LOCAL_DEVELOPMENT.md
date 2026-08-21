# Desenvolvimento local

## Servicos

- TopwebCRM: `http://127.0.0.1:8000`
- OpenWA: `http://localhost:2785`
- MySQL do Compose: host `3307`, container `3306`
- Redis do Compose: host `6380`, container `6379`

O OpenWA e um servico separado e nao faz parte do `compose.yaml` do TopwebCRM. A conectividade e as credenciais devem ser configuradas explicitamente.

## Instalacao

1. Criar `.env` a partir de `.env.example` e definir banco, Redis, URL e credenciais locais.
2. Instalar dependencias Composer e Node conforme os manifests do checkout.
3. Executar `php artisan krayin-crm:install` sem pular a criacao administrativa, salvo quando a conta for criada por outro procedimento documentado.
4. Executar migrations, build de assets e verificacao de `/up`.
5. Iniciar worker e scheduler quando testar TopwebChat.

## Verificacao minima

```bash
php artisan about
php artisan migrate:status
php artisan route:list --name=topweb_chat
php artisan schedule:list
php artisan test
```

Nao declare a instalacao reproduzivel enquanto `migrate:fresh` ou o instalador oficial, seed e testes nao passarem em banco limpo.

## Dados locais

Nao versionar `.env`, bancos SQLite, `vendor/`, `node_modules/`, `storage/`, `public/storage` ou assets gerados fora do processo de release.

Nao alterar PHP global, Scoop ou PATH para corrigir o projeto sem autorizacao explicita.
