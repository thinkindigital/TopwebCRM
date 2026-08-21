# Mapa Operacional do TopwebCRM

## Como usar este mapa

Este documento descreve o fork real, não apenas convenções Laravel. Para criar ou alterar pacotes, consulte também `docs/krayincrm/llms-full.txt` e o código local. O fork usa PHP 8.3, Laravel 12, Concord, repositories Prettus, Blade com componentes Vue e Vite. O baseline Pest está restaurado e inclui testes de contrato do OpenWA.

## Estrutura da raiz

| Caminho | Responsabilidade |
|---|---|
| `app/` | Extensões globais do fork: providers, comandos e serviços transversais, como proteção de dados sensíveis. |
| `bootstrap/` | Inicialização Laravel e lista efetiva de service providers em `bootstrap/providers.php`. |
| `config/` | Configuração Laravel e registro dos módulos Concord em `config/concord.php`. |
| `database/` | Factories, migrations e seeders da aplicação raiz. Migrations de módulos ficam dentro dos próprios pacotes. |
| `packages/Webkul/` | Domínios e módulos reais do CRM. É o principal local de desenvolvimento funcional. |
| `resources/` | Assets e traduções globais do fork. |
| `lang/` | Traduções Laravel da aplicação raiz. |
| `routes/` | Rotas globais e agendamentos em `routes/console.php`. Módulos registram rotas próprias. |
| `public/` | Document root e manifests compilados de root, Admin, Installer e WebForm. |
| `storage/` | Logs, cache e arquivos. Dados privados ficam em `storage/app/private`. |
| `tests/` | Diretório esperado para testes do fork; ausente no checkout atual e rastreado pela Epic E03. |
| `docker/` e `compose*.yaml` | Ambiente local e imagem/stack de produção. |
| `docs/` | Regras, mapas, operação, decisões, referências e histórico arquivado. |

## Módulos Webkul

| Módulo | Domínio principal |
|---|---|
| `Admin` | Painel, controllers, resources JSON, DataGrids, menus, ACL, Blade/Vue e rotas administrativas. |
| `Core` | Configuração compartilhada, eventos de renderização, helpers e infraestrutura comum. |
| `User` | Usuários, roles, autenticação administrativa e escopo de visualização. |
| `Contact` | Pessoas e organizações. Telefones e e-mails de Pessoa são arrays JSON. |
| `Lead` | Leads/oportunidades, pipelines, etapas, fontes, tipos e vínculos comerciais. |
| `Activity` | Atividades, notas, reuniões, ligações e anexos. |
| `Email` / `EmailTemplate` | Mensagens de e-mail, anexos, templates e processamento de entrada. |
| `Quote` / `Product` | Cotações, itens, valores e catálogo. |
| `Attribute` | Atributos customizados e valores EAV usados por entidades CRM. |
| `DataGrid` / `DataTransfer` | Listagem, filtro, ordenação, exportação e importação. |
| `Automation` / `Marketing` | Workflows, campanhas, jobs e tarefas agendadas. |
| `Tag`, `Warehouse`, `WebForm`, `Installer` | Tags, depósitos, formulários públicos e instalação. |
| `TopwebChat` | Extensão Topweb para atendimento WhatsApp integrado ao OpenWA; integração ponta a ponta ainda em correção. |

## Anatomia de um pacote

O padrão confirmado é:

```text
packages/Webkul/Nome/
├── composer.json
└── src/
    ├── Config/                 # menu, ACL e configuração
    ├── Contracts/              # contratos Concord
    ├── Database/Migrations/
    ├── Http/Controllers/
    ├── Models/                 # model e proxy Concord
    ├── Providers/              # provider Laravel e ModuleServiceProvider
    ├── Repositories/
    ├── Resources/lang/
    ├── Resources/views/
    └── Routes/
```

Ao criar um módulo: registrar namespace no `composer.json` raiz, provider em `bootstrap/providers.php`, módulo em `config/concord.php`, models no `ModuleServiceProvider`, e carregar rotas, migrations, views e traduções no service provider do pacote.

