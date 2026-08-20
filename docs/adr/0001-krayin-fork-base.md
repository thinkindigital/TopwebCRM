# Fork do Krayin CRM v2.2 como base principal

**Contexto**: Precisávamos de um CRM estável, maduro e legalmente modificável como fundação.
**Decisão**: Usar Krayin CRM (krayin/laravel-crm branch 2.2) como base do TopwebCRM.
**Por que**: Krayin oferece domínio CRM completo (Leads, Pessoas, Organizações, Atividades, E-mails, Cotações, Pipelines), arquitetura modular via Concord/Prettus, painel admin com DataGrids/Vue, ACL via Bouncer, e licença permissiva. Evita reescrita do zero.

**Alternativas consideradas**: Evo CRM (arquitetura distribuída, complexidade excessiva para fork), construir do zero (tempo/risco alto).