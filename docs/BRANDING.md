# Personalização de Marca do TopwebCRM

## Nome da Aplicação

Defina o nome exibido pelo Laravel no `.env`:

```dotenv
APP_NAME="TopwebCRM"
```

Depois da alteração, limpe os caches:

```bash
php artisan optimize:clear
```

O módulo de atendimento mantém o identificador técnico `TopwebChat`, o namespace `Webkul\TopwebChat` e o prefixo de ambiente `TOPWEB_CHAT_`. Não renomeie esses identificadores sem uma migration planejada.

## Logo e Favicon pelo Painel

O Krayin oferece configuração nativa em **Configurações > Geral > Logo do painel administrativo**. Esse formulário usa as chaves reais:

- `general.general.admin_logo.logo_image`;
- `general.general.admin_logo.favicon_image`.

Envie uma logo compatível com fundos claros e escuros e um ícone quadrado para o favicon. Os arquivos de referência da Topweb estão em:

- `docs/assets/Topweb_Large_LIGHT.svg`;
- `docs/assets/Topweb_Large_DARK.svg`;
- `docs/assets/Topweb_Icon.svg`;
- versões PNG equivalentes no mesmo diretório.

Os uploads configurados pelo painel prevalecem sobre os assets padrão do tema e são carregados via storage.

## Assets Padrão

Quando não há logo configurada, o painel usa os assets compilados do Admin, incluindo `images/logo.svg`, `images/dark-logo.svg` e `images/favicon.ico`. Alterar esses arquivos-base exige rebuild:

```bash
npm ci
npm run build
php artisan optimize:clear
```

Prefira o painel para personalização operacional. Substitua assets do pacote somente quando a imagem Docker precisar sair com a marca aplicada antes da primeira configuração.

## Verificação

Valide login, cabeçalho desktop, menu mobile, modo claro, modo escuro e favicon. Não coloque tokens, `.env` ou materiais privados de marca dentro de `public/` ou das camadas da imagem Docker.
