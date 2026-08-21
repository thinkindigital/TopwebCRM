# Deploy, release e rollback

## Arquitetura

- `docker/php/Dockerfile.production` gera imagem imutavel com codigo, dependencias e assets.
- `compose.production.yaml` separa app, worker, scheduler, MySQL e Redis.
- Traefik termina TLS e encaminha para o Apache na porta `8080`.
- Secrets de producao nao pertencem ao repositorio ou a imagem.
- OpenWA e implantado separadamente e precisa de rede/URL alcancavel pelo CRM.

## Build

```bash
docker build -f docker/php/Dockerfile.production -t topwebcrm:<versao> .
```

Use tag imutavel. Nao publique `latest` como unica referencia de rollback.

## Release

1. Validar imagem, healthcheck e ausencia de `.env`, testes e artefatos locais.
2. Publicar a tag no registry autorizado.
3. Atualizar a stack com a nova tag.
4. Executar migrations em fase controlada.
5. Reiniciar workers e limpar caches.
6. Validar `/up`, login, filas, scheduler e fluxos criticos.

## Rollback

1. Reaplicar a tag anterior da imagem.
2. Nao reverter migration destrutiva automaticamente.
3. Restaurar banco ou storage somente a partir de backup validado.
4. Confirmar compatibilidade de `APP_KEY` com campos criptografados.

## Setup Orion

O ambiente alvo usa Docker Swarm, Portainer Business Edition e Traefik. Nao reutilizar bancos, caches ou redes globais sem avaliar isolamento. Detalhes especificos do provedor devem ser mantidos como inventario operacional, nao como regra arquitetural do produto.

## Segredos e backups

- Preservar `APP_KEY`; sua perda torna dados criptografados ilegíveis.
- Fazer backup de banco e `storage/app/private`.
- Rotacionar API Keys e segredos de webhook com periodo de convivencia controlado.
- Nunca registrar token, chave ou payload completo nos logs do deploy.
