# TopwebCRM

Fork do [Krayin CRM](https://github.com/krayin/laravel-crm) voltado à operação comercial, à governança de dados e ao atendimento por WhatsApp. O projeto mantém o CRM como sistema de registro e integra o módulo próprio **TopwebChat** ao [OpenWA](https://github.com/rmyndharis/OpenWA), executado como serviço independente.

## Estado atual

O repositório contém os dois modos de execução usados pelo projeto:

- desenvolvimento local com Docker Compose;
- produção em Docker Swarm, administrada pelo Portainer e integrada ao Traefik de um servidor preparado pelo SetupOrion.

O TopwebChat já possui cadastro e descoberta de sessões OpenWA, configuração de webhook assinado, sincronização de histórico, envio assíncrono, recebimento de eventos e relacionamento das conversas com pessoas e leads. A aceitação funcional de cada release continua dependendo dos testes automatizados e do checklist de produção; planejamento e pendências vivem no `ORCHESTRATOR-ROADMAP.md` e nos GitHub Issues.

A base técnica atual é Krayin CRM 2.2, Laravel 12 e PHP 8.3.

## Arquitetura em uma visão

```text
Navegador
   |
Traefik / HTTPS
   |---------------------------|
   v                           v
TopwebCRM                  OpenWA
app + queue + scheduler    API + sessão WhatsApp
   |                           |
MySQL + Redis              PostgreSQL + Redis
   |                           |
   |------ rede overlay -------|
          topweb_integrations
```

TopwebCRM e OpenWA são stacks separadas. Cada uma conserva banco, cache e volumes próprios; somente a comunicação de integração passa pela rede overlay compartilhada. Em produção, o CRM chama a API do OpenWA pela rede interna e o OpenWA entrega webhooks ao endereço HTTPS público do CRM.

## Endereços e configuração

Os domínios pertencem à instalação, não ao código. Use variáveis no Portainer em vez de copiar endereços de outro cliente.

| Contexto | TopwebCRM | OpenWA |
|---|---|---|
| Local | `http://localhost:8000` | `http://localhost:2785` |
| Público em produção | `https://crm.<dominio-do-cliente>` | `https://openwa.<dominio-do-cliente>` |
| CRM → OpenWA no Swarm | — | `http://openwa_openwa_api:2785` |

O nome interno pressupõe que a stack do OpenWA se chama `openwa`. Se outro nome for usado no Portainer, o DNS do serviço também muda para `<stack>_openwa_api`. Não acrescente `/api` à URL base cadastrada no TopwebChat: o adapter já monta rotas como `/api/health` e `/api/sessions`.

## Desenvolvimento local

O Compose principal inicia TopwebCRM, MySQL e Redis. O OpenWA é iniciado separadamente, conforme o projeto upstream.

```bash
cp .env.example .env
docker compose build
docker compose run --rm app composer install
docker compose up -d
docker compose exec app php artisan krayin-crm:install
```

Configuração completa, incluindo a diferença entre acesso a partir do host e de containers: `docs/operations/LOCAL_DEVELOPMENT.md`.

## Produção

Os manifests versionados são:

- `compose.production.yaml`: app, queue, scheduler, Percona e Redis do CRM;
- `compose.openwa.production.yaml`: API OpenWA, PostgreSQL e Redis;
- `docker/php/Dockerfile.production`: imagem imutável do TopwebCRM;
- `docker/openwa-swarm-entrypoint.sh`: adaptação de secrets para o OpenWA no Swarm.

O fluxo esperado é: CI aprovado em `main`, publicação da imagem `ghcr.io/thinkindigital/topwebcrm:sha-<commit>` e atualização da stack TopwebCRM pela API do Portainer. Reiniciar uma task não equivale a atualizar a imagem.

O runbook único de instalação, release, validação, backup e rollback está em `docs/operations/DEPLOYMENT.md`.

## Documentação

Comece por `docs/README.md`. Os documentos centrais são:

- `CONTEXT.md`: linguagem e limites do domínio;
- `docs/ARCHITECTURE.md`: decisões e fronteiras do sistema;
- `docs/PRODUCT_RULES.md`: invariantes de produto;
- `docs/SECURITY_RULES.md`: controles obrigatórios;
- `docs/topweb-chat/README.md`: funcionamento e operação do TopwebChat;
- `docs/topweb-chat/OPENWA.md`: contrato da API externa usado pelo adapter;
- `docs/operations/DEPLOYMENT.md`: produção no SetupOrion, Portainer e Swarm.

## Contribuição

- GitHub Issues são o tracker oficial.
- Não versione `.env`, bancos locais, `vendor/`, `node_modules/`, `storage/` ou credenciais.
- Mudanças funcionais devem incluir testes, documentação e verificação de autorização.
- Leia `AGENTS.md` antes de executar trabalho automatizado no repositório.

## Upstream e licença

- Base: [Krayin CRM](https://github.com/krayin/laravel-crm)
- Provedor WhatsApp: [OpenWA](https://github.com/rmyndharis/OpenWA)
- Política de referência: `docs/reference/KRAYIN.md`
- Licença: consulte `LICENSE`.
