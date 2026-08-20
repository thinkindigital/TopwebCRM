# Epic E04: Infraestrutura de Produção (Setup Orion)

## Objetivo
Stack Docker Swarm parametrizada com imagem imutável, secrets externos, healthchecks, isolamento.

## Critérios de Sucesso
- [ ] `Dockerfile.production` (código, deps, assets, Apache 8080, www-data, healthcheck)
- [ ] `compose.production.yaml` (app, worker, scheduler, MySQL, Redis, secrets, rede Traefik, volumes estáveis)
- [ ] Build validado, healthcheck HTTP 200, ausência .env/docs/testes na imagem

## Estado
done

## Slices
- [ ] #20 - Dockerfile.production + auxiliares + .dockerignore
- [ ] #21 - compose.production.yaml parametrizado (imagem, domínio, rede, SMTP)
- [ ] #22 - Secrets externos, volumes com placement fixo
- [ ] #23 - Migrations separadas por fase, espera ativa DB, healthchecks
- [ ] #24 - Imagem `topwebcrm:validation` buildada, healthy, respondendo /up