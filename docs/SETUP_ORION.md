# Infraestrutura Setup Orion

## Referência oficial

O ambiente de produção do TopwebCRM usa como base operacional o [Setup Orion](https://github.com/oriondesign2015/setuporion). A auditoria local foi feita em 28/07/2026 sobre o commit `044c723d2f6b869e9f6e7566872ef8404e40cfe9`.

O Setup Orion inicializa Docker Swarm, cria uma rede overlay externa informada durante a instalação e publica serviços pelo Traefik. O instalador exige Traefik e Portainer antes das demais ferramentas. O README recomenda servidor vazio para a instalação inicial; em servidor já operacional, use apenas opções incrementais e faça backup antes.

## Infraestrutura confirmada

O servidor possui Portainer Business Edition, Traefik, MySQL, Redis, RedisInsight e MongoDB.

O nome da rede Orion fica registrado em `/root/dados_vps/dados_vps` como `Rede interna`. No ambiente atual, a referência conhecida é `renacesso`; confirme antes do deploy:

```bash
grep "Rede interna:" /root/dados_vps/dados_vps
docker network inspect renacesso
```

O Traefik Orion usa provider Swarm, rede overlay externa, entrypoint `websecure` e certificados pelo resolver `letsencryptresolver`.

## Decisões do TopwebCRM

`compose.production.yaml` segue o modelo Orion, mas endurece alguns pontos:

- somente o serviço HTTP recebe labels Traefik;
- banco e Redis do CRM ficam em rede overlay privada;
- MySQL e Redis são dedicados ao TopwebCRM, como na stack Krayin do próprio Orion;
- os serviços globais `mysql` e `redis` não são reutilizados;
- RedisInsight não acessa o Redis privado por padrão;
- segredos ficam em Docker Secrets;
- volumes são externos e possuem nomes estáveis;
- serviços persistentes usam o mesmo nó rotulado;
- app, queue e scheduler usam a mesma imagem imutável.

Não use a opção Krayin embutida no Setup Orion para este fork: ela instala `webkul/krayin:v2.1.2-https` e não contém o TopwebChat nem as regras de dados sensíveis.

## Afinidade e persistência

```bash
docker node ls
docker node update --label-add topwebcrm=true NOME_DO_NO
docker volume create topwebcrm_storage
docker volume create topwebcrm_db
docker volume create topwebcrm_redis
```

Em Swarm com vários nós, volumes locais não migram com a tarefa. Mantenha a label no nó que contém os dados ou adote storage compartilhado antes de mover os serviços.

## Ferramentas futuras

As opções Orion mais úteis para evoluções futuras são Uptime Kuma, Grafana, Prometheus, cAdvisor, MinIO e RabbitMQ. Nenhuma é requisito do deploy atual; instale somente quando houver necessidade definida.

## Fontes

- [Setup Orion](https://github.com/oriondesign2015/setuporion)
- [Docker Swarm stack deploy](https://docs.docker.com/engine/swarm/stack-deploy/)
- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)
- [Portainer: adicionar stack](https://docs.portainer.io/sts/user/docker/stacks/add)
