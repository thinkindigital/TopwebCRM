# MASTER - TopwebChat Design System

> Fonte: sintese B-operacional (ADR 0011) + HITL R1/R1K (ADR 0014).
> Subordinado a Krayin/Admin e as policies. R1 e R1K sao estilos do mesmo
> workspace, nunca implementacoes ou comportamentos distintos.

## Tokens compartilhados

- Tipografia: Inter do Admin Krayin, sem fonte externa propria.
- Spacing: escala 4/8 do Admin; componentes usam 4/8/12/16 e secoes 16/24/32.
- Primary: `var(--brand-color)` quando o perfil usa identidade TopwebCRM.
- Semanticos: success/warning/error/info + unread + nota ambar + in/out +
  restricted + offline + unknown. Nenhum estado depende apenas de cor.
- Focus: anel visivel; disabled: sem acao + enfase reduzida; reduced motion;
  alvos interativos minimos de 44 px.

| Token | Origem | R1 light/dark | R1K light/dark |
|---|---|---|---|
| `workspace.background` | Krayin gray | `#eef2f3` / `#111827` | `#f3f4f6` / `#111827` |
| `surface` | Krayin gray | `#ffffff` / `#18232c` | `#ffffff` / `#182230` |
| `surface.elevated` | derivado | `#f8fafc` / `#202d36` | `#f9fafb` / `#202b3a` |
| `timeline` | derivado neutro | `#f1f5f9` / `#151f27` | `#f4f6f8` / `#151f2d` |
| `border` | Krayin gray | `#dbe3e6` / `#33434c` | `#d1d5db` / `#374151` |
| `text.primary` | Krayin gray | `#17252b` / `#edf3f5` | `#1f2937` / `#f3f4f6` |
| `text.muted` | Krayin gray | `#64747b` / `#b2c0c6` | `#6b7280` / `#c0cad7` |
| `primary` | perfil/TopwebCRM | `#0f766e` / `#2dd4bf` | `var(--brand-color)` |
| `warning.soft` | semantic | `#fef9c3` / `#493b16` | `#fffbeb` / `#3b3018` |
| `message.incoming` | semantic + surface | surface | `#ffffff` / `#202b3a` |
| `message.outgoing` | semantic + primary | primary | brand moderado por tema |
| `conversation.selected` | semantic + primary | primary soft | brand soft |

## Perfis oficiais

- **R1:** sintese visual original, teal operacional, bolhas e superficies mais
  expressivas. Mantem integralmente o contrato do workspace.
- **R1K:** Krayin Next, default recomendado. Usa `brandColor`, gray surfaces,
  radii e controles do Admin com semanticas operacionais do TopwebChat.
- A diferenca entre perfis vive em tokens/classes sobre o mesmo markup. Nao
  duplicar Blade, JavaScript, endpoints, autorizacao ou testes funcionais.

## Tipografia e forma

- Radii moderados (workspace continuo, sem cards soltos): bolhas 12-16, paineis
  6-12 e pills/badges 9999.
- Hierarquia: contato/nome 600-700, corpo 400/14 com line-height 1.5,
  meta/tempo 10-12 secundario. Uppercase somente para labels semanticas curtas.

## Regras do workspace

- 3 zonas: fila 280-300 + conversa fluida dominante + contexto 300-320
  (recolhivel, com projecao minima Lead/etapa/proxima-acao no header).
- 1366: fila + conversa; contexto em drawer fechado por padrao.
- Tablet master-detail; mobile single-pane, retorno explicito, composer fixo.
- Fila: contadores honestos, linha com identidade/preview/badge/estado/
  responsavel/tempo; sem SLA falso, sem metricas financeiras.
- Timeline: bolhas familiares, status semantico, notas ambar inequivocas,
  midia restrita diferente de falha.
- Composer: clip SVG, preview, Enter/Shift+Enter, foco preservado e
  `operation_key`.
- Nota interna nasce no contexto e usa `Adicionar nota`; nunca compartilha
  silenciosamente o modo ou o botao `Enviar` do WhatsApp.
- Attachment tray tem altura limitada e estado por item. A implementacao deve
  refletir a capacidade real do backend; UX de batch nao autoriza contrato novo.
- Contexto segue Negociacao -> Proxima acao -> Atividade recente -> Notas
  internas.
- Icones SVG; emoji so como conteudo. Alvos 44 px; foco visivel; AA;
  `aria-live` em regiao separada; reduced motion.
