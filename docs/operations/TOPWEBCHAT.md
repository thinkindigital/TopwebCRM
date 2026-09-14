---
doc_id: topwebchat-runbook
type: runbook
status: active
authority: operational
scope: topweb-chat
last_verified_commit: 205c296
update_triggers: [compose, provider-version, secret, queue, scheduler, healthcheck]
related: [docs/modules/topweb-chat/providers/OPENWA.md]
---

# TopwebChat Operations

## Pré-requisitos

OpenWA saudável; UUID e API key válidos; app e gateway na mesma rede; queue e
scheduler ativos. A URL cadastrada é a origem sem `/api`. Em produção use
`http://openwa_openwa_api:2785`; o webhook usa a URL HTTPS pública do CRM.

## Provisionamento

Em **TopwebChat → Configurações**, cadastre nome, UUID, base e API key; valide
saúde/sessões; configure webhook; reconcilie; valide envio, recebimento e ACK.
API key e segredo dependem da mesma `APP_KEY` entre releases.

## Rotinas

- `topweb-chat:reconcile` a cada minuto.
- `topweb-chat:reconcile --history` a cada cinco minutos.
- `topweb-chat:close-stale-attendances` a cada minuto.
- `topweb-chat:project-lead-media` e `topweb-chat:retry-failed` a cada cinco minutos.

## Diagnóstico

Valide health do gateway, serviços sem restart loop, DNS interno, UUID/base,
webhook HTTPS, worker e scheduler. Use PHP apenas no container:

```bash
docker exec topwebcrm_topwebcrm_app php artisan route:list --name=topweb_chat
docker exec topwebcrm_topwebcrm_app php artisan schedule:list
docker exec topwebcrm_topwebcrm_app php artisan topweb-chat:reconcile
```

O smoke de release exige sessão real dedicada: texto, mídia, inbound, status,
autorização negativa e persistência após restart. Timeout/`unknown` não pode ser
reenviado cegamente.
