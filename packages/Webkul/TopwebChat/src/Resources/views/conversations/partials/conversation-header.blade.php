{{-- E-02 C1: cabeçalho da conversa (identidade + estado do canal).
     Interface: $conversation, $remoteId, $historyUnavailable, $readUnavailable,
     $providerUnavailable. Sem mudança visual ou de comportamento. --}}
<header class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900 flex-shrink-0">
    <div class="flex min-w-0 items-center gap-3">
        <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brandColor text-lg font-bold text-white">
            {{ mb_strtoupper(mb_substr($conversation->person?->name ?? '?', 0, 1)) }}
        </div>

        <div class="min-w-0">
        <a href="{{ route('admin.topweb_chat.index') }}" class="text-xs font-medium text-brandColor hover:underline">
            @lang('topweb_chat::app.conversations.back')
        </a>

        <h1 class="truncate text-lg font-bold text-gray-800 dark:text-white">
            {{ $conversation->person?->name ?? trans('topweb_chat::app.contacts.unknown') }}
        </h1>

        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $remoteId }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
        <span
            id="topweb-chat-sync-status"
            class="rounded-full bg-gray-100 px-3 py-1.5 font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
        >
            @lang('topweb_chat::app.messages.sync_connecting')
        </span>
        <span class="rounded-full bg-gray-100 px-3 py-1.5 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
            {{ $conversation->assignedUser?->name ?? trans('topweb_chat::app.conversations.unassigned') }}
        </span>
        <span
            id="topweb-chat-connection-badge"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 font-medium {{ $conversation->instance?->status === 'ready' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}"
        >
            <span class="h-2 w-2 rounded-full bg-current"></span>
            <span id="topweb-chat-instance-status">{{ $conversation->instance?->status ?? 'unknown' }}</span>
        </span>
    </div>
</header>

<div
    id="topweb-chat-connection-warning"
    class="{{ $conversation->instance?->status === 'ready' ? 'hidden' : '' }} border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200 flex-shrink-0"
>
    @lang('topweb_chat::app.messages.instance_not_connected')
</div>

@if ($historyUnavailable || $readUnavailable || $providerUnavailable)
    <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200 flex-shrink-0">
        @lang('topweb_chat::app.messages.integration_unavailable')
    </div>
@endif
