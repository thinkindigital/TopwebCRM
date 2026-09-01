# Documentação do TopwebCRM

Este índice aponta para a fonte certa sem repetir o mesmo procedimento em vários lugares. Um comportamento só é tratado como implementado quando estiver confirmado no código e puder ser verificado por teste ou procedimento reproduzível.

## Leitura por objetivo

| Preciso entender... | Fonte canônica |
|---|---|
| linguagem, atores e limites do produto | `CONTEXT.md` |
| arquitetura e fronteiras dos componentes | `docs/ARCHITECTURE.md` |
| regras funcionais e de segurança | `docs/PRODUCT_RULES.md` e `docs/SECURITY_RULES.md` |
| localização do código | `docs/SYSTEM_MAP.md` |
| funcionamento e operação do TopwebChat | `docs/topweb-chat/README.md` |
| endpoints OpenWA consumidos pelo CRM | `docs/topweb-chat/OPENWA.md` |
| desenvolvimento local | `docs/operations/LOCAL_DEVELOPMENT.md` |
| instalação, release e rollback no SetupOrion | `docs/operations/DEPLOYMENT.md` |
| decisões arquiteturais aceitas | `docs/adr/` |
| trabalho planejado ou pendente | `ORCHESTRATOR-ROADMAP.md` e GitHub Issues |
| protocolo para agentes automatizados | `AGENTS.md` |

## Autoridade e estado

A ordem de autoridade é: código, migrations, configuração e testes; ADRs vigentes; contexto e regras; arquitetura e documentação de módulo; runbooks; roadmap e Issues; histórico e referências externas.

Os termos usados na documentação têm significado específico:

- **Implementado:** existe no código atual e possui verificação reproduzível.
- **Decidido:** contrato aceito, mesmo que a entrega ainda esteja incompleta.
- **Planejado:** escopo de roadmap ou Issue, sem garantia de disponibilidade.
- **Histórico:** registro preservado, mas sem autoridade sobre o comportamento atual.
- **Referência externa:** material de outro projeto, usado apenas como apoio.

## Manutenção

- Atualize a fonte canônica e apenas faça referência a ela nos demais documentos.
- Não copie catálogos completos de APIs externas.
- Não use um changelog ou uma tarefa concluída como especificação atual.
- Atualize testes e documentação no mesmo slice de uma mudança funcional.
- Use ADR somente para decisões duradouras, surpreendentes ou difíceis de reverter.
