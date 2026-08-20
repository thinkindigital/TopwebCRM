# Epic E05: Confiabilidade Operacional do Chat (Resiliência OpenWA)

## Objetivo
Reconciliação instância/histórico, catch-up assíncrono, status monotônico, tratamento rate limit, proteção concorrência.

## Critérios de Sucesso
- [ ] Reconciliação manual/agendada instância; catch-up histórico conhecido paginado
- [ ] Leitura remota assíncrona; status monotônico (delivered/read não rebaixam para failed)
- [ ] Rate limit OpenWA respeita `Retry-After` + teto configurável
- [ ] Locks transacionais em atribuição/associação/ingestão; contadores inbound monotônicos
- [ ] Preservação notas internas; ingestão webhook atômica

## Estado
done

## Slices
- [ ] #25 - Comando `topweb-chat:reconcile --history` + scheduler (estado 1min, histórico 5min/20 conv)
- [ ] #26 - Outbox local com operation_key, tentativa única, unknown sem retry cego
- [ ] #27 - Timeline JSON sanitizado polling 5s
- [ ] #28 - 25 testes contratos/ACL/dados sensíveis (79 asserções)
- [ ] #29 - Documentação: TOPWEB_CHAT_OPENWA_MAP.md, TOPWEB_CHAT_OPERATIONS.md, TOPWEB_CHAT_ARCHITECTURE.md