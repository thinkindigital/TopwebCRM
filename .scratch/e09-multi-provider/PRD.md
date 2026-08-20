# Epic E09: Multi-provider e Evolution API

## Objetivo
Suporte a Evolution API como provider alternativo/intercambiável.

## Critérios de Sucesso
- [ ] Adapter `EvolutionApiProvider` implementando `MessagingProvider`
- [ ] Config por env; testes de contrato compartilhados
- [ ] Documentação migração OpenWA ↔ Evolution

## Estado
todo

## Slices
- [ ] #63 - Adapter Evolution API (autenticação, envio texto/mídia, webhook, instâncias)
- [ ] #64 - Factory/selector de provider por instância (`Instance.provider`)
- [ ] #65 - Testes de contrato compartilhados (MessageContract, ConversationContract)
- [ ] #66 - Docs de migração OpenWA ↔ Evolution (runbook)
- [ ] #67 - Validação E2E multi-provider (secure-e2e)