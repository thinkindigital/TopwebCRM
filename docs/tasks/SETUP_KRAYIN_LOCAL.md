# Setup Local do Krayin CRM

## Objetivo
Disponibilizar o Krayin CRM 2.2 localmente de forma reproduzível, preservando a documentação e as regras do TopwebCRM.

## Estado confirmado
- Upstream: `https://github.com/krayin/laravel-crm.git`
- Branch base: `2.2`
- Runtime: PHP 8.3 em Docker
- Banco: MySQL 8.0 em Docker
- Aplicação: `http://localhost:8000`
- Banco no host: `localhost:3307`

## Decisões
- Manter o upstream como remoto de referência, sem copiar alterações funcionais para o núcleo.
- Usar Docker Compose porque PHP e Composer não estão instalados no host.
- Persistir o banco em volume nomeado.
- Manter segredos e configuração local exclusivamente em `.env`.
- Não recompilar assets versionados até existir alteração real de frontend.

## Operação
Os comandos de instalação, inicialização, logs e reset estão em `docs/LOCAL_DEVELOPMENT.md`.

## Imagem de produção
- `docker/php/Dockerfile` é uma base de desenvolvimento e depende do bind mount definido em `compose.yaml`;
- ele não incorpora código, `vendor/` ou configuração de produção;
- a imagem imutável está implementada em `docker/php/Dockerfile.production`;
- a stack Swarm está em `compose.production.yaml`;
- o destino usa Setup Orion, documentado em `docs/SETUP_ORION.md`;
- build, healthcheck e execução não-root foram validados localmente.

## Validação concluída
- Requisitos PHP do lockfile atendidos.
- Instalação, migrations e seeders concluídos.
- Login administrativo autenticado e dashboard retornando HTTP 200.

## Restrições conhecidas
- A suíte completa não deve usar o banco de desenvolvimento; criar um banco de teste isolado primeiro.
- `docker compose down -v` apaga todos os dados locais do MySQL.
- O bind mount no Windows pode tornar a primeira requisição mais lenta.
