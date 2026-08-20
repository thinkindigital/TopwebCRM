# Epic E02: Visibilidade de Dados Sensíveis por Perfil

## Objetivo
Centralizar decisão de visibilidade (SensitiveDataService + permissão `sensitive_data.view` + concessão individual `users.can_view_sensitive_data`), cobrir todas as superfícies.

## Critérios de Sucesso
- [ ] Admins/perfis com permissão veem integral; usuários comuns veem mascarado/oculto
- [ ] Coberto em: listagens, detalhes, formulários, APIs, Resources, busca, autocomplete, filtros, export, relatórios, anexos (disco privado + rotas autorizadas)
- [ ] Migração legada idempotente (`sensitive-data:migrate-attachments`)

## Estado
done

## Slices
- [ ] #7 - Configuração declarativa `config/sensitive-data.php`
- [ ] #8 - Serviço central `SensitiveDataService` + `SensitiveFileService`
- [ ] #9 - Permissão na árvore ACL + concessão individual no User
- [ ] #10 - Resources, DataGrids, formulários, busca, export, dashboard, atividades, anexos, cotações
- [ ] #11 - Comando `sensitive-data:migrate-attachments` (dry-run + execução)
- [ ] #12 - Testes Pest (10 testes, 34 asserções) + Pint + Blade cache + healthcheck