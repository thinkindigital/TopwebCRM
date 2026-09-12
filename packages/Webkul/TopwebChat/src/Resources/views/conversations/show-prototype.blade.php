{{-- PROTOTYPE-UX S02r3 — DESCARTÁVEL. APAGAR após o veredito. Mesma rota/auth/dados.
     Layout 100% via style="" (o build Vite do Admin NÃO compila classes arbitrary
     novas em views do TopwebChat — verificado no CSS em produção). Cores/espaços
     usam utilities padrão. Scroll SÓ em fila/timeline; composer fixo. --}}
<x-admin::layouts>
    <x-slot:title>
        PROTOTIPO {{ $variant }} — {{ $conversation->person?->name ?? 'Chat' }}
    </x-slot>

    @php
        $user = auth()->guard('user')->user();
        $sensitiveData = app(\App\Services\SensitiveDataService::class);
        $remoteId = $sensitiveData->canView()
            ? $conversation->remote_jid
            : $sensitiveData->maskPhone($conversation->remote_jid);
        $channelOk = $conversation->instance?->status === 'ready';
        $unreadFirst = $queueConversations->where('unread_count', '>', 0);
        $readRest = $queueConversations->where('unread_count', '<=', 0);
        $orderedQueue = $variant === 'B' ? $unreadFirst->concat($readRest) : $queueConversations;
        // A = 2 zonas (fila fina + chat) · B = 3 zonas densas (fila + chat + contexto)
        $gridCols = $variant === 'B' ? '290px minmax(0,1fr) 300px' : '250px minmax(0,1fr)';
    @endphp

    {{-- Barra flutuante do protótipo --}}
    <div style="position:fixed;bottom:16px;left:50%;transform:translateX(-50%);z-index:2000;display:flex;gap:8px;align-items:center;border:2px dashed #dc2626;border-radius:9999px;background:#fff;padding:8px 16px;font-size:12px;box-shadow:0 10px 25px rgba(0,0,0,.2)">
        <span style="font-weight:800;color:#dc2626">PROTOTIPO DESCARTAVEL</span>
        <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $queue, 'variant' => 'A']) }}" style="border-radius:9999px;padding:4px 12px;font-weight:700;{{ $variant === 'A' ? 'background:#0f766e;color:#fff' : 'background:#f3f4f6' }}">A WhatsApp</a>
        <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $queue, 'variant' => 'B']) }}" style="border-radius:9999px;padding:4px 12px;font-weight:700;{{ $variant === 'B' ? 'background:#0f766e;color:#fff' : 'background:#f3f4f6' }}">B central</a>
        <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $queue]) }}" style="border-radius:9999px;padding:4px 12px;background:#f3f4f6">Sair</a>
    </div>

    {{-- SHELL travado: ocupa a viewport, página não rola --}}
    <div class="proto-shell" style="position:sticky;top:64px;height:calc(100dvh - 80px);min-height:480px;display:grid;gap:12px;grid-template-columns:{{ $gridCols }};padding-bottom:64px">
        <style>
            @media (max-width: 1023px) {
                .proto-shell { display:flex !important; flex-direction:column !important; height:auto !important; position:static !important; }
                .proto-queue { max-height:220px !important; }
                .proto-chat { min-height:60vh !important; }
                .proto-context { display:none; }
                .proto-context-toggle { display:block !important; }
            }
            .proto-queue, .proto-timeline, .proto-context { scrollbar-width:thin; }
        </style>

        {{-- FILA lateral com scroll próprio --}}
        <section aria-label="Fila" class="rounded-xl border bg-white dark:bg-gray-900" style="display:flex;min-height:0;flex-direction:column;overflow:hidden;border-color:#e5e7eb">
            <div style="display:flex;gap:4px;padding:8px;border-bottom:1px solid #e5e7eb;font-size:12px;flex-shrink:0">
                @foreach (['mine' => 'Meus', 'unassigned' => 'Sem atendente'] as $key => $label)
                    <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $key, 'variant' => $variant]) }}"
                       style="border-radius:9999px;padding:6px 12px;font-weight:700;{{ $queue === $key ? 'background:#0f766e;color:#fff' : 'color:#4b5563' }}">{{ $label }}</a>
                @endforeach
            </div>
            <ul class="proto-queue" style="flex:1;min-height:0;overflow-y:auto;margin:0;padding:0;list-style:none">
                @forelse ($orderedQueue as $item)
                    <li>
                        <a href="{{ route('admin.topweb_chat.show', [$item, 'queue' => $queue, 'variant' => $variant]) }}"
                           style="display:block;padding:10px 12px;border-bottom:1px solid #f3f4f6;{{ $item->id === $conversation->id ? 'background:#f0fdfa' : '' }}">
                            <span style="display:flex;gap:8px;align-items:center">
                                <strong style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:14px">{{ $item->person?->name ?? 'Desconhecido' }}</strong>
                                @if ($item->unread_count)
                                    <span style="border-radius:9999px;background:#0f766e;color:#fff;font-size:11px;font-weight:800;padding:1px 8px">{{ $item->unread_count }}</span>
                                @endif
                            </span>
                            <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:#6b7280">{{ $item->lead?->title ?? $remoteId }}</span>
                            <span style="font-size:11px;color:#9ca3af">{{ $item->last_message_at?->diffForHumans() }} · {{ $item->assignedUser?->name ?? 'sem atendente' }}</span>
                        </a>
                    </li>
                @empty
                    <li style="padding:24px;text-align:center;font-size:14px;color:#6b7280">Nenhuma conversa nesta fila.</li>
                @endforelse
            </ul>
        </section>

        {{-- CONVERSA: header fixo + timeline com scroll + composer fixo --}}
        <section aria-label="Conversa" class="proto-chat rounded-xl border bg-white dark:bg-gray-900" style="display:flex;min-height:0;min-width:0;flex-direction:column;overflow:hidden;border-color:#e5e7eb">
            <header style="flex-shrink:0;border-bottom:1px solid #e5e7eb;padding:10px 16px">
                <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:{{ $channelOk ? '#059669' : '#d97706' }}">
                    {{ $channelOk ? 'Canal conectado' : 'Canal indisponível — histórico local legível, envio bloqueado' }}
                </p>
                <h1 style="margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:16px;font-weight:800">{{ $conversation->person?->name ?? 'Desconhecido' }}</h1>
                <p style="margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px;color:#6b7280">{{ $remoteId }} · {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
            </header>

            <div class="proto-timeline" style="flex:1;min-height:0;overflow-y:auto;background:#f8fafc;padding:16px;display:flex;flex-direction:column;gap:8px">
                @forelse ($conversation->messages as $message)
                    <div style="display:flex;{{ $message->direction === 'outgoing' ? 'justify-content:flex-end' : 'justify-content:flex-start' }}">
                        <div style="max-width:75%;border-radius:16px;padding:8px 14px;font-size:14px;box-shadow:0 1px 2px rgba(0,0,0,.08);{{ $message->direction === 'outgoing' ? 'background:#0f766e;color:#fff' : 'background:#fff;border:1px solid #e5e7eb' }}">
                            @if ($message->hasMedia())
                                @if ($canViewSensitiveMedia && $message->mediaIsStored())
                                    <p style="font-size:12px;opacity:.8">[mídia — abrir na tela real]</p>
                                @elseif ($canViewSensitiveMedia)
                                    <p style="font-size:12px;opacity:.8">Mídia processando…</p>
                                @else
                                    <p style="font-size:12px;opacity:.8">Mídia restrita à concessão (não é falha técnica)</p>
                                @endif
                            @endif
                            @if ($message->content)
                                <p style="margin:0;white-space:pre-wrap;overflow-wrap:anywhere">{{ $message->content }}</p>
                            @endif
                            <p style="margin:4px 0 0;font-size:11px;opacity:.7">{{ $message->sent_at?->format('d/m H:i') }} · {{ $message->status }}</p>
                        </div>
                    </div>
                @empty
                    <p style="padding:40px 0;text-align:center;font-size:14px;color:#6b7280">Nenhuma mensagem ainda. Envie a primeira abaixo.</p>
                @endforelse

                @foreach ($conversation->internalNotes ?? [] as $note)
                    <div style="margin:0 24px;border:1px solid #fcd34d;background:#fffbeb;border-radius:8px;padding:8px 12px;font-size:12px">
                        <p style="margin:0;font-weight:800;color:#92400e">Nota interna — nunca enviada ao cliente</p>
                        <p style="margin:4px 0">{{ $note->note }}</p>
                        <p style="margin:0;opacity:.7">{{ $note->user?->name }} · {{ $note->created_at?->format('d/m H:i') }}</p>
                    </div>
                @endforeach
            </div>

            @if (bouncer()->hasPermission('topweb_chat.inbox.send'))
                <form method="POST" action="{{ route('admin.topweb_chat.messages.store', $conversation) }}" style="flex-shrink:0;display:flex;align-items:flex-end;gap:8px;border-top:1px solid #e5e7eb;background:#fff;padding:12px">
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <details style="position:relative;flex-shrink:0">
                        <summary style="display:grid;place-items:center;width:44px;height:44px;border:1px solid #d1d5db;border-radius:9999px;cursor:pointer" aria-label="Anexar" title="Anexar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m21 12-8.5 8.5a5.5 5.5 0 0 1-7.78-7.78L12 5.4a3.67 3.67 0 0 1 5.2 5.2l-7.07 7.07a1.83 1.83 0 0 1-2.6-2.6L14.6 8"/></svg>
                        </summary>
                        <div style="position:absolute;bottom:48px;left:0;width:176px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;padding:4px;font-size:14px;box-shadow:0 10px 25px rgba(0,0,0,.15)">
                            <label style="display:block;border-radius:8px;padding:8px 12px;cursor:pointer">Imagem / vídeo<input type="file" name="media" style="display:none" accept="image/*,video/*"></label>
                            <label style="display:block;border-radius:8px;padding:8px 12px;cursor:pointer">Documento<input type="file" name="document" style="display:none" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx"></label>
                        </div>
                    </details>
                    <textarea name="content" rows="1" style="flex:1;min-height:44px;max-height:128px;resize:none;border:1px solid #d1d5db;border-radius:16px;padding:12px 16px;font-size:14px" placeholder="Responder…" @disabled(! $channelOk)></textarea>
                    <button class="primary-button" style="min-height:44px;flex-shrink:0;border-radius:9999px;padding-left:20px;padding-right:20px" @disabled(! $channelOk)>Enviar</button>
                </form>
            @endif
        </section>

        {{-- CONTEXTO: A = some no desktop (drawer no mobile) · B = terceira zona --}}
        @if ($variant === 'B')
            <aside aria-label="Contexto CRM" class="proto-context rounded-xl border bg-white dark:bg-gray-900" style="min-height:0;overflow-y:auto;border-color:#e5e7eb;padding:16px">
                <h2 style="margin:0;font-size:14px;font-weight:800">Contexto CRM</h2>
                <div style="margin-top:8px;display:grid;gap:6px;font-size:14px">
                    <p style="margin:0"><strong>Lead:</strong> {{ $conversation->lead?->title ?? 'sem vínculo' }}</p>
                    <p style="margin:0"><strong>Etapa:</strong> {{ $conversation->lead?->stage?->name ?? '—' }}</p>
                    <p style="margin:0"><strong>Responsável:</strong> {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
                </div>
                @if ($conversation->lead && bouncer()->hasPermission('topweb_chat.inbox.stage') && bouncer()->hasPermission('leads.edit'))
                    <form method="POST" action="{{ route('admin.topweb_chat.lead_stage.update', $conversation) }}" style="margin-top:12px;display:grid;gap:8px">
                        @csrf
                        @method('PUT')
                        <label for="proto-stage" style="font-size:12px;font-weight:700">Mudar etapa</label>
                        <select id="proto-stage" name="lead_pipeline_stage_id" class="custom-select" required>
                            @foreach ($pipelineStages as $stage)
                                <option value="{{ $stage->id }}" @selected($conversation->lead->lead_pipeline_stage_id === $stage->id)>{{ $stage->name }}</option>
                            @endforeach
                        </select>
                        <button class="secondary-button">Aplicar</button>
                    </form>
                @endif
            </aside>
        @endif
    </div>

    @if ($variant === 'A')
        <details class="proto-context-toggle" style="display:none;margin-top:12px;border:1px solid #e5e7eb;border-radius:12px;background:#fff;padding:12px 16px">
            <summary style="cursor:pointer;font-size:14px;font-weight:800">Contexto CRM — {{ $conversation->lead?->title ?? 'sem Lead vinculado' }}</summary>
            <p style="font-size:14px"><strong>Etapa:</strong> {{ $conversation->lead?->stage?->name ?? '—' }} · <strong>Responsável:</strong> {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
        </details>
    @endif
</x-admin::layouts>
