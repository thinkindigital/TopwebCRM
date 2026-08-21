# ADR 0004: TopwebChat com OpenWA primário

- Status: aceito
- Data: 2026-07-16

## Contexto

O CRM precisa operar WhatsApp sem depender de SaaS fechado. A implementação anterior assumia RyzeAPI e tornou-se inviável para desenvolvimento e testes.

## Decisão

- Manter o domínio de conversas e mensagens no pacote `packages/Webkul/TopwebChat`.
- Usar `MessagingProvider` como fronteira de transporte.
- Adotar OpenWA self-hosted como único provedor suportado nesta etapa.
- Persistir conversas, mensagens, eventos e estado necessários ao CRM.
- Receber eventos por webhook HMAC idempotente e processá-los assincronamente.
- Manter credenciais criptografadas e fora do navegador.
- Remover RyzeAPI sem camada de compatibilidade, pois não há consumidor externo ou dado persistido de produção que a exija.

## Consequências

- Recursos existentes que ainda usam contratos RyzeAPI são regressões a corrigir, não compatibilidade suportada.
- Uma futura Evolution API exigirá testes de contrato e resolução por instância antes de ser anunciada como suportada.
- Capacidade documentada pelo OpenWA não significa capacidade exposta pelo TopwebCRM.
- O contrato auditado do provedor fica em `docs/topweb-chat/OPENWA.md`.
