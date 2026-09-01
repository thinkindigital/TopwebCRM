# Visibilidade de Dados Sensíveis — Guia Operacional

> **Decisão arquitetural vigente:** `docs/adr/0007-sensitive-data-individual-and-contextual-access.md`
> **Serviço central:** `app/Services/SensitiveDataService.php` + `SensitiveFileService.php`
> **Configuração:** `config/sensitive-data.php`

---

## Como Funciona (Resumo)

A visualização integral de dados sensíveis é **negada por padrão** e concedida **individualmente por usuário** via `users.can_view_sensitive_data`. Roles com `permission_type=all` **não** liberam automaticamente — a concessão individual é a única exceção.

| Usuário | Concessão Individual | Resultado |
|---------|---------------------|-----------|
| Qualquer | Habilitada (`true`) | Visualização integral |
| Qualquer | Desabilitada/ausente (`false`) | Dados mascarados ou ocultos |
| Não autenticado | — | Acesso negado |

---

## Como Habilitar/Revogar (Admin)

1. Login com conta autorizada a administrar usuários/roles
2. **Configurações → Usuários** → editar usuário alvo
3. Marcar/desmarcar **Visualizar dados sensíveis completos** (campo `can_view_sensitive_data`)
4. Salvar → próxima requisição já reflete a decisão

> Não há cache de decisão — é avaliada em tempo real no `SensitiveDataService`.

---

## Entidades e Campos Protegidos

| Entidade | Campos Protegidos (Exemplos) |
|----------|------------------------------|
| **Pessoas** | e-mails, telefones, identificadores únicos |
| **Organizações** | endereço |
| **Leads** | descrição, origem, motivo de perda, valor, produtos |
| **Atividades** | título, comentário, localização, metadados, arquivos |
| **E-mails** | conteúdo, participantes, identificadores, anexos |
| **Cotações** | endereços, descrição, descontos, impostos, totais |
| **Atributos Customizados** | Tipos/nomes documentais em `config/sensitive-data.php` (ex: `cpf`, `cnpj`, `rg`, `tax_id`) |
| **Conversas (TopwebChat)** | telefone/JID, identificadores externos, metadados estruturados |

> **Texto de mensagens WhatsApp** terá mascaramento seletivo de padrões sensíveis. Notas e outros textos livres seguem a classificação do contexto correspondente.

---

## Superfícies a Revalidar

**Estado:** planejado na Epic E02. Os itens permanecem desmarcados porque a suíte citada pelo histórico não existe no checkout atual.

- [ ] Resources / API responses (`PersonResource`, `LeadResource`, `ConversationResource`, etc.)
- [ ] DataGrids (listagens, filtros, ordenação, exportação — campos sensíveis não pesquisáveis/exportáveis)
- [ ] Páginas de detalhe / formulários (valor cru não exposto; backend bloqueia overwrite não autorizado)
- [ ] Busca global / autocomplete / filtros (respeitam autorização)
- [ ] Dashboard / Kanban / relatórios financeiros
- [ ] Downloads de anexos (rotas autorizadas, disco `private`)
- [ ] Payloads Vue serializados (resources JSON)

---

## Anexos — Disco Privado

**Novos anexos** (e-mail, atividade e mídia recebida pelo TopwebChat): gravados em `storage/app/private` (config `SENSITIVE_DATA_DISK`), com acesso resolvido por rotas autenticadas. No TopwebChat, a rota também revalida o acesso à conversa e exige `can_view_sensitive_data`; imagens, áudios e vídeos autorizados são exibidos na timeline, enquanto outros tipos são oferecidos para abertura controlada.

**Legados** (antes da feature): migrar com comando idempotente:

```bash
# 1. Validar conflitos/ausências (sem mover)
php artisan sensitive-data:migrate-attachments --dry-run

# 2. Executar migração efetiva (após revisar saída do dry-run)
php artisan sensitive-data:migrate-attachments
```

> Arquivos legados permanecem públicos até execução da migração no ambiente que os contém.

---

## Extensão para Provedores WhatsApp (OpenWA, Evolution)

Resources de conversas/mensagens **devem** chamar `SensitiveDataService` antes de devolver:
- Telefone / JID / `remote_jid`
- Identificadores externos
- Metadados estruturados sensíveis

Mídias: usar `SensitiveFileService` → armazenar em disco privado; **não** repassar URLs públicas do provedor diretamente.

---

## Limites Conhecidos (Riscos Remanescentes)

| Limite | Mitigação Futura |
|--------|------------------|
| Webhooks/workflows = fronteira de confiança | Política explícita de confiança por integração (ADR futuro) |
| Ownership por registro | Camada separada — auditoria dedicada (Epic E07) |
| Extensões `view_render_event` recebem models crus | Revisão obrigatória em novas extensões |
| Mudanças de acesso integral não auditadas | Trilha de auditoria imutável (Epic E07) |
| Atributos de Warehouse não classificados | Decidir se entram em `config/sensitive-data.php` |

---

## Testes e Validação

```bash
# Comando alvo após a Epic E02 restaurar testes versionados
php artisan test

# Lint + style
./vendor/bin/pint

# Cache de views
php artisan view:cache

# Healthcheck
curl -f https://crm.seudominio.com/up
```

---

## Referências

- ADR vigente: `docs/adr/0007-sensitive-data-individual-and-contextual-access.md`
- ADR histórico superado: `docs/adr/0003-sensitive-data-visibility-by-role.md`
- Histórico: `docs/archive/CHANGELOG_AI.md` (entradas 2026-07-14)
- Task arquivada: `docs/archive/IMPLEMENT_SENSITIVE_DATA_VISIBILITY.md`
