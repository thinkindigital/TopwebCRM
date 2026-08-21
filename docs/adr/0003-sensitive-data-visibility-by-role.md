# Visibilidade de dados sensíveis por perfil (concessão individual)

**Status**: superado pelo `0007-sensitive-data-individual-and-contextual-access.md` onde houver conflito.

**Contexto**: Usuários comuns não devem ver dados sensíveis integrais; admins/perfis autorizados veem tudo.
**Decisão**: Centralizar decisão em `SensitiveDataService` + permissão `sensitive_data.view` na árvore ACL + concessão individual `users.can_view_sensitive_data`.
**Por que**: Evita `if admin` espalhado; single source of truth; exceções individuais via role dedicada preservam role como fonte única. Máscara/ocultação aplicada na saída (Resources, DataGrids, formulários, busca, export, anexos) — nunca só no frontend.

**Classes**: Pública interna / Restrita (perm. específica) / Altamente sensível (admin + perm. explícita).
**Superfícies cobertas**: listagens, detalhes, formulários, APIs, Resources, busca, autocomplete, filtros, export, relatórios, logs, anexos (disco privado + rotas autorizadas).
**Riscos remanescentes**: webhooks/workflows precisam política de confiança; extensões Blade recebem models crus; ownership por registro separado; anexos legados até migração.
