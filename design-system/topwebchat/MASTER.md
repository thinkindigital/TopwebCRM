# MASTER — TopwebChat Design System (veredito D-01: síntese B-operacional)

> Fonte: `ui-ux-pro-max` S01 (dials 4/3/8) adaptado às autoridades do projeto +
> veredito humano 2026-09-12 (ADR 0011). Subordinado a Krayin/Admin, PRODUCT_RULES
> e segurança. Sem `--force` sem autorização.

## Tokens

- `--color-primary: #0F766E` (teal) / `--color-accent: #0369A1`
  / `--color-destructive: #DC2626` / bg `#F0FDFA`, card `#FFFFFF`
- Tipografia do Admin Krayin (sem fontes externas no chat; queries de pairing
  retornaram estilos editoriais incompatíveis — fallback registrado, não adotado).
- Spacing 4/8: base 4 (gaps densos), seções 16/24/32; composer/queue usam 8/12/16.
- Radii moderados (workspace contínuo, sem cards soltos): bolhas 16, painéis 12,
  pills/badges 9999.
- Hierarquia de texto (relativa ao Admin): contato/nome 800, corpo 400 14,
  meta/tempo 11–12 secundário, header operacional 11 uppercase.
- Semânticos: success/warning/error/info + unread + nota-âmbar + in/out +
  restricted + offline + unknown. Dark equivalente; nada só-cor.
- Focus: anel visível `primary`; disabled: opacidade + sem ação; reduced motion
  respeitado; alvos 44 px.

## Regras (veredito)

- 3 zonas: fila 280–300 + conversa fluida dominante + contexto 300–320
  (recolhível, com projeção mínima Lead/etapa/próxima-ação no header).
- 1366: fila + conversa; contexto em drawer fechado por padrão.
- Tablet master-detail; mobile single-pane, retorno explícito, composer fixo.
- Fila: contadores honestos, linha com identidade/preview/badge/estado/
  responsável/tempo; sem SLA falso, sem métricas financeiras.
- Timeline: bolhas familiares, status semântico, notas âmbar inequívocas,
  mídia restrita ≠ falha. Composer: clip SVG, preview, Enter/Shift+Enter,
  foco preservado, `operation_key`.
- Ícones SVG; emoji só como conteúdo. Alvos 44 px; foco visível; AA;
  `aria-live` em região separada; reduced motion.
- Layout via `style=""` ou utilities presentes até E-03 resolver o build
  (Vite do Admin não compila arbitrary novo no chat — verificado).
