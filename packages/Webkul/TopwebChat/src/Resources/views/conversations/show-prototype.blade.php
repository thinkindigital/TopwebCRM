{{-- PROTOTYPE-UX S02r2 — DESCARTÁVEL. APAGAR após o veredito. Mesma rota/auth/dados.
     Corrige: shell com altura travada (scroll SÓ na timeline/fila), composer fixo,
     fila lateral persistente no desktop, A/B estruturalmente distintos. --}}
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
    @endphp

    {{-- Barra flutuante do protótipo --}}
    <div class="fixed bottom-4 left-1/2 z-[2000] flex -translate-x-1/2 items-center gap-2 rounded-full border-2 border-dashed border-red-500 bg-white px-4 py-2 text-xs shadow-xl dark:bg-gray-900">
        <span class="font-bold text-red-600">PROTOTIPO DESCARTAVEL</span>
        <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $queue, 'variant' => 'A']) }}" class="rounded-full px-3 py-1 font-semibold {{ $variant === 'A' ? 'bg-brandColor text-white' : 'bg-gray-100 dark:bg-gray-800' }}">A WhatsApp</a>
        <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $queue, 'variant' => 'B']) }}" class="rounded-full px-3 py-1 font-semibold {{ $variant === 'B' ? 'bg-brandColor text-white' : 'bg-gray-100 dark:bg-gray-800' }}">B central</a>
        <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $queue]) }}" class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Sair</a>
    </div>

    {{-- SHELL: altura travada na viewport, página não rola; só fila/timeline têm scroll --}}
    <div class="sticky top-[64px] flex h-[calc(100dvh-80px)] min-h-[480px] gap-3 pb-16 {{ $variant === 'B' ? 'lg:grid lg:grid-cols-[290px_minmax(0,1fr)_300px]' : '' }} {{ $variant === 'A' ? 'mx-auto w-full max-w-5xl lg:grid lg:grid-cols-[250px_minmax(0,1fr)]' : '' }}">

        {{-- FILA lateral: A = colapsável · B = sempre visível e priorizada --}}
        @if ($variant === 'A')
            <details class="lg:hidden">
                <summary class="cursor-pointer rounded-xl border bg-white px-4 py-3 text-sm font-bold dark:bg-gray-900">Fila ({{ $queueConversations->count() }}) — tocar para abrir</summary>
            </details>
        @endif
        <section aria-label="Fila" class="{{ $variant === 'A' ? 'hidden lg:flex' : 'flex' }} min-h-0 w-full flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 lg:w-auto">
            <div class="flex shrink-0 gap-1 border-b border-gray-200 p-2 text-xs dark:border-gray-800">
                @foreach (['mine' => 'Meus', 'unassigned' => 'Sem atendente'] as $key => $label)
                    <a href="{{ route('admin.topweb_chat.show', [$conversation, 'queue' => $key, 'variant' => $variant]) }}"
                       class="rounded-full px-3 py-1.5 font-semibold {{ $queue === $key ? 'bg-brandColor text-white' : 'text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                @endforeach
            </div>
            <ul class="min-h-0 flex-1 divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800">
                @forelse ($orderedQueue as $item)
                    <li>
                        <a href="{{ route('admin.topweb_chat.show', [$item, 'queue' => $queue, 'variant' => $variant]) }}"
                           class="block px-3 py-2.5 {{ $item->id === $conversation->id ? 'bg-teal-50 dark:bg-teal-950' : 'hover:bg-gray-50 dark:hover:bg-gray-950' }}">
                            <span class="flex items-center gap-2">
                                <strong class="truncate text-sm">{{ $item->person?->name ?? 'Desconhecido' }}</strong>
                                @if ($item->unread_count)
                                    <span class="rounded-full bg-brandColor px-2 py-0.5 text-[11px] font-bold text-white">{{ $item->unread_count }}</span>
                                @endif
                            </span>
                            <span class="block truncate text-xs text-gray-500">{{ $item->lead?->title ?? $remoteId }}</span>
                            <span class="text-[11px] text-gray-400">{{ $item->last_message_at?->diffForHumans() }} · {{ $item->assignedUser?->name ?? 'sem atendente' }}</span>
                        </a>
                    </li>
                @empty
                    <li class="p-6 text-center text-sm text-gray-500">Nenhuma conversa nesta fila.</li>
                @endforelse
            </ul>
        </section>

        {{-- CONVERSA: header fixo + timeline com scroll + composer fixo --}}
        <section aria-label="Conversa" class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <header class="shrink-0 border-b border-gray-200 px-4 py-2.5 dark:border-gray-800">
                <p class="text-[11px] font-semibold uppercase tracking-wide {{ $channelOk ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ $channelOk ? 'Canal conectado' : 'Canal indisponível — histórico local legível, envio bloqueado' }}
                </p>
                <h1 class="truncate text-base font-bold">{{ $conversation->person?->name ?? 'Desconhecido' }}</h1>
                <p class="truncate text-xs text-gray-500">{{ $remoteId }} · {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
            </header>

            <div class="min-h-0 flex-1 space-y-2 overflow-y-auto bg-slate-50 p-4 dark:bg-gray-950">
                @forelse ($conversation->messages as $message)
                    <div class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm {{ $message->direction === 'outgoing' ? 'rounded-br-md bg-brandColor text-white' : 'rounded-bl-md border bg-white dark:bg-gray-900' }}">
                            @if ($message->hasMedia())
                                @if ($canViewSensitiveMedia && $message->mediaIsStored())
                                    <p class="text-xs opacity-80">[mídia — abrir na tela real]</p>
                                @elseif ($canViewSensitiveMedia)
                                    <p class="text-xs opacity-80">Mídia processando…</p>
                                @else
                                    <p class="text-xs opacity-80">Mídia restrita à concessão (não é falha técnica)</p>
                                @endif
                            @endif
                            @if ($message->content)
                                <p class="whitespace-pre-wrap break-words">{{ $message->content }}</p>
                            @endif
                            <p class="mt-1 text-[11px] opacity-70">{{ $message->sent_at?->format('d/m H:i') }} · {{ $message->status }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-gray-500">Nenhuma mensagem ainda. Envie a primeira abaixo.</p>
                @endforelse

                @foreach ($conversation->internalNotes ?? [] as $note)
                    <div class="mx-6 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs dark:bg-amber-950">
                        <p class="font-bold text-amber-800 dark:text-amber-200">Nota interna — nunca enviada ao cliente</p>
                        <p>{{ $note->note }}</p>
                        <p class="opacity-70">{{ $note->user?->name }} · {{ $note->created_at?->format('d/m H:i') }}</p>
                    </div>
                @endforeach
            </div>

            @if (bouncer()->hasPermission('topweb_chat.inbox.send'))
                <form method="POST" action="{{ route('admin.topweb_chat.messages.store', $conversation) }}" class="flex shrink-0 items-end gap-2 border-t border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <details class="relative shrink-0">
                        <summary class="grid h-11 w-11 cursor-pointer list-none place-items-center rounded-full border text-lg" aria-label="Anexar" title="Anexar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m21 12-8.5 8.5a5.5 5.5 0 0 1-7.78-7.78L12 5.4a3.67 3.67 0 0 1 5.2 5.2l-7.07 7.07a1.83 1.83 0 0 1-2.6-2.6L14.6 8"/></svg>
                        </summary>
                        <div class="absolute bottom-12 left-0 w-44 rounded-xl border bg-white p-1 text-sm shadow-xl dark:bg-gray-900">
                            <label class="block cursor-pointer rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800">Imagem / vídeo<input type="file" name="media" class="hidden" accept="image/*,video/*"></label>
                            <label class="block cursor-pointer rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800">Documento<input type="file" name="document" class="hidden" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx"></label>
                        </div>
                    </details>
                    <textarea name="content" rows="1" class="max-h-32 min-h-11 flex-1 resize-none rounded-2xl border px-4 py-3 text-sm" placeholder="Responder… (Enter envia no app real; aqui use o botão)" @disabled(! $channelOk)></textarea>
                    <button class="primary-button min-h-11 shrink-0 rounded-full px-5" @disabled(! $channelOk)>Enviar</button>
                </form>
            @endif
        </section>

        {{-- CONTEXTO: A = drawer · B = terceira zona persistente --}}
        @if ($variant === 'A')
            <details class="lg:hidden">
                <summary class="cursor-pointer rounded-xl border bg-white px-4 py-3 text-sm font-bold dark:bg-gray-900">Contexto CRM</summary>
            </details>
        @endif
        <aside aria-label="Contexto CRM" class="{{ $variant === 'A' ? 'hidden lg:block lg:w-64' : '' }} min-h-0 overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-sm font-bold">Contexto CRM</h2>
            <div class="mt-2 grid gap-1.5 text-sm">
                <p><strong>Lead:</strong> {{ $conversation->lead?->title ?? 'sem vínculo' }}</p>
                <p><strong>Etapa:</strong> {{ $conversation->lead?->stage?->name ?? '—' }}</p>
                <p><strong>Responsável:</strong> {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
            </div>
            @if ($conversation->lead && bouncer()->hasPermission('topweb_chat.inbox.stage') && bouncer()->hasPermission('leads.edit'))
                <form method="POST" action="{{ route('admin.topweb_chat.lead_stage.update', $conversation) }}" class="mt-3 grid gap-2">
                    @csrf
                    @method('PUT')
                    <label for="proto-stage" class="text-xs font-semibold">Mudar etapa</label>
                    <select id="proto-stage" name="lead_pipeline_stage_id" class="custom-select" required>
                        @foreach ($pipelineStages as $stage)
                            <option value="{{ $stage->id }}" @selected($conversation->lead->lead_pipeline_stage_id === $stage->id)>{{ $stage->name }}</option>
                        @endforeach
                    </select>
                    <button class="secondary-button">Aplicar</button>
                </form>
            @endif
        </aside>
    </div>
</x-admin::layouts>
