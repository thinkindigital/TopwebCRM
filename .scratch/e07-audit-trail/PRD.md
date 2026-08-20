# Epic E07: Auditoria e Trilha Operacional

## Objetivo
Trilha de auditoria para ações sensíveis (visualização dados sensíveis, exportação, alteração permissões, mudança integração, ações mensageria).

## Critérios de Sucesso
- [ ] Log estruturado imutável: quem/quando/o que/entidade/impacto
- [ ] Consulta/filtro por admin; retenção configurável
- [ ] Cobertura: SensitiveDataService, export, ACL, TopwebChat

## Estado
todo

## Slices
- [ ] #53 - Modelo/entidade AuditLog (immutable table)
- [ ] #54 - Listeners/observers nos pontos críticos (SensitiveDataService, export, ACL, TopwebChat)
- [ ] #55 - UI admin para consulta/filtro (DataGrid + filtros avançados)
- [ ] #56 - Testes de cobertura (auditoria não vaza dados sensíveis)
- [ ] #57 - Retenção configurável + política de arquivamento