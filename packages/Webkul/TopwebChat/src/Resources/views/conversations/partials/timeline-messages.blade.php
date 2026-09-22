{{-- E-03: representação canônica da timeline (única fonte visual).
     Interface: $conversation (com messages ordenadas), $canViewSensitiveMedia,
     $canViewNotes. JS não reconstrói markup.
     S2: eventos unificados (message + internal note) ordenados por occurred_at. --}}
@php
    $previousDate = null;
    $today = \Illuminate\Support\Carbon::today();
    $yesterday = \Illuminate\Support\Carbon::yesterday();
    $showNotes = $canViewNotes ?? false;
    $timelineEvents = $conversation->messages
        ->map(fn ($message) => [
            'occurred_at' => $message->sent_at ?? $message->created_at,
            'kind' => 'message',
            'model' => $message,
        ])
        ->concat(
            $showNotes
                ? $conversation->internalNotes->map(fn ($note) => [
                    'occurred_at' => $note->created_at,
                    'kind' => 'note',
                    'model' => $note,
                ])
                : collect()
        )
        ->sortBy(fn ($event) => $event['occurred_at']);
@endphp
@forelse ($timelineEvents as $event)
    @php
        $eventAt = $event['occurred_at'];
        $eventDate = $eventAt?->toDateString();
    @endphp
    @if ($eventDate && $eventDate !== $previousDate)
        @php($previousDate = $eventDate)
        <div class="my-2 flex justify-center topweb-chat-date-separator">
            <span class="rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-gray-500 shadow-sm dark:bg-gray-900 dark:text-gray-300">{{ $eventDate === $today->toDateString() ? 'Hoje' : ($eventDate === $yesterday->toDateString() ? 'Ontem' : $eventAt->format('d/m/Y')) }}</span>
        </div>
    @endif
    @if ($event['kind'] === 'note')
        @php($note = $event['model'])
        <div class="topweb-chat-internal-note mx-6 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100" data-note-id="{{ $note->id }}">
            <p class="font-bold">Nota interna — visível só para a equipe</p>
            <p class="whitespace-pre-wrap break-words">{{ $note->content }}</p>
            <p class="opacity-70">{{ $note->user?->name }} · {{ $note->created_at?->format('d/m/Y H:i') }}</p>
        </div>
    @else
    @php($message = $event['model'])
    <article
        class="twp-message {{ $message->direction === 'outgoing' ? 'twp-message-outgoing flex justify-end' : 'flex justify-start' }}"
        data-message-id="{{ $message->id }}"
    >
        <div class="twp-message-bubble max-w-[85%] rounded-2xl border px-4 py-2.5 shadow-sm sm:max-w-[72%] {{ $message->direction === 'outgoing' ? 'rounded-br-md' : 'rounded-bl-md' }}">
            @if ($message->hasMedia())
                @php($mediaMime = (string) data_get($message->metadata, 'media_mime'))

                {{-- D2: ID do arquivo visível a ambos os perfis; nome/bytes só com concessão. --}}
                <p class="mb-1 text-xs opacity-60">@lang('topweb_chat::app.messages.file_id_label', ['id' => $message->id])</p>

                @if ($canViewSensitiveMedia && $message->mediaIsStored())
                    @if (data_get($message->metadata, 'media_original_name'))
                        <p class="mb-1 truncate text-sm font-medium">{{ data_get($message->metadata, 'media_original_name') }}</p>
                    @endif

                    @if (str_starts_with($mediaMime, 'image/'))
                        <a href="{{ route('admin.topweb_chat.messages.media', [$conversation, $message]) }}" target="_blank" rel="noopener">
                            <img
                                src="{{ route('admin.topweb_chat.messages.media', [$conversation, $message]) }}"
                                alt="@lang('topweb_chat::app.messages.media_image')"
                                class="mb-2 max-h-80 w-auto max-w-full rounded-xl object-contain"
                                loading="lazy"
                            >
                        </a>
                    @elseif (str_starts_with($mediaMime, 'audio/'))
                        <audio class="mb-2 max-w-full" controls preload="metadata" src="{{ route('admin.topweb_chat.messages.media', [$conversation, $message]) }}"></audio>
                    @elseif (str_starts_with($mediaMime, 'video/'))
                        <video class="mb-2 max-h-80 max-w-full rounded-xl" controls preload="metadata" src="{{ route('admin.topweb_chat.messages.media', [$conversation, $message]) }}"></video>
                    @else
                        <a class="mb-2 flex items-center gap-2 rounded-xl bg-black/10 px-3 py-2 font-medium hover:underline" href="{{ route('admin.topweb_chat.messages.media', [$conversation, $message]) }}" target="_blank" rel="noopener">
                            @lang('topweb_chat::app.messages.open_media')
                        </a>
                    @endif
                @elseif ($canViewSensitiveMedia)
                    <p class="mb-1 text-sm opacity-80">@lang('topweb_chat::app.messages.media_processing')</p>
                @else
                    <p class="mb-1 text-sm opacity-80">@lang('topweb_chat::app.messages.media_restricted')</p>
                @endif
            @endif

            @if ($message->content)
                <p class="whitespace-pre-wrap break-words">{{ $message->content }}</p>
            @elseif (! $message->hasMedia())
                <p class="whitespace-pre-wrap break-words">@lang('topweb_chat::app.messages.unsupported')</p>
            @endif
            <div class="mt-2 flex items-center gap-2 text-xs opacity-75">
                <span>{{ $message->sent_at?->format('d/m/Y H:i') ?? $message->created_at?->format('d/m/Y H:i') }}</span>
                <span>{{ $message->status }}</span>
                @if ($message->status === 'unknown')
                    <span class="topweb-chat-status-unknown" title="@lang('topweb_chat::app.messages.status_unknown_hint')">@lang('topweb_chat::app.messages.status_unknown_hint')</span>
                @endif
                @if ($message->status === 'failed' && $message->last_error)
                    @php($errorCodeKey = 'topweb_chat::app.messages.error_'.$message->last_error)
                    <span class="text-red-600 dark:text-red-400" title="{{ $message->last_error }}">
                        {{ \Illuminate\Support\Facades\Lang::has($errorCodeKey) ? trans($errorCodeKey) : $message->last_error }}
                    </span>
                @endif
                @if (app(\Webkul\TopwebChat\Services\MessageService::class)->canRetry($message))
                    <button
                        type="button"
                        class="underline"
                        data-requires-channel
                        data-retry-url="{{ route('admin.topweb_chat.messages.retry', [$conversation, $message]) }}"
                    >
                        @lang('topweb_chat::app.messages.retry')
                    </button>
                @endif
            </div>
        </div>
    </article>
    @endif
@empty
    <p class="py-10 text-center text-gray-600 dark:text-gray-300">@lang('topweb_chat::app.messages.empty')</p>
@endforelse
<div id="topweb-chat-anchor" style="height: 1px;" aria-hidden="true"></div>
<div id="topweb-chat-poll-meta" class="hidden" data-instance-status="{{ $conversation->instance?->status ?? 'unknown' }}" data-last-id="{{ $conversation->messages->last()?->id ?? 0 }}"></div>
