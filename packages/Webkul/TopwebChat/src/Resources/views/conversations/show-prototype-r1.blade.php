{{-- PROTOTYPES R1/R1K DESCARTAVEIS: estados locais, sem mutacoes de backend. --}}
<x-admin::layouts>
    <x-slot:title>PROTOTYPE {{ ($prototypeVisualVariant ?? 'R1') === 'R1K' ? 'R1K' : 'R1' }} - TopwebChat</x-slot>

    @php
        $visualVariant = ($prototypeVisualVariant ?? 'R1') === 'R1K' ? 'R1K' : 'R1';
        $alternateVisualVariant = $visualVariant === 'R1K' ? 'R1' : 'R1K';
        $prototypeChrome = ! request()->boolean('clean');
        $scenarioDefinitions = [
            'linked-action' => 'Lead + proxima acao',
            'attachment-single' => 'Anexo unico',
            'attachments-multiple' => 'Multiplos anexos',
            'attachment-failed' => 'Falha em anexo',
            'offline' => 'Canal indisponivel',
            'no-action' => 'Sem proxima acao',
            'activity-create' => 'Criar proxima acao',
            'action-existing' => 'Proxima acao existente',
            'note-create' => 'Criar nota interna',
            'unlinked' => 'Sem Lead vinculado',
            'stage-open' => 'Trocar etapa',
            'dense' => 'Conversa longa',
        ];
        $legacyScenarios = [
            'linked' => 'linked-action',
            'attachment' => 'attachment-single',
        ];
        $requestedScenario = $legacyScenarios[$prototypeScenario] ?? $prototypeScenario;
        $scenario = array_key_exists($requestedScenario, $scenarioDefinitions) ? $requestedScenario : 'linked-action';
        $pane = in_array($prototypePane, ['queue', 'conversation'], true) ? $prototypePane : 'conversation';
        $isOffline = $scenario === 'offline';
        $isUnlinked = $scenario === 'unlinked';
        $activityCompleted = request()->boolean('activity_completed');
        $hasNextAction = ! in_array($scenario, ['unlinked', 'no-action', 'activity-create'], true) && ! $activityCompleted;
        $noteEditorOpen = $scenario === 'note-create';
        $activityEditorOpen = $scenario === 'activity-create';
        $stageMenuOpen = $scenario === 'stage-open';
        $stageErrorVisible = request()->boolean('stage_error');
        $personName = $conversation->person?->name ?? 'Contato permitido';
        $leadTitle = $isUnlinked ? null : ($conversation->lead?->title ?? 'Interesse Residencial Aurora');
        $stageName = $isUnlinked ? null : ($conversation->lead?->stage?->name ?? 'Qualificacao');
        $ownerName = $conversation->assignedUser?->name ?? 'Leonardo';
        $stageOptions = $pipelineStages->pluck('name')->filter()->values()->all();
        if (count($stageOptions) < 3) {
            $stageOptions = ['Novo Lead', 'Qualificacao', 'Proposta', 'Negociacao', 'Fechado'];
        }
        $attachments = match ($scenario) {
            'attachment-single' => [
                ['name' => 'Contrato.pdf', 'meta' => 'PDF · 1,8 MB', 'state' => 'Pronto'],
            ],
            'attachments-multiple' => [
                ['name' => 'Contrato.pdf', 'meta' => 'PDF · 1,8 MB', 'state' => 'Pronto'],
                ['name' => 'Planta_A.jpg', 'meta' => 'JPG · 840 KB', 'state' => 'Enviando'],
                ['name' => 'Planta_B.jpg', 'meta' => 'JPG · 760 KB', 'state' => 'Preparando'],
                ['name' => 'Memorial.pdf', 'meta' => 'PDF · 1,8 MB', 'state' => 'Enviado'],
            ],
            'attachment-failed' => [
                ['name' => 'Contrato.pdf', 'meta' => 'PDF · 1,8 MB', 'state' => 'Pronto'],
                ['name' => 'Planta_A.jpg', 'meta' => 'JPG · 840 KB', 'state' => 'Falhou'],
                ['name' => 'Planta_B.jpg', 'meta' => 'JPG · 760 KB', 'state' => 'Pronto'],
            ],
            default => [],
        };
        if ($scenario === 'attachments-multiple' && request()->integer('attachment_count') === 2) {
            $attachments = array_slice($attachments, 0, 2);
        }
        $scenarioKeys = array_keys($scenarioDefinitions);
        $scenarioIndex = array_search($scenario, $scenarioKeys, true);
        $previousScenario = $scenarioKeys[($scenarioIndex - 1 + count($scenarioKeys)) % count($scenarioKeys)];
        $nextScenario = $scenarioKeys[($scenarioIndex + 1) % count($scenarioKeys)];
        $prototypeUrl = fn (array $parameters = []) => route('admin.topweb_chat.show', array_merge([
            $conversation,
            'variant' => $visualVariant,
            'queue' => $queue,
            'scenario' => $scenario,
            'pane' => $pane,
            'clean' => request()->boolean('clean') ? 1 : null,
        ], $parameters));
    @endphp

    @push('styles')
    <style>
        .twp-root {
            --twp-primary: #0f766e; --twp-primary-soft: #ccfbf1; --twp-bg: #eef2f3;
            --twp-surface: #fff; --twp-elevated: #f8fafc; --twp-timeline: #f1f5f9;
            --twp-line: #dbe3e6; --twp-text: #17252b; --twp-muted: #64747b;
            --twp-warning: #a16207; --twp-warning-bg: #fef9c3; --twp-error: #b91c1c;
            --twp-error-bg: #fef2f2; --twp-success: #047857; --twp-incoming: #fff;
            --twp-outgoing: #0f766e; --twp-selected: #ccfbf1;
            color: var(--twp-text); font-size: 14px;
        }
        .dark .twp-root {
            --twp-primary: #2dd4bf; --twp-primary-soft: #123d3a; --twp-bg: #111827;
            --twp-surface: #18232c; --twp-elevated: #202d36; --twp-timeline: #151f27;
            --twp-line: #33434c; --twp-text: #edf3f5; --twp-muted: #b2c0c6;
            --twp-warning: #fde68a; --twp-warning-bg: #493b16; --twp-error: #fecaca;
            --twp-error-bg: #4c1d1d; --twp-success: #6ee7b7; --twp-incoming: #202d36;
            --twp-outgoing: #0f766e; --twp-selected: #123d3a;
        }
        .twp-root.is-krayin-next {
            --twp-primary: var(--brand-color); --twp-primary-soft: color-mix(in srgb, var(--brand-color) 9%, #fff);
            --twp-bg: #f3f4f6; --twp-surface: #fff; --twp-elevated: #f9fafb; --twp-timeline: #f4f6f8;
            --twp-line: #d1d5db; --twp-text: #1f2937; --twp-muted: #6b7280;
            --twp-warning: #92400e; --twp-warning-bg: #fffbeb; --twp-error: #b91c1c;
            --twp-error-bg: #fef2f2; --twp-success: #047857; --twp-incoming: #fff;
            --twp-outgoing: color-mix(in srgb, var(--brand-color) 86%, #1e3a5f); --twp-selected: var(--twp-primary-soft);
        }
        .dark .twp-root.is-krayin-next {
            --twp-primary: var(--brand-color); --twp-primary-soft: color-mix(in srgb, var(--brand-color) 18%, #182230);
            --twp-bg: #111827; --twp-surface: #182230; --twp-elevated: #202b3a; --twp-timeline: #151f2d;
            --twp-line: #374151; --twp-text: #f3f4f6; --twp-muted: #c0cad7;
            --twp-warning: #fcd34d; --twp-warning-bg: #3b3018; --twp-error: #fecaca;
            --twp-error-bg: #442126; --twp-success: #6ee7b7; --twp-incoming: #202b3a;
            --twp-outgoing: color-mix(in srgb, var(--brand-color) 66%, #1e293b); --twp-selected: var(--twp-primary-soft);
        }
        .twp-root *, .twp-root *::before, .twp-root *::after { box-sizing: border-box; }
        .twp-root [hidden] { display: none !important; }
        .twp-root button, .twp-root a, .twp-root input, .twp-root textarea, .twp-root select { font: inherit; }
        .twp-root button:focus-visible, .twp-root a:focus-visible, .twp-root input:focus-visible,
        .twp-root textarea:focus-visible, .twp-root select:focus-visible, .twp-switcher a:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--twp-primary) 55%, transparent); outline-offset: 2px;
        }
        .twp-shell { background: var(--twp-bg); border: 1px solid var(--twp-line); border-radius: 8px; display: grid; grid-template-columns: 286px minmax(480px,1fr) 310px; height: calc(100dvh - 130px); min-height: 560px; overflow: hidden; }
        .twp-queue, .twp-conversation, .twp-context { background: var(--twp-surface); min-height: 0; min-width: 0; }
        .twp-queue, .twp-conversation { display: flex; flex-direction: column; }
        .twp-context { border-left: 1px solid var(--twp-line); overflow-y: auto; padding: 18px; }
        .twp-queue-head { border-bottom: 1px solid var(--twp-line); padding: 16px 14px 12px; }
        .twp-eyebrow { color: var(--twp-muted); font-size: 12px; font-weight: 500; }
        .twp-title { font-size: 20px; font-weight: 700; line-height: 1.2; margin: 4px 0 0; }
        .twp-tabs { display: grid; gap: 0 12px; grid-template-columns: repeat(2,minmax(0,1fr)); margin-top: 12px; }
        .twp-tab { border-bottom: 2px solid transparent; color: var(--twp-muted); font-size: 11px; min-width: 0; padding: 8px 1px 7px; text-align: left; text-decoration: none; }
        .twp-tab span { display: block; line-height: 1.25; white-space: normal; }
        .twp-tab[aria-current="page"] { border-bottom-color: var(--twp-primary); color: var(--twp-primary); font-weight: 600; }
        .twp-search { display: block; margin-top: 10px; position: relative; }
        .twp-search input { background: var(--twp-surface); border: 1px solid var(--twp-line); border-radius: 4px; color: var(--twp-text); min-height: 38px; padding: 0 12px 0 38px; width: 100%; }
        .twp-search svg { color: var(--twp-muted); left: 12px; position: absolute; top: 10px; }
        .twp-list { flex: 1; min-height: 0; overflow-y: auto; }
        .twp-row { border-bottom: 1px solid var(--twp-line); color: inherit; display: block; min-height: 88px; padding: 12px 14px; text-decoration: none; }
        .twp-row:hover { background: var(--twp-elevated); }
        .twp-row.is-active { background: var(--twp-selected); box-shadow: inset 2px 0 var(--twp-primary); }
        .twp-row-top, .twp-row-meta, .twp-chat-head, .twp-head-actions, .twp-composer-row, .twp-attachment-item, .twp-inline-actions { align-items: center; display: flex; }
        .twp-row-top { gap: 8px; }.twp-row-name { flex: 1; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .twp-time, .twp-row-meta { color: var(--twp-muted); font-size: 11px; }.twp-preview { color: var(--twp-muted); margin: 5px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.twp-row-meta { justify-content: space-between; }
        .twp-badge { background: var(--twp-primary); border-radius: 999px; color: #fff; font-size: 11px; font-weight: 600; min-width: 22px; padding: 2px 7px; text-align: center; }
        .twp-state { align-items: center; display: inline-flex; gap: 5px; }.twp-state::before { background: var(--twp-primary); border-radius: 50%; content: ''; height: 7px; width: 7px; }
        .twp-conversation { border-left: 1px solid var(--twp-line); }
        .twp-chat-head { border-bottom: 1px solid var(--twp-line); flex: none; gap: 12px; min-height: 72px; padding: 10px 16px; }
        .twp-back, .twp-icon-button { align-items:center; background:transparent; border:1px solid var(--twp-line); border-radius:6px; color:var(--twp-text); cursor:pointer; display:inline-flex; flex:none; height:44px; justify-content:center; width:44px; }
        .twp-identity { flex:1; min-width:0; }.twp-identity h1 { font-size:16px; font-weight:700; margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.twp-identity p { color:var(--twp-muted); font-size:12px; margin:3px 0 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .twp-channel { color:var(--twp-success); font-size:11px; font-weight:600; }.twp-channel.is-offline { color:var(--twp-warning); }.twp-head-actions { gap:8px; }.twp-context-button { gap:7px; padding:0 12px; width:auto; }
        .twp-offline { background:var(--twp-warning-bg); border-bottom:1px solid color-mix(in srgb,var(--twp-warning) 45%,transparent); color:var(--twp-warning); flex:none; font-size:13px; padding:10px 16px; }
        .twp-timeline { background:var(--twp-timeline); display:flex; flex:1; flex-direction:column; gap:9px; min-height:0; overflow-y:auto; padding:18px clamp(16px,4vw,52px); }
        .twp-day { align-self:center; background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:6px; color:var(--twp-muted); font-size:11px; padding:4px 10px; }
        .twp-message { display:flex; }.twp-message.out { justify-content:flex-end; }.twp-bubble { background:var(--twp-incoming); border:1px solid var(--twp-line); border-radius:12px 12px 12px 4px; font-size:14px; font-weight:400; line-height:1.5; max-width:min(72%,620px); padding:9px 12px 7px; }
        .twp-message.out .twp-bubble { background:var(--twp-outgoing); border-color:transparent; border-radius:12px 12px 4px 12px; color:#fff; }.twp-bubble p { margin:0; overflow-wrap:anywhere; white-space:pre-wrap; }.twp-message-meta { font-size:10px; margin-top:5px; opacity:.76; text-align:right; }
        .twp-document { align-items:center; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); border-radius:6px; display:flex; gap:10px; margin-bottom:7px; padding:9px; }.twp-document strong,.twp-document span { display:block; }.twp-document span { font-size:11px; opacity:.8; }
        .twp-note { align-self:stretch; background:var(--twp-warning-bg); border-left:3px solid var(--twp-warning); border-radius:6px; color:var(--twp-text); margin:4px 7%; padding:10px 12px; }.twp-note strong { color:var(--twp-warning); display:block; font-size:11px; letter-spacing:.04em; text-transform:uppercase; }.twp-note-meta { color:var(--twp-muted); display:block; font-size:11px; margin:2px 0 7px; }
        .twp-composer { background:var(--twp-surface); border-top:1px solid var(--twp-line); flex:none; padding:10px 12px; }.twp-attachment-tray { border:1px solid var(--twp-line); border-radius:6px; margin:0 52px 8px; max-height:154px; overflow-y:auto; }.twp-attachment-summary { background:var(--twp-elevated); border-bottom:1px solid var(--twp-line); color:var(--twp-muted); font-size:11px; padding:7px 10px; }
        .twp-attachment-item { gap:9px; min-height:42px; padding:7px 9px; }.twp-attachment-item + .twp-attachment-item { border-top:1px solid var(--twp-line); }.twp-attachment-copy { flex:1; min-width:0; }.twp-attachment-copy strong,.twp-attachment-copy span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.twp-attachment-copy strong { font-size:12px; font-weight:600; }.twp-attachment-copy span { color:var(--twp-muted); font-size:11px; }.twp-file-state { color:var(--twp-success); font-size:11px; white-space:nowrap; }.twp-attachment-item.is-preparando .twp-file-state { color:var(--twp-muted); }.twp-attachment-item.is-enviando .twp-file-state { color:var(--twp-primary); font-weight:600; }.twp-attachment-item.is-falhou { background:var(--twp-error-bg); }.twp-attachment-item.is-falhou .twp-file-state { color:var(--twp-error); font-weight:600; }.twp-remove { border:0; height:32px; width:32px; }
        .twp-composer-row { gap:8px; }.twp-composer textarea { background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:6px; color:var(--twp-text); flex:1; line-height:1.45; max-height:108px; min-height:44px; padding:11px 12px; resize:none; }.twp-send,.twp-button { background:var(--twp-primary); border:1px solid var(--twp-primary); border-radius:6px; color:#fff; cursor:pointer; font-weight:600; min-height:40px; padding:0 14px; }.twp-send { min-height:44px; }.twp-button.secondary { background:transparent; border-color:var(--twp-line); color:var(--twp-text); }.twp-button.danger { background:var(--twp-error-bg); border-color:color-mix(in srgb,var(--twp-error) 35%,transparent); color:var(--twp-error); }
        .twp-send:disabled,.twp-composer textarea:disabled,.twp-icon-button:disabled,.twp-button:disabled { cursor:not-allowed; opacity:.48; }.twp-composer-help { color:var(--twp-muted); font-size:10px; margin:5px 52px 0; text-align:right; }
        .twp-section { border-bottom:1px solid var(--twp-line); padding:0 0 16px; }.twp-section + .twp-section { padding-top:16px; }.twp-section:last-child { border-bottom:0; }.twp-section h2 { font-size:14px; font-weight:600; margin:0 0 10px; }.twp-lead-title { font-size:15px; font-weight:700; margin:0 0 3px; }.twp-secondary { color:var(--twp-muted); font-size:12px; margin:0; }
        .twp-field { display:grid; gap:5px; margin-top:12px; }.twp-field label { color:var(--twp-muted); font-size:11px; font-weight:600; }.twp-control { background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:4px; color:var(--twp-text); min-height:40px; padding:8px 10px; width:100%; }.twp-stage-wrap { position:relative; }.twp-stage-trigger { align-items:center; background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:4px; color:var(--twp-text); cursor:pointer; display:flex; justify-content:space-between; min-height:40px; padding:8px 10px; width:100%; }.twp-stage-menu { background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:6px; box-shadow:0 10px 20px rgba(0,0,0,.12); display:none; left:0; padding:4px; position:absolute; right:0; top:44px; z-index:5; }.twp-stage-wrap.is-open .twp-stage-menu { display:block; }.twp-stage-option { background:transparent; border:0; border-radius:4px; color:var(--twp-text); cursor:pointer; display:flex; justify-content:space-between; padding:8px; text-align:left; width:100%; }.twp-stage-option:hover,.twp-stage-option.is-current { background:var(--twp-primary-soft); color:var(--twp-primary); }
        .twp-feedback { color:var(--twp-success); font-size:11px; margin:7px 0 0; }.twp-error { background:var(--twp-error-bg); border-left:3px solid var(--twp-error); border-radius:4px; color:var(--twp-error); font-size:11px; margin-top:8px; padding:8px; }
        .twp-action { border:1px solid var(--twp-line); border-radius:6px; padding:11px; }.twp-action strong { display:block; }.twp-action span { color:var(--twp-muted); display:block; font-size:12px; margin-top:3px; }.twp-inline-actions { flex-wrap:wrap; gap:6px; margin-top:10px; }.twp-inline-actions .twp-button { font-size:11px; min-height:34px; padding:0 10px; }.twp-link { color:var(--twp-primary); cursor:pointer; display:inline-block; font-size:12px; font-weight:600; margin-top:10px; text-decoration:none; }.twp-link:hover { text-decoration:underline; }.twp-empty { padding:4px 0; }.twp-empty p { color:var(--twp-muted); font-size:12px; margin:4px 0 0; }.twp-activity { border-left:2px solid var(--twp-line); padding:0 0 12px 12px; }.twp-activity:last-child { padding-bottom:0; }.twp-activity strong { display:block; font-size:12px; }.twp-activity span { color:var(--twp-muted); font-size:11px; }
        .twp-note-editor { background:var(--twp-warning-bg); border:1px solid color-mix(in srgb,var(--twp-warning) 35%,transparent); border-radius:6px; display:none; padding:10px; }.twp-note-editor.is-open { display:block; }.twp-note-editor strong { color:var(--twp-warning); display:block; font-size:11px; letter-spacing:.04em; margin-bottom:8px; }.twp-note-editor textarea { background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:4px; color:var(--twp-text); line-height:1.45; min-height:88px; padding:9px; resize:vertical; width:100%; }.twp-note-editor .twp-inline-actions { justify-content:flex-end; }
        .twp-scrim,.twp-flow-scrim { background:rgba(31,41,55,.52); border:0; display:none; inset:0; position:fixed; z-index:10002; }.twp-flow-scrim { z-index:10004; }.twp-flow { background:var(--twp-surface); border:1px solid var(--twp-line); border-radius:8px; box-shadow:0 18px 40px rgba(0,0,0,.22); display:none; left:50%; max-height:calc(100dvh - 48px); overflow-y:auto; padding:20px; position:fixed; top:50%; transform:translate(-50%,-50%); width:min(440px,calc(100vw - 32px)); z-index:10005; }.twp-flow.is-open,.twp-root.is-flow-open .twp-flow-scrim { display:block; }.twp-flow-head { align-items:center; display:flex; justify-content:space-between; }.twp-flow h2 { font-size:17px; margin:0; }.twp-flow-grid { display:grid; gap:0 12px; grid-template-columns:1fr 1fr; }.twp-flow-grid .is-wide { grid-column:1/-1; }.twp-flow-footer { display:flex; gap:8px; justify-content:flex-end; margin-top:18px; }
        .twp-context-close,.twp-back { display:none; }.twp-switcher { align-items:center; background:#17252b; border:1px solid #52636a; border-radius:999px; bottom:12px; box-shadow:0 10px 28px rgba(0,0,0,.28); color:#fff; display:flex; font-size:12px; gap:5px; left:50%; max-width:calc(100vw - 24px); overflow-x:auto; padding:5px; position:fixed; transform:translateX(-50%); z-index:11000; }.twp-switcher a { border-radius:999px; color:#fff; min-height:34px; padding:9px 11px; text-decoration:none; white-space:nowrap; }.twp-switcher .is-current { background:#fff; color:#17252b; font-weight:800; }.twp-switcher-label { font-weight:800; padding:0 6px; white-space:nowrap; }.twp-prototype-mark { color:#fca5a5; }
        @media (max-width:1439px) {
            .twp-shell { grid-template-columns:286px minmax(0,1fr); }.twp-context { border:1px solid var(--twp-line); border-radius:8px; box-shadow:0 10px 20px rgba(0,0,0,.16); display:none; height:calc(100dvh - 24px); max-width:340px; padding-top:70px; position:fixed; right:12px; top:12px; width:min(340px,88vw); z-index:10003; }.twp-root.is-context-open .twp-context,.twp-root.is-context-open .twp-scrim { display:block; }.twp-context-close { display:inline-flex; position:absolute; right:14px; top:14px; }
        }
        @media (max-width:1023px) {
            .twp-shell { display:block; height:calc(100dvh - 130px); min-height:520px; }.twp-queue,.twp-conversation { height:100%; }.twp-root.is-pane-conversation .twp-queue { display:none; }.twp-root.is-pane-queue .twp-conversation { display:none; }.twp-back { display:inline-flex; }.twp-row { min-height:82px; }
        }
        @media (max-width:767px) {
            .twp-shell { border-left:0; border-radius:0; border-right:0; height:calc(100dvh - 116px); margin:0 -16px; }.twp-chat-head { min-height:68px; padding:8px 10px; }.twp-channel { display:none; }.twp-context-button span { display:none; }.twp-timeline { padding:14px 10px; }.twp-bubble { max-width:88%; }.twp-send { font-size:12px; padding:0 10px; }.twp-send::after { content:none; }.twp-attachment-tray { margin-left:0; margin-right:0; }.twp-composer-help { display:none; }.twp-context { bottom:12px; height:min(78dvh,620px); left:12px; max-width:none; padding-top:58px; right:12px; top:auto; width:calc(100% - 24px); }.twp-flow { bottom:12px; left:12px; max-height:min(82dvh,680px); top:auto; transform:none; width:calc(100% - 24px); }.twp-flow-grid { grid-template-columns:1fr; }.twp-flow-grid .is-wide { grid-column:auto; }.twp-switcher a:not(.twp-arrow),.twp-switcher-label .twp-prototype-mark { display:none; }
        }
        @media (prefers-reduced-motion:reduce) { .twp-root *, .twp-root *::before { scroll-behavior:auto!important; transition:none!important; } }
    </style>
    @endpush

    <main
        class="twp-root {{ $visualVariant === 'R1K' ? 'is-krayin-next' : '' }} is-pane-{{ $pane }} {{ $prototypeContextOpen ? 'is-context-open' : '' }} {{ $activityEditorOpen ? 'is-flow-open' : '' }}"
        data-prototype-root
        data-scenario="{{ $scenario }}"
    >
        <div class="twp-shell">
            <nav class="twp-queue" aria-label="Fila de conversas">
                <div class="twp-queue-head">
                    <div class="twp-eyebrow">Workspace comercial</div>
                    <h1 class="twp-title">TopwebChat</h1>
                    <div class="twp-tabs" aria-label="Visoes da fila">
                        @foreach ([
                            'mine' => ['Minhas', $queueCounts['mine'] ?? 0],
                            'unassigned' => ['Sem atendente', $queueCounts['unassigned'] ?? 0],
                            'waiting' => ['Aguardando cliente', 4],
                            'all' => ['Todas', $queueCounts['all'] ?? 0],
                        ] as $key => [$label, $count])
                            @if ($key !== 'all' || auth()->guard('user')->user()->role?->permission_type === 'all')
                                <a class="twp-tab" href="{{ $prototypeUrl(['queue' => $key === 'waiting' ? 'mine' : $key, 'pane' => 'queue']) }}" @if ($queue === $key || ($key === 'waiting' && request('queue') === 'waiting')) aria-current="page" @endif>
                                    <span>{{ $label }} · {{ $count }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                    <label class="twp-search">
                        <span class="sr-only">Buscar por nome ou Lead</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="search" placeholder="Buscar nome ou Lead" readonly>
                    </label>
                </div>
                <div class="twp-list">
                    <a class="twp-row is-active" href="{{ $prototypeUrl(['pane' => 'conversation']) }}" aria-current="page">
                        <div class="twp-row-top"><span class="twp-row-name">{{ $personName }}</span><span class="twp-badge">3</span><span class="twp-time">2 min</span></div>
                        <p class="twp-preview">Perfeito, podemos agendar uma visita para amanha?</p>
                        <div class="twp-row-meta"><span class="twp-state">Aguardando voce</span><span>{{ $ownerName }}</span></div>
                    </a>
                    @foreach ([['Marina Costa','Proposta enviada. Aguardo seu retorno.','Aguardando cliente','18 min'],['Rafael Souza','Tenho interesse no apartamento de 2 quartos.','Aguardando voce','32 min'],['Contato protegido','Identidade liberada somente apos claim.','Sem atendente','1 h'],['Ana Lima','Pode me enviar as plantas disponiveis?','Aguardando voce','2 h']] as $demo)
                        <a class="twp-row" href="{{ $prototypeUrl(['pane' => 'conversation']) }}"><div class="twp-row-top"><span class="twp-row-name">{{ $demo[0] }}</span><span class="twp-time">{{ $demo[3] }}</span></div><p class="twp-preview">{{ $demo[1] }}</p><div class="twp-row-meta"><span class="twp-state">{{ $demo[2] }}</span><span>Equipe comercial</span></div></a>
                    @endforeach
                </div>
            </nav>

            <section class="twp-conversation" aria-label="Conversa selecionada">
                <header class="twp-chat-head">
                    <a class="twp-back" href="{{ $prototypeUrl(['pane' => 'queue']) }}" aria-label="Voltar para a fila"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
                    <div class="twp-identity">
                        <div class="twp-channel {{ $isOffline ? 'is-offline' : '' }}">{{ $isOffline ? 'Canal indisponivel - historico preservado' : 'Canal conectado - atualizado agora' }}</div>
                        <h1>{{ $personName }}</h1>
                        <p>{{ $leadTitle ?? 'Sem Lead vinculado' }} · {{ $ownerName }}</p>
                    </div>
                    <div class="twp-head-actions"><button class="twp-icon-button twp-context-button" type="button" data-context-open aria-controls="prototype-context" aria-expanded="{{ $prototypeContextOpen ? 'true' : 'false' }}"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3h18v18H3zM9 3v18"/></svg><span>Contexto</span></button></div>
                </header>
                @if ($isOffline)
                    <div class="twp-offline" role="status"><strong>Envio temporariamente indisponivel.</strong> Consulte o historico normalmente; nada sera perdido.</div>
                @endif
                <div class="twp-timeline" id="topweb-chat-prototype-timeline">
                    <div class="twp-day">Hoje</div>
                    @if ($scenario === 'dense')
                        @foreach ([
                            ['in','08:42','Bom dia. Gostaria de confirmar as opcoes de dois quartos.'],
                            ['out','08:44','Bom dia! Tenho tres unidades que combinam com o seu perfil.'],
                            ['in','08:51','Alguma recebe sol pela manha?'],
                            ['out','08:55','As unidades 704 e 804 recebem sol da manha na sala.'],
                            ['in','09:04','E como funciona a entrada durante a obra?'],
                            ['out','09:08','Vou separar a simulacao e o fluxo de pagamento para voce.'],
                            ['in','09:21','Prefiro visitar antes de decidir. Pode ser amanha?'],
                            ['out','09:25','Sim. Tenho disponibilidade as 10:30 ou as 15:00.'],
                        ] as [$direction,$time,$message])
                            <article class="twp-message {{ $direction === 'out' ? 'out' : '' }}"><div class="twp-bubble"><p>{{ $message }}</p><div class="twp-message-meta">{{ $time }}{{ $direction === 'out' ? ' · Entregue' : '' }}</div></div></article>
                        @endforeach
                    @else
                        <article class="twp-message"><div class="twp-bubble"><p>Ola! Vi o anuncio do residencial e queria entender melhor as opcoes de planta.</p><div class="twp-message-meta">09:18</div></div></article>
                        <article class="twp-message out"><div class="twp-bubble"><p>Bom dia! Temos plantas de 2 e 3 quartos. Posso te ajudar a comparar as unidades disponiveis.</p><div class="twp-message-meta">09:20 · Lida</div></div></article>
                        <article class="twp-message"><div class="twp-bubble"><p>Procuro uma unidade com dois quartos, varanda e uma vaga. Quero entender a orientacao solar e o financiamento.</p><div class="twp-message-meta">09:27</div></div></article>
                        <aside class="twp-note"><strong>Nota interna - somente equipe</strong><span class="twp-note-meta">Leonardo · 09:29</span>Cliente prioriza iluminacao natural. Confirmar orientacao antes da visita.</aside>
                        <article class="twp-message out"><div class="twp-bubble"><div class="twp-document"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg><div><strong>Plantas_Aurora.pdf</strong><span>PDF · 2,4 MB</span></div></div><p>A unidade 704 recebe sol da manha.</p><div class="twp-message-meta">09:34 · Entregue</div></div></article>
                        <article class="twp-message"><div class="twp-bubble"><p>Perfeito, podemos agendar uma visita para amanha?</p><div class="twp-message-meta">09:38</div></div></article>
                    @endif
                </div>
                <div class="twp-composer">
                    @if ($attachments !== [])
                        <div class="twp-attachment-tray" data-attachment-tray>
                            @if (count($attachments) >= 4)<div class="twp-attachment-summary"><strong>{{ count($attachments) }} anexos · 5,2 MB</strong> · lista compacta</div>@endif
                            @foreach ($attachments as $index => $attachment)
                                <div class="twp-attachment-item is-{{ strtolower($attachment['state']) }}" data-attachment-item>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                                    <div class="twp-attachment-copy"><strong>{{ $attachment['name'] }}</strong><span>{{ $attachment['meta'] }}</span></div>
                                    <span class="twp-file-state">{{ $attachment['state'] }}</span>
                                    <button class="twp-icon-button twp-remove" type="button" data-remove-attachment aria-label="Remover {{ $attachment['name'] }}" onclick="this.closest('[data-attachment-item]').remove()"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg></button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <div class="twp-composer-row">
                        <button class="twp-icon-button" type="button" aria-label="Anexar arquivos" title="Anexar um ou mais arquivos" @disabled($isOffline)><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m21 12-8.5 8.5a5.5 5.5 0 0 1-7.78-7.78L12 5.4a3.67 3.67 0 0 1 5.2 5.2l-7.07 7.07a1.83 1.83 0 0 1-2.6-2.6L14.6 8"/></svg></button>
                        <textarea rows="1" placeholder="Responder..." data-composer-input @disabled($isOffline)></textarea>
                        <button class="twp-send" type="button" data-send-message @disabled($isOffline)>Enviar</button>
                    </div>
                    <div class="twp-composer-help">Enter envia · Shift+Enter cria nova linha</div>
                </div>
            </section>

            <button class="twp-scrim" type="button" data-context-close aria-label="Fechar contexto"></button>
            <aside class="twp-context" id="prototype-context" aria-label="Contexto comercial">
                <button class="twp-icon-button twp-context-close" type="button" data-context-close aria-label="Fechar contexto"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg></button>
                <section class="twp-section">
                    <h2>Negociacao</h2>
                    @if ($leadTitle)
                        <p class="twp-lead-title">{{ $leadTitle }}</p>
                        <div class="twp-field"><label>Etapa</label><div class="twp-stage-wrap {{ $stageMenuOpen && ! $stageErrorVisible ? 'is-open' : '' }}" data-stage-wrap><button class="twp-stage-trigger" type="button" data-stage-trigger aria-expanded="{{ $stageMenuOpen && ! $stageErrorVisible ? 'true' : 'false' }}"><span data-stage-label>{{ $stageName }}</span><span aria-hidden="true">⌄</span></button><div class="twp-stage-menu" role="listbox" aria-label="Etapas validas do pipeline">@foreach ($stageOptions as $option)<button class="twp-stage-option {{ $option === $stageName ? 'is-current' : '' }}" type="button" data-stage-option="{{ $option }}" role="option" aria-selected="{{ $option === $stageName ? 'true' : 'false' }}"><span>{{ $option }}</span>@if ($option === $stageName)<span aria-hidden="true">✓</span>@endif</button>@endforeach</div></div><p class="twp-feedback" data-stage-feedback hidden>Etapa atualizada.</p><p class="twp-error" data-stage-error @if (! $stageErrorVisible) hidden @endif>Nao foi possivel alterar a etapa. A etapa anterior foi mantida.</p></div>
                        <div class="twp-field"><label>Responsavel</label><p class="twp-secondary">{{ $ownerName }}</p></div>
                        <a class="twp-link" href="#">Abrir Lead completo</a>
                    @else
                        <div class="twp-empty"><strong>Nenhum Lead vinculado</strong><p>Vincule uma negociacao permitida para acessar etapa e atividades.</p><a class="twp-link" href="#">Vincular Lead</a></div>
                    @endif
                </section>
                <section class="twp-section" data-next-action-section>
                    <h2>Proxima acao</h2>
                    <div data-next-action-content>
                        @if ($hasNextAction)
                            <div class="twp-action"><strong>Visita · Amanha · 10:30</strong><span>Pendente · {{ $ownerName }}</span><div class="twp-inline-actions"><button class="twp-button secondary" type="button" data-complete-activity>Concluir</button><button class="twp-button secondary" type="button" data-open-activity>Reagendar</button><button class="twp-button secondary" type="button" aria-label="Mais opcoes">...</button></div></div><a class="twp-link" href="#">Gerenciar atividade</a>
                        @else
                            <div class="twp-empty"><strong>Nenhuma proxima acao</strong><p>{{ $activityCompleted ? 'A visita foi concluida. Nenhuma outra Activity acionavel esta pendente.' : 'Registre o proximo passo para manter a negociacao em movimento.' }}</p><button class="twp-link" type="button" data-open-activity>Criar proxima acao</button></div>
                        @endif
                    </div>
                </section>
                @if ($leadTitle)
                    <section class="twp-section"><h2>Atividade recente</h2><div data-recent-activity>@if ($activityCompleted)<div class="twp-activity"><strong>Visita concluida</strong><span>Agora · {{ $ownerName }}</span></div>@endif<div class="twp-activity"><strong>Ligacao concluida</strong><span>Hoje · 08:45 · {{ $ownerName }}</span></div><div class="twp-activity"><strong>Follow-up concluido</strong><span>Ontem · 16:20 · Equipe comercial</span></div></div></section>
                    <section class="twp-section"><h2>Notas internas</h2><div class="twp-note-editor {{ $noteEditorOpen ? 'is-open' : '' }}" data-note-editor><strong>Nota interna - somente equipe</strong><textarea data-note-input aria-label="Conteudo da nota interna" placeholder="Registre uma observacao visivel somente para a equipe.">{{ $noteEditorOpen ? 'Cliente pediu retorno apos as 18h.' : '' }}</textarea><div class="twp-inline-actions"><button class="twp-button secondary" type="button" data-cancel-note>Cancelar</button><button class="twp-button" type="button" data-add-note>Adicionar nota</button></div></div><button class="twp-link" type="button" data-open-note @if ($noteEditorOpen) hidden @endif>+ Adicionar nota</button></section>
                @endif
            </aside>
        </div>

        <button class="twp-flow-scrim" type="button" data-close-activity aria-label="Fechar criacao de atividade"></button>
        <section class="twp-flow {{ $activityEditorOpen ? 'is-open' : '' }}" data-activity-flow role="dialog" aria-modal="true" aria-labelledby="activity-flow-title">
            <div class="twp-flow-head"><h2 id="activity-flow-title">Criar proxima acao</h2><button class="twp-icon-button" type="button" data-close-activity aria-label="Fechar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg></button></div>
            <div class="twp-flow-grid"><div class="twp-field"><label for="activity-type">Tipo</label><select class="twp-control" id="activity-type"><option>Visita</option><option>Ligacao</option><option>Reuniao</option><option>Follow-up</option></select></div><div class="twp-field"><label for="activity-owner">Responsavel</label><select class="twp-control" id="activity-owner"><option>{{ $ownerName }}</option><option>Equipe comercial</option></select></div><div class="twp-field"><label for="activity-date">Data</label><input class="twp-control" id="activity-date" type="date" value="2026-09-16"></div><div class="twp-field"><label for="activity-time">Horario</label><input class="twp-control" id="activity-time" type="time" value="10:30"></div><div class="twp-field is-wide"><label for="activity-details">Detalhes opcionais</label><textarea class="twp-control" id="activity-details" rows="3" placeholder="Conteudo sujeito a politica de dados sensiveis"></textarea></div></div>
            <div class="twp-flow-footer"><button class="twp-button secondary" type="button" data-close-activity>Cancelar</button><button class="twp-button" type="button" data-create-activity>Criar atividade</button></div>
        </section>
    </main>

    @if ($prototypeChrome)
        <nav class="twp-switcher" aria-label="Cenarios do prototype">
            <a class="twp-arrow" href="{{ $prototypeUrl(['scenario' => $previousScenario]) }}" aria-label="Cenario anterior">←</a>
            <span class="twp-switcher-label"><span class="twp-prototype-mark">PROTOTYPE {{ $visualVariant }} · </span>{{ $scenarioDefinitions[$scenario] }}</span>
            <a href="{{ $prototypeUrl(['variant' => $alternateVisualVariant]) }}">Comparar {{ $alternateVisualVariant }}</a>
            @foreach ($scenarioDefinitions as $key => $label)<a class="{{ $scenario === $key ? 'is-current' : '' }}" href="{{ $prototypeUrl(['scenario' => $key]) }}" @if ($scenario === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach
            <a class="twp-arrow" href="{{ $prototypeUrl(['scenario' => $nextScenario]) }}" aria-label="Proximo cenario">→</a>
        </nav>
    @endif

    @push('scripts')
    <script>
        (() => {
            const initialize = () => {
                const root = document.querySelector('[data-prototype-root]');
                if (! root) return;
                root.dataset.interactive = 'ready';
            const timeline = root.querySelector('#topweb-chat-prototype-timeline');
            const contextButton = root.querySelector('[data-context-open]');
            if (window.matchMedia('(min-width: 1440px)').matches) contextButton?.setAttribute('aria-expanded', 'true');
            const setContext = (open) => {
                root.classList.toggle('is-context-open', open);
                contextButton?.setAttribute('aria-expanded', String(open));
            };
            const setActivity = (open) => {
                root.classList.toggle('is-flow-open', open);
                root.querySelector('[data-activity-flow]')?.classList.toggle('is-open', open);
                if (open) root.querySelector('#activity-type')?.focus();
            };
            window.r1kPrototype = {
                selectStage(value) {
                    if (value === 'Fechado') {
                        root.querySelector('[data-stage-error]').hidden = false;
                        root.querySelector('[data-stage-feedback]').hidden = true;
                    } else {
                        root.querySelector('[data-stage-label]').textContent = value;
                        root.querySelector('[data-stage-feedback]').hidden = false;
                        root.querySelector('[data-stage-error]').hidden = true;
                    }
                    root.querySelector('[data-stage-wrap]')?.classList.remove('is-open');
                    root.querySelector('[data-stage-trigger]')?.setAttribute('aria-expanded', 'false');
                },
                completeActivity() {
                    root.querySelector('[data-next-action-content]').innerHTML = '<div class="twp-empty"><strong>Nenhuma proxima acao</strong><p>A visita foi concluida. Nenhuma outra Activity acionavel esta pendente.</p><button class="twp-link" type="button" data-open-activity>Criar proxima acao</button></div>';
                    root.querySelector('[data-recent-activity]')?.insertAdjacentHTML('afterbegin', '<div class="twp-activity"><strong>Visita concluida</strong><span>Agora · {{ e($ownerName) }}</span></div>');
                },
            };
            document.addEventListener('click', (event) => {
                const option = event.target.closest('[data-stage-option]');
                if (option) window.r1kPrototype.selectStage(option.dataset.stageOption);
                if (event.target.closest('[data-complete-activity]')) window.r1kPrototype.completeActivity();
            }, true);
            contextButton?.addEventListener('click', () => setContext(true));
            root.querySelectorAll('[data-context-close]').forEach((button) => button.addEventListener('click', () => setContext(false)));
            root.querySelectorAll('[data-open-activity]').forEach((button) => button.addEventListener('click', () => setActivity(true)));
            root.querySelectorAll('[data-close-activity]').forEach((button) => button.addEventListener('click', () => setActivity(false)));
            root.querySelectorAll('[data-remove-attachment]').forEach((button) => button.addEventListener('click', () => {
                button.closest('[data-attachment-item]')?.remove();
                if (! root.querySelector('[data-attachment-item]')) root.querySelector('[data-attachment-tray]')?.remove();
            }));
            root.querySelector('[data-create-activity]')?.addEventListener('click', () => {
                root.querySelector('[data-next-action-content]').innerHTML = '<div class="twp-action"><strong>Visita · 16 set · 10:30</strong><span>Pendente · {{ e($ownerName) }}</span><div class="twp-inline-actions"><button class="twp-button secondary" type="button" data-complete-activity>Concluir</button><button class="twp-button secondary" type="button">Reagendar</button></div></div><p class="twp-feedback">Atividade criada. Esta e agora a proxima acao.</p>';
                setActivity(false);
            });
            root.addEventListener('click', (event) => {
                if (event.target.closest('[data-open-activity]')) setActivity(true);
                const remove = event.target.closest('[data-remove-attachment]');
                if (remove) {
                    remove.closest('[data-attachment-item]')?.remove();
                    if (! root.querySelector('[data-attachment-item]')) root.querySelector('[data-attachment-tray]')?.remove();
                }
            });
            const stageWrap = root.querySelector('[data-stage-wrap]');
            root.querySelector('[data-stage-trigger]')?.addEventListener('click', () => {
                const open = ! stageWrap.classList.contains('is-open');
                stageWrap.classList.toggle('is-open', open);
                root.querySelector('[data-stage-trigger]').setAttribute('aria-expanded', String(open));
            });
            root.querySelectorAll('[data-stage-option]').forEach((option) => option.addEventListener('click', () => {
                const previous = root.querySelector('[data-stage-label]').textContent;
                if (option.dataset.stageOption === 'Fechado') {
                    root.querySelector('[data-stage-error]').hidden = false;
                    root.querySelector('[data-stage-feedback]').hidden = true;
                    root.querySelector('[data-stage-label]').textContent = previous;
                } else {
                    root.querySelector('[data-stage-label]').textContent = option.dataset.stageOption;
                    root.querySelector('[data-stage-feedback]').hidden = false;
                    root.querySelector('[data-stage-error]').hidden = true;
                }
                stageWrap.classList.remove('is-open');
                root.querySelector('[data-stage-trigger]').setAttribute('aria-expanded', 'false');
            }));
            const noteEditor = root.querySelector('[data-note-editor]');
            const noteTrigger = root.querySelector('[data-open-note]');
            noteTrigger?.addEventListener('click', () => { noteEditor.classList.add('is-open'); noteTrigger.hidden = true; root.querySelector('[data-note-input]')?.focus(); });
            root.querySelector('[data-cancel-note]')?.addEventListener('click', () => { noteEditor.classList.remove('is-open'); noteTrigger.hidden = false; });
            root.querySelector('[data-add-note]')?.addEventListener('click', () => {
                const input = root.querySelector('[data-note-input]');
                if (! input.value.trim()) return;
                timeline?.insertAdjacentHTML('beforeend', `<aside class="twp-note"><strong>Nota interna - somente equipe</strong><span class="twp-note-meta">{{ e($ownerName) }} · agora</span>${input.value.replace(/[&<>]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[char]))}</aside>`);
                input.value = ''; noteEditor.classList.remove('is-open'); noteTrigger.hidden = false; timeline.scrollTop = timeline.scrollHeight;
            });
            const composer = root.querySelector('[data-composer-input]');
            const sendMessage = () => {
                const text = composer?.value.trim();
                if (! text && ! root.querySelector('[data-attachment-item]')) return;
                timeline?.insertAdjacentHTML('beforeend', `<article class="twp-message out"><div class="twp-bubble"><p>${text || 'Anexos enviados.'}</p><div class="twp-message-meta">agora · Enviando</div></div></article>`);
                if (composer) composer.value = '';
                root.querySelector('[data-attachment-tray]')?.remove();
                timeline.scrollTop = timeline.scrollHeight;
            };
            root.querySelector('[data-send-message]')?.addEventListener('click', sendMessage);
            composer?.addEventListener('keydown', (event) => { if (event.key === 'Enter' && ! event.shiftKey) { event.preventDefault(); sendMessage(); } });
            document.addEventListener('keydown', (event) => {
                if (event.target.matches('[data-composer-input]') && event.key === 'Enter' && ! event.shiftKey) {
                    event.preventDefault();
                    sendMessage();
                }
            }, true);
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') { setActivity(false); setContext(false); stageWrap?.classList.remove('is-open'); }
                if (['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName)) return;
                if (event.key === 'ArrowLeft') document.querySelector('.twp-switcher a:first-child')?.click();
                if (event.key === 'ArrowRight') document.querySelector('.twp-switcher a:last-child')?.click();
            });
            if (timeline) timeline.scrollTop = timeline.scrollHeight;
                root.dataset.interactiveEnd = 'ready';
            };

            window.addEventListener('load', () => window.setTimeout(initialize, 0), { once: true });
        })();
    </script>
    @endpush
</x-admin::layouts>
