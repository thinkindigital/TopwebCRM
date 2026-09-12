{{-- PROTOTYPE-UX S02 — DESCARTÁVEL. Não refatorar, não testar, APAGAR após o veredito.
     Mesma rota/auth/dados do show real. Sem polling JS (recarregue para atualizar).
     Sem SLA falso: ordenação usa só não-lidas + last_message_at. --}}
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
        $baseParams = ['queue' => $queue];
        $channelOk = $conversation->instance?->status === 'ready';
    @endphp

    {{-- Barra flutuante do protótipo --}}
    <div class="fixed bottom-4 left-1/2 z-[2000] flex -translate-x-1/2 items-center gap-2 rounded-full border-2 border-dashed border-red-500 bg-white px-4 py-2 text-xs shadow-xl dark:bg-gray-900">
        <span class="font-bold text-red-600">PROTOTIPO DESCARTAVEL</span>
        <a href="{{ route('admin.topweb_chat.show', array_merge([$conversation], $baseParams, ['variant' => 'A'])) }}" class="rounded-full px-3 py-1 font-semibold {{ $variant === 'A' ? 'bg-brandColor text-white' : 'bg-gray-100 dark:bg-gray-800' }}">A familiar</a>
        <a href="{{ route('admin.topweb_chat.show', array_merge([$conversation], $baseParams, ['variant' => 'B'])) }}" class="rounded-full px-3 py-1 font-semibold {{ $variant === 'B' ? 'bg-brandColor text-white' : 'bg-gray-100 dark:bg-gray-800' }}">B operacional</a>
        <a href="{{ route('admin.topweb_chat.show', array_merge([$conversation], $baseParams)) }}" class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">Sair</a>
    </div>

    <div class="flex flex-col gap-3 pb-20">
        {{-- Fila: A = lista simples · B = fila de trabalho priorizada (só dados existentes) --}}
        <div class="grid gap-3 {{ $variant === 'B' ? 'lg:grid-cols-[300px_minmax(0,1fr)]' : 'lg:grid-cols-[260px_minmax(0,1fr)]' }}">
            <section aria-label="Fila" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex gap-1 border-b border-gray-200 p-2 text-xs dark:border-gray-800">
                    @foreach (['mine' => 'Meus', 'unassigned' => 'Sem atendente'] as $key => $label)
                        <a href="{{ route('admin.topweb_chat.show', array_merge([$conversation], ['queue' => $key, 'variant' => $variant])) }}"
                           class="rounded-full px-3 py-1.5 font-semibold {{ $queue === $key ? 'bg-brandColor text-white' : 'text-gray-600 dark:text-gray-300' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <ul class="max-h-[38vh] divide-y divide-gray-100 overflow-y-auto dark:divide-gray-800 lg:max-h-[70vh]">
                    @forelse ($queueConversations->sortByDesc(fn ($c) => [$c->unread_count > 0, $c->last_message_at]) as $item)
                        <li>
                            <a href="{{ route('admin.topweb_chat.show', array_merge([$item], ['queue' => $queue, 'variant' => $variant])) }}"
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

            {{-- Conversa --}}
            <section aria-label="Conversa" class="flex min-h-[60vh] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <header class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                        @if ($channelOk)
                            Canal conectado
                        @else
                            Canal temporariamente indisponível — o histórico local continua legível
                        @endif
                    </p>
                    <h1 class="truncate text-base font-bold">{{ $conversation->person?->name ?? 'Desconhecido' }}</h1>
                    <p class="text-xs text-gray-500">{{ $remoteId }} · {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
                </header>

                <div class="flex-1 space-y-2 overflow-y-auto bg-slate-50 p-4 dark:bg-gray-950">
                    @forelse ($conversation->messages as $message)
                        <div class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[75%] rounded-2xl px-3.5 py-2 text-sm shadow-sm {{ $message->direction === 'outgoing' ? 'rounded-br-md bg-brandColor text-white' : 'rounded-bl-md border bg-white dark:bg-gray-900' }}">
                                @if ($message->hasMedia())
                                    @if ($canViewSensitiveMedia && $message->mediaIsStored())
                                        <p class="text-xs opacity-80">[mídia — abrir na tela real]</p>
                                    @elseif ($canViewSensitiveMedia)
                                        <p class="text-xs opacity-80">Mídia processando…</p>
                                    @else
                                        <p class="text-xs opacity-80">🔒 Mídia restrita à concessão (não é falha técnica)</p>
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
                    <form method="POST" action="{{ route('admin.topweb_chat.messages.store', $conversation) }}" class="flex shrink-0 items-end gap-2 border-t border-gray-200 p-3 dark:border-gray-800">
                        @csrf
                        <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <details class="relative">
                            <summary class="grid h-11 w-11 cursor-pointer list-none place-items-center rounded-full border text-lg" aria-label="Anexar" title="Anexar">📎</summary>
                            <div class="absolute bottom-12 left-0 w-44 rounded-xl border bg-white p-1 text-sm shadow-xl dark:bg-gray-900">
                                <label class="block cursor-pointer rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800">Imagem / vídeo<input type="file" name="media" class="hidden" accept="image/*,video/*"></label>
                                <label class="block cursor-pointer rounded-lg px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800">Documento<input type="file" name="document" class="hidden" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx"></label>
                            </div>
                        </details>
                        <textarea name="content" rows="1" class="max-h-32 min-h-11 flex-1 resize-none rounded-2xl border px-4 py-3 text-sm" placeholder="Responder…" @disabled(! $channelOk)></textarea>
                        <button class="primary-button min-h-11 rounded-full px-5" @disabled(! $channelOk)>Enviar</button>
                    </form>
                @endif
            </section>
        </div>

        {{-- Contexto CRM: A = drawer (details) · B = persistente recolhível --}}
        <details class="rounded-xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" @if ($variant === 'B') open @endif>
            <summary class="cursor-pointer px-4 py-3 text-sm font-bold">Contexto CRM — {{ $conversation->lead?->title ?? 'sem Lead vinculado' }}</summary>
            <div class="grid gap-2 border-t border-gray-100 px-4 py-3 text-sm dark:border-gray-800 sm:grid-cols-2">
                <p><strong>Pessoa:</strong> {{ $conversation->person?->name ?? 'não vinculada' }}</p>
                <p><strong>Etapa:</strong> {{ $conversation->lead?->stage?->name ?? '—' }}</p>
                <p><strong>Responsável:</strong> {{ $conversation->assignedUser?->name ?? 'sem atendente' }}</p>
                <p><strong>Instância:</strong> {{ $conversation->instance?->name }} (técnico)</p>
                @if ($conversation->lead && bouncer()->hasPermission('topweb_chat.inbox.stage') && bouncer()->hasPermission('leads.edit'))
                    <form method="POST" action="{{ route('admin.topweb_chat.lead_stage.update', $conversation) }}" class="flex items-center gap-2 sm:col-span-2">
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
            </div>
        </details>
    </div>
</x-admin::layouts>
