# Domain Configuration

## Contexto do Projeto

**Nome**: TopwebCRM
**Tipo**: Fork evoluído do Krayin CRM v2.2
**Objetivo**: CRM interno robusto, seguro, extensível, operacionalmente viável com integração WhatsApp

## Domínios Principais

1. **CRM Core** (Krayin base): Leads, Pessoas, Organizações, Atividades, E-mails, Cotações, Pipelines, Produtos, Atributos, Tags, Warehouses, WebForms, Automation, Marketing
2. **Segurança e Governança**: Visibilidade de dados sensíveis por perfil, concessão individual, mascaramento centralizado, disco privado, auditoria
3. **Mensageria WhatsApp** (TopwebChat): Instâncias RyzeAPI, Conversas, Mensagens, Notas internas, Atribuição, Filas, Webhook, Jobs assíncronos, Outbox local, Reconciliação
4. **Infraestrutura**: Docker Swarm (Setup Orion), Portainer EE, Traefik, Imagem imutável, Stack parametrizada, Secrets externos

## Entidades Centrais

- **User** → Role (permissões, escopo) + `can_view_sensitive_data` (concessão individual)
- **Person** → telefones/e-mails (JSON array), Organization opcional, Leads, Activities, Tags
- **Organization** → Pessoas, User opcional
- **Lead** → Person, User, Pipeline, Stage, Activities, Products, Emails, Quotes, Tags
- **Activity/Email/Quote** → histórico operacional/comercial vinculado a Lead/Person
- **Instance** → RyzeAPI instance name, token/secret criptografados, status, enabled
- **Conversation** → Instance, Remote Identity (criptografado/hash), Person, Lead opcional, Assignee opcional, fila, contadores
- **Message** → direção, tipo, conteúdo, provider_id, status, metadata criptografada
- **InternalNote** → texto livre visível apenas a operadores autorizados da conversa
- **WebhookEvent** → inbox idempotente, payload criptografado, estado processamento

## Stack Técnica

PHP 8.3, Laravel 12, Concord, Prettus Repositories, Blade + Vue components, Vite (4 builds independentes), Pest, Redis (queue/cache/scheduler), MySQL 8.0, Apache 8080 (produção)