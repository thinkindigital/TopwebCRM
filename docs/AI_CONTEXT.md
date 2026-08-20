Não faça refatoração estética. Não renomeie sem necessidade. Não reorganize estrutura inteira. Foque só no objetivo.

# AI_CONTEXT.md — Protocolo de Inicialização Obrigatório

Este documento define a **ordem obrigatória de leitura** antes de qualquer análise, mudança de código, refatoração, migração ou implementação.

## Ordem de Leitura Obrigatória

1. `AGENTS.md` — Protocolo de trabalho, rules, skills disponíveis
2. `CONTEXT.md` — **Glossário único do domínio** (fonte da verdade para terminologia)
3. `docs/ARCHITECTURE.md` — Princípios arquiteturais e padrões de extensão (resumo + pointers para ADRs)
4. `docs/SECURITY_RULES.md` — Regras normativas de segurança
5. `docs/PRODUCT_RULES.md` — Regras de produto (se existir)
6. Task file em `docs/tasks/` que melhor corresponda à request atual
7. Documentação específica do módulo afetado (ex: `docs/TOPWEB_CHAT_*.md`)
8. **Somente então** inspecionar e alterar source files

Se qualquer arquivo estiver ausente, declarar explicitamente antes de prosseguir.

---

## Referências Rápidas (Single Source of Truth)

| Conceito | Documento Autoritativo |
|----------|------------------------|
| Terminologia do domínio (Fork, Lead, Pessoa, Instância, Provedor, etc.) | `CONTEXT.md` |
| Decisões arquiteturais irreversíveis (ADRs) | `docs/adr/0001`–`0006` |
| Princípios de extensão / zonas de risco | `docs/ARCHITECTURE.md` + `docs/adr/0006` |
| Dados sensíveis: classificação, mascaramento, superfícies | `docs/adr/0003` |
| TopwebChat: arquitetura, provider adapter, OpenWA | `docs/adr/0004` + `docs/TOPWEB_CHAT_*.md` |
| Infraestrutura produção (Orion, Swarm, Imagem) | `docs/adr/0005` + `docs/SETUP_ORION.md` |
| Krayin internals (modules, ACL, Concord, DataGrid) | `docs/krayincrm/llms-full.txt` (consultar por seção) |
| OpenWA endpoints, webhooks, HMAC | `docs/TOPWEB_CHAT_OPENWA_MAP.md` |

---

## Precedência de Fontes (Não Negociável)

```
código local > docs locais TopwebCRM > ADRs > llms-full local (Krayin) > documentação online > hipótese
```

- **Nunca** inventar nomes de tabelas, rotas, classes, políticas, services ou pastas sem validar no código
- **Nunca** copiar exemplos do `llms-full.txt` sem reconfirmar no fork (divergências conhecidas: Laravel 12 vs 11, middleware/ACL diferentes)
- Snapshots locais (`docs/krayincrm/llms-full.txt`) são referência por seção; revalidar URL canônica antes de decisões versionáveis

---

## Regras de Trabalho (Resumo — ver AGENTS.md para completo)

- **Startup protocol**: Seguir a ordem acima sem pular etapas
- **Output format**: Para tarefas não-triviais, retornar: objective summary → files read → code areas inspected → exact files affected → implementation plan → risks → tests to run
- **Sensitive data rule**: Toda task envolvendo dados cliente/lead/contato/empresa/oportunidade deve cobrir: UI visibility, backend auth, API output, search/filter/autocomplete, exports, logs, notifications, integrations, cache. Se faltar superfície, reportar trabalho parcial.
- **Integration rule**: Provedores externos (WhatsApp) → adapter/service + jobs/queues + secrets em env + retry/error logging + sem lógica de provedor no controller
- **Documentation update**: Após task relevante → update `docs/CHANGELOG_AI.md` + task doc se entendimento mudou + novo constraint arquitetural descoberto
- **Mapping rule**: Mapear Krayin/Evo → identificar módulos reais + trace entry→rule→persistence→output + citar arquivos exatos + distinguir confirmado vs hipótese + listar extension points seguros + danger zones

---

## Domínios Críticos (Exigem Análise Rigorosa)

Autenticação, Autorização, Usuários/Roles/Permissões, Leads/Pessoas/Organizações/Oportunidades, Campos Sensíveis, Exportações, Integrações Externas, Mensageria/Webhooks, Logs/Auditoria/Cache, Filas/Jobs, APIs Internas/Públicas.

---

## Regra de Ouro

> Dúvida rapidez vs confiabilidade → **confiabilidade**  
> Dúvida "mexer em tudo" vs "intervir com precisão" → **precisão**  
> Dúvida conveniência local vs coerência sistêmica → **coerência sistêmica**