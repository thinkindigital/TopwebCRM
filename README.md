# TopwebCRM

Fork do Krayin CRM 2.2 para operacao interna, governanca de dados e atendimento WhatsApp integrado.

## Estado

O CRM base esta disponivel para desenvolvimento local. O TopwebChat possui dominio e interface iniciais, mas a integracao OpenWA ainda esta em correcao e nao deve ser considerada pronta de ponta a ponta.

Planejamento e estado verificavel: `ORCHESTRATOR-ROADMAP.md`.

## Componentes

- Laravel 12 e PHP 8.3.
- Krayin CRM 2.2 como base.
- MySQL e Redis em producao.
- TopwebChat em `packages/Webkul/TopwebChat`.
- OpenWA self-hosted como provedor WhatsApp.
- Docker Compose para desenvolvimento e Docker Swarm para producao.

## Desenvolvimento local

Consulte `docs/operations/LOCAL_DEVELOPMENT.md`.

Endpoints locais usuais:

- TopwebCRM: `http://127.0.0.1:8000`
- OpenWA: `http://localhost:2785`

O OpenWA e executado separadamente. O TopwebCRM nao inclui uma instancia OpenWA no seu Compose.

## Documentacao

Comece por `docs/README.md`.

- Arquitetura: `docs/ARCHITECTURE.md`
- Produto: `docs/PRODUCT_RULES.md`
- Seguranca: `docs/SECURITY_RULES.md`
- TopwebChat: `docs/topweb-chat/README.md`
- OpenWA: `docs/topweb-chat/OPENWA.md`
- Deploy: `docs/operations/DEPLOYMENT.md`
- Decisoes: `docs/adr/`

## Contribuicao

- GitHub Issues sao o tracker oficial.
- Nao versionar `.env`, bancos locais, `vendor/`, `node_modules/`, `storage/` ou credenciais.
- Mudancas funcionais devem incluir testes, documentacao e verificacao de autorizacao.
- Leia `AGENTS.md` antes de executar trabalho automatizado no repositorio.

## Upstream e licenca

- Upstream: https://github.com/krayin/laravel-crm
- Politica de referencia: `docs/reference/KRAYIN.md`
- Licenca: consulte `LICENSE`.
