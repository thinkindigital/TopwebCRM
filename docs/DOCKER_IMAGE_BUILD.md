# Build da Imagem Docker do TopwebCRM

## Estado confirmado

O repositório possui `compose.yaml` e `docker/php/Dockerfile` para desenvolvimento local. A imagem atual contém PHP 8.3 CLI, extensões necessárias e Composer, mas **não copia o código, dependências Composer, `.env` ou assets para dentro da imagem**. O Compose completa o ambiente com o bind mount `./:/var/www/html` e inicia `php artisan serve`.

Consequentemente, a imagem atual é uma base de desenvolvimento e **não deve ser publicada como imagem final do Portainer**.

A imagem imutável está em `docker/php/Dockerfile.production`, e a stack Swarm/Portainer operacional está em `compose.production.yaml`. O destino usa a infraestrutura do Setup Orion descrita em `docs/SETUP_ORION.md`.

## Build local atual

```powershell
docker compose build --pull app
docker compose up -d mysql
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose up -d app
docker compose ps
```

Para reconstruir sem cache:

```powershell
docker compose build --pull --no-cache app
```

Valide em `http://localhost:8000/up` e `http://localhost:8000/admin/login`. Esse fluxo continua dependente dos arquivos do host.

## Imagem de produção

O Dockerfile de produção:

1. usa PHP 8.3 com Apache em `8080`;
2. instala dependências Composer sem pacotes de desenvolvimento;
3. copia código, `vendor/` e os manifests versionados em `public/`;
4. exclui `.env*`, Git, testes, docs e caches pelo `.dockerignore`;
5. executa como `www-data`;
6. restringe o document root a `public/` e nega acesso ao storage privado;
7. fornece healthcheck em `/up`;
8. é reutilizada por app, worker e scheduler.

O repositório ainda não versiona `package-lock.json` nos quatro builds Vite. Portanto, a imagem usa os manifests compilados e versionados existentes. Antes de alterar JavaScript/CSS, compile root, Admin, Installer e WebForm no ambiente de release e confirme os quatro manifests. A evolução recomendada é versionar lockfiles e migrar o build frontend para um estágio Node reproduzível.

## Versionamento e publicação

Use tags imutáveis, preferencialmente versão mais commit curto:

```text
registry.example.com/topwebcrm/topwebcrm:0.1.0-abc1234
registry.example.com/topwebcrm/topwebcrm:0.1.0
```

```powershell
docker build --pull --file docker/php/Dockerfile.production --tag registry.example.com/topwebcrm/topwebcrm:0.1.0-abc1234 .
docker image inspect registry.example.com/topwebcrm/topwebcrm:0.1.0-abc1234
docker push registry.example.com/topwebcrm/topwebcrm:0.1.0-abc1234
```

Nunca use apenas `latest` como referência de rollback.

## Configuração no Portainer EE

A stack deverá separar responsabilidades usando a mesma tag de imagem:

- **app:** processo HTTP;
- **queue:** `php artisan queue:work` com timeout, tentativas e reinício controlados;
- **scheduler:** `php artisan schedule:work` ou disparo externo de `schedule:run`;
- **database:** MySQL dedicado e persistente;
- **cache/queue:** Redis dedicado e persistente;
- **proxy:** Traefik e rede overlay externa fornecidos pelo Setup Orion.

Crie os secrets externos antes do deploy:

```bash
printf '%s' 'base64:CHAVE_GERADA' | docker secret create topwebcrm_app_key -
printf '%s' 'SENHA_FORTE' | docker secret create topwebcrm_db_password -
printf '%s' 'OUTRA_SENHA_FORTE' | docker secret create topwebcrm_db_root_password -
printf '%s' 'SENHA_SMTP' | docker secret create topwebcrm_mail_password -
```

Antes do deploy, crie a label do nó e os volumes externos conforme `docs/SETUP_ORION.md`. Altere domínio, registry, SMTP e rede externa por variáveis do Portainer. Depois:

```bash
docker stack config -c compose.production.yaml
docker stack deploy -c compose.production.yaml topwebcrm
```

O entrypoint lê `APP_KEY_FILE`, `DB_PASSWORD_FILE` e `MAIL_PASSWORD_FILE`. Preserve a mesma `APP_KEY` em app, worker, scheduler e releases: tokens Ryze, segredo do webhook, JIDs e payloads estão criptografados por ela.

Não copie os valores apresentados em stacks antigas. As credenciais compartilhadas nesta conversa devem ser consideradas expostas e rotacionadas antes do deploy.

Volumes persistentes precisam cobrir uploads públicos e privados. A stack preserva somente `/var/www/html/storage/app`; anexos sensíveis ficam em `storage/app/private`. Não monte um volume em `/var/www/html`, pois ele ocultaria o código da imagem e impediria upgrades confiáveis.

Em Swarm, volume local não é compartilhado entre nós. A stack de referência fixa os serviços no manager; para escalar, substitua por NFS, CSI, S3 ou outro storage compartilhado.

## Procedimento de release

1. Criar uma tag de aplicação e registrar o commit de origem.
2. Executar Pest, Pint e build de assets.
3. Gerar a imagem de produção sem usar `.env` local.
4. Inspecionar camadas e confirmar ausência de segredos.
5. Subir a imagem no registry com tag imutável.
6. Fazer backup do banco e do volume de uploads.
7. Atualizar a stack do Portainer para a nova tag.
8. Executar migrations uma única vez, em tarefa controlada.
9. Executar `php artisan optimize:clear` e preparar caches compatíveis com o ambiente.
10. Validar `/up`, login, dashboard, filas, scheduler, uploads e downloads privados.

O entrypoint suporta migrations somente no serviço app e elas ficam desabilitadas por padrão. Na fase de migration, mantenha queue e scheduler com zero réplicas; depois desabilite `TOPWEBCRM_RUN_MIGRATIONS` e ative os processos assíncronos.

Para instalações com anexos anteriores à proteção privada:

```bash
php artisan sensitive-data:migrate-attachments --dry-run
php artisan sensitive-data:migrate-attachments
```

O segundo comando exige revisão prévia do relatório.

## Rollback

Mantenha a tag anterior disponível. Em falha:

1. interrompa novas migrations ou jobs;
2. restaure banco somente se a migration não for retrocompatível;
3. selecione a tag anterior no Portainer;
4. preserve `storage/app` durante a troca de imagem;
5. valide `/up`, login e operações essenciais.

## Checklist de aceite da imagem

- código e dependências estão dentro da imagem;
- `.env` e tokens não estão nas camadas;
- assets foram compilados e o manifest existe;
- processo não roda como root;
- healthcheck responde;
- uploads privados não são servidos diretamente;
- app, worker e scheduler usam a mesma versão;
- migrations e rollback foram ensaiados;
- a stack não depende de bind mount do repositório.
