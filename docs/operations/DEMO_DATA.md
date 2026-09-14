---
doc_id: demo-data
type: runbook
status: active
authority: operational
scope: demo
last_verified_commit: 205c296
update_triggers: [demo-command, demo-schema, demo-data]
related: [docs/modules/topweb-chat/README.md]
---

# Dados Demo Comerciais

Para preencher o CRM para apresentação comercial sem apagar acessos existentes, use o importador CSV aditivo:

```bash
php artisan topwebchat:import-real-estate-demo --user-email=admin@exemplo.com
```

O CSV padrão fica em `packages/Webkul/TopwebChat/src/Database/demo/real-estate-demo.csv`.

O comando preserva `users` e `roles`; ele cria ou atualiza registros por chaves naturais em pipelines, etapas, fontes, tipos, produtos, pessoas e leads.

Para importar outro arquivo:

```bash
php artisan topwebchat:import-real-estate-demo /caminho/arquivo.csv --user-email=admin@exemplo.com
```

Se `--user-email` não for informado, o comando usa o primeiro usuário ativo como dono dos leads importados.