## Fluxos centrais

### Requisição administrativa

`rota -> middleware web/admin_locale/user -> Bouncer/ACL -> controller -> repository/service -> model -> Blade ou Resource JSON`

As rotas Admin são agregadas em `packages/Webkul/Admin/src/Routes/Admin/web.php`. O login usa o guard `user`; `packages/Webkul/Admin/src/Http/Middleware/Bouncer.php` valida sessão, status e permissão. A árvore de permissões vem dos arquivos `Config/acl.php`, enquanto `packages/Webkul/Admin/src/Bouncer.php` resolve ACL e escopo de usuários autorizados.

### Persistência Concord

Models implementam contratos e possuem proxies. `config/concord.php` registra os module providers, e cada `ModuleServiceProvider` declara os models. Repositories estendem `Webkul\Core\Eloquent\Repository` e são a fronteira preferida para persistência de regras de negócio.

### UI, DataGrid e APIs internas

- Views: `packages/Webkul/*/src/Resources/views`.
- Componentes principais: `packages/Webkul/Admin/src/Resources/views/components`.
- Resources JSON: `packages/Webkul/Admin/src/Http/Resources`.
- DataGrids: `packages/Webkul/Admin/src/DataGrids`.
- Extensões sem editar core: eventos registrados por `ViewRenderEventManager`.

## Entidades e relações

- `User` pertence a `Role`; a role define permissões e escopo, enquanto `users.can_view_sensitive_data` é uma concessão individual do fork.
- `Person` pertence opcionalmente a `Organization` e `User`; possui leads, atividades e tags.
- `Organization` possui pessoas e pertence opcionalmente a um usuário.
- `Lead` pertence a Pessoa, usuário, pipeline e etapa; pode ter atividades, produtos, e-mails, cotações e tags.
- `Activity`, `Email` e `Quote` vinculam o histórico operacional e comercial ao lead/pessoa.
- `TopwebChat\Conversation` pertence a uma instância, Pessoa, Lead opcional e atendente opcional; possui mensagens e notas internas.

## Traduções e assets

Idiomas ativos são definidos em `config/app.php`; o locale administrativo é aplicado por `packages/Webkul/Admin/src/Http/Middleware/Locale.php`. Traduções de pacote ficam em `packages/Webkul/<Modulo>/src/Resources/lang/<locale>` e são chamadas por namespace, por exemplo `topweb_chat::app.menu.title`. Traduções globais do fork ficam em `resources/lang/<locale>`.

Há quatro builds independentes:

- raiz: `package.json` -> `public/build`;
- Admin: `packages/Webkul/Admin/package.json` -> `public/admin/build`;
- Installer: `packages/Webkul/Installer/package.json` -> `public/installer/build`;
- WebForm: `packages/Webkul/WebForm/package.json` -> `public/webform/build`.

O TopwebChat reutiliza Blade, Vue e classes do Admin; não possui build Vite próprio.

## Pontos seguros de extensão

- Novo domínio: pacote em `packages/Webkul`, seguindo contracts, proxies, providers e repositories.
- Ações nas telas: eventos `view_render_event`, evitando cópia de views do Admin.
- Integrações externas: contrato + adapter + service + job; controllers apenas validam e coordenam.
- Dados sensíveis: `app/Services/SensitiveDataService.php` na saída e autorização backend; nunca somente máscara visual.
- Arquivos sensíveis: `app/Services/SensitiveFileService.php` e disco `private`.

## Zonas de risco

- `bootstrap/providers.php`, `config/concord.php` e `composer.json`: erro impede bootstrap do módulo.
- Bouncer, ACL, DataGrid e Resources: mudanças afetam várias telas, APIs e exportações.
- Atributos EAV e JSON de contato: filtros ou updates ingênuos podem apagar ou revelar dados.
- Migrations de pacote: não devem controlar colunas globais de outro domínio.
- Eventos Blade recebem models; qualquer extensão deve reaplicar autorização e sanitização.
- `storage/app/private`, `APP_KEY` e campos criptografados não podem ser perdidos entre releases.
