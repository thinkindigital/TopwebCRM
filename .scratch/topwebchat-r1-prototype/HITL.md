# R1/R1K Approved UX Baseline

## Scope

Comparacao descartavel dos estilos R1 e R1K, com refinamento final da R1K. Os
controles simulam estado apenas no navegador; nenhuma acao chama endpoints de
mutacao ou altera dados do CRM.

## Token map

| Token R1K | Origem | Light | Dark |
|---|---|---|---|
| `workspace.background` | Krayin/TopwebCRM gray | `#f3f4f6` | `#111827` |
| `surface` | Krayin/TopwebCRM gray | `#ffffff` | `#182230` |
| `surface.elevated` | derivado de surface | `#f9fafb` | `#202b3a` |
| `timeline` | derivado neutro | `#f4f6f8` | `#151f2d` |
| `border` | Krayin gray | `#d1d5db` | `#374151` |
| `text.primary` | Krayin gray | `#1f2937` | `#f3f4f6` |
| `text.muted` | Krayin gray | `#6b7280` | `#c0cad7` |
| `primary` | TopwebCRM | `var(--brand-color)` | `var(--brand-color)` |
| `primary.soft` | derivado de primary | `brand 9% + white` | `brand 18% + surface` |
| `warning` | semantic | `#92400e` | `#fcd34d` |
| `warning.soft` | semantic | `#fffbeb` | `#3b3018` |
| `error` | semantic | `#b91c1c` | `#fecaca` |
| `message.outgoing` | semantic + primary | `brand 86% + navy` | `brand 66% + slate` |
| `message.incoming` | semantic + surface | `#ffffff` | `#202b3a` |
| `conversation.selected` | semantic + primary.soft | `primary.soft` | `primary.soft` |

TopwebChat adiciona semantica de componente; tipografia, brand color, escala gray,
spacing 4/8, dark mode e controles continuam subordinados ao Admin Krayin.

## Prototype behavior

- Nota interna nasce no contexto por `+ Adicionar nota`, usa editor amber, acao
  `Adicionar nota` e entra na timeline como evento de equipe, nunca como bubble.
- Attachments usam tray limitado acima do composer, remocao individual e estado
  por item (`Preparando`, `Pronto`, `Enviando`, `Enviado`, `Falhou`). Quatro ou
  mais arquivos ganham resumo e scroll interno.
- Etapa usa somente opcoes apresentadas como pertencentes ao pipeline. Sucesso e
  rejeicao mantem feedback local; a rejeicao preserva o valor anterior.
- Activity abre em overlay localizado, usa controles de data/hora/tipo/responsavel,
  pode ser concluida no contexto e migra visualmente para Atividade recente.
- Composer mantem Enter para enviar e Shift+Enter para nova linha.

## Backend gaps found

1. Outbound TopwebChat aceita um unico `media` ou `document` por request e cria
   uma Message por arquivo. Nao existe contrato de batch, falha parcial ou estado
   individual de varios arquivos. A UX multipla deste prototype e alvo futuro.
2. TopwebChat le a proxima Activity, mas nao possui endpoints inline para criar,
   concluir ou recalcular o contexto. O CRM generico possui create/update.
3. ACTIONABLE/RECORD/SYSTEM ainda e aproximado por exclusoes em
   `NextActionService`; nao existe `ActivityActionabilityPolicy` centralizada.
4. O context polling atualiza somente a timeline. Alteracoes de Activity exigem
   reload para recalcular proxima acao e indicadores da fila.
5. O design-system canonico registrava teal do target anterior. O checkpoint de
   promocao passou a documentar R1 e R1K, com `var(--brand-color)` na R1K.
6. O composer atual envia texto de attachment como `content`, enquanto os ramos
   de media/documento do controller leem `caption`; a legenda pode ser ignorada.
7. Os inputs atuais de media e documento sao independentes e singulares. Ambos
   podem permanecer preenchidos, mas o backend processa primeiro `media`.
8. O picker anuncia TXT/XLS/XLSX, mas a allowlist de MIME do service nao aceita
   esses formatos. Audio existe no backend, mas nao possui picker no composer.
9. A documentacao OpenWA registra fallback `send-document` para
   `whatsapp-web.js`; o adapter atual seleciona endpoints nativos por MIME.
10. A troca de etapa possui rota/service reais, mas nao ha teste direto cobrindo
    owner, ACL, `leads.edit`, etapa de outro pipeline e eventos emitidos.

## Ergonomic findings

- Quatro views da fila funcionam sem scrollbar nativa usando grid 2x2.
- Superficies dark progridem de workspace para timeline e messages sem preto
  absoluto; accent saturado fica restrito a selecao, acao e outgoing.
- Composer, fila, conversa e contexto mantem posicoes estaveis; editores usam
  overlay ou expansao localizada.
- Contexto comercial prioriza Negociacao, Proxima acao, Atividade recente e
  Notas internas; dados administrativos nao competem com o ciclo comercial.
- Mensagens permanecem em 14px/1.5, metadados em 10-12px e largura controlada.

## Evidence

- 23 screenshots reais: desktop light/dark, 1366 e mobile light/dark.
- Capturas finais: `screenshots/r1k-final/`; matrizes HITL:
  `screenshots/comparisons-final/`.
- Browser assertions: tema global, scenario correto, sem overflow estrutural,
  tabs sem scrollbar desktop, progressao dark sem preto absoluto e texto de
  mensagem com contraste AA.
- Fluxos locais exercitados apos a captura: envio e quebra de linha, remocao do
  segundo anexo, criacao/conclusao de Activity, nota interna e troca de etapa.
- Blade compilation, PHP syntax, Pint e `git diff --check` passaram.
