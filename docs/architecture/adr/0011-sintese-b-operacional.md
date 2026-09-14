---
doc_id: adr-0011
type: adr
status: active
authority: canonical
scope: E14
last_verified_commit: 205c296
update_triggers: [workspace-target, decision-superseded]
related: [docs/modules/topweb-chat/UX.md]
---

# ADR 0011: Síntese B-operacional como target do TopwebChat

- Status: aceito
- Data: 2026-09-12

## Contexto

O protótipo descartável `?variant=A|B` (D-01, validado com screenshots reais em
desktop 1440 e mobile 390) confirmou: A entrega familiaridade sem operação; B
entrega operação (fila priorizada, 3 zonas, contexto persistente). A decisão
precisava sair de “qual layout é melhor” para “o que o corretor precisa”.

## Decisão

- Base estrutural e operacional: **variante B** (3 zonas, fila orientada a
  trabalho, conversa dominante, contexto persistente/recolhível).
- De A, preservam-se só padrões de familiaridade: leitura da timeline, composer
  simples, anexar/enviar reconhecíveis, estados compreensíveis, retorno
  explícito, foco/scroll previsíveis. Não se herdam: fila cronológica, contexto
  escondido, 2 zonas fixas.
- A e B passam a protótipos de validação, não targets; o produto é a síntese.
- Densidade: ≥1440 fila 280–300 + conversa fluida + contexto 300–320 visível e
  recolhível; 1366 fila + conversa, contexto em drawer fechado por padrão;
  tablet master-detail; mobile single-pane com retorno explícito e composer fixo.
- Fila como decisão (contadores honestos, sem SLA falso, sem métricas
  financeiras); contexto recolhido mantém projeção mínima (Lead, etapa, próxima
  ação segura); conversa sempre dominante.

## Consequências

- Protótipo A/B apagado; MASTER persistido com estas regras.
- E-01 (tokens completos), E-02/E-03 e verticais constroem sobre esta decisão.
- Futura variante C só se responder pergunta nova, nunca para reacender A×B.
