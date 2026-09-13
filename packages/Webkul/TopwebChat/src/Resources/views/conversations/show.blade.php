<x-admin::layouts>
    <x-slot:title>
        {{ $conversation->person?->name ?? trans('topweb_chat::app.menu.title') }}
    </x-slot>

    @php
        $user = auth()->guard('user')->user();
        $isAdmin = $user->role?->permission_type === 'all';
        $canReleaseConversation = $conversation->assigned_user_id
            && ($isAdmin || $conversation->assigned_user_id === $user->id);
        $sensitiveData = app(\App\Services\SensitiveDataService::class);
        $remoteId = $sensitiveData->canView()
            ? $conversation->remote_jid
            : $sensitiveData->maskPhone($conversation->remote_jid);
    @endphp

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section
            class="relative flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            style="height: clamp(30rem, calc(100dvh - 10rem), 48rem); min-height: 0; display: flex; flex-direction: column;"
        >
            @include('topweb_chat::conversations.partials.conversation-header', [
                'conversation' => $conversation,
                'remoteId' => $remoteId,
                'historyUnavailable' => $historyUnavailable,
                'readUnavailable' => $readUnavailable,
                'providerUnavailable' => $providerUnavailable,
            ])

            <div
                id="topweb-chat-timeline"
                class="flex min-h-0 flex-1 flex-col justify-start gap-2.5 overflow-y-auto bg-slate-50 p-4 sm:p-5 dark:bg-gray-950"
                style="min-height: 0; flex: 1 1 auto; overflow-y: auto;"
                data-messages-url="{{ route('admin.topweb_chat.messages.index', $conversation) }}"
                aria-live="polite"
            >
                @include('topweb_chat::conversations.partials.timeline-messages', [
                    'conversation' => $conversation,
                    'canViewSensitiveMedia' => $canViewSensitiveMedia,
                    'canViewNotes' => $canViewNotes ?? false,
                ])
            </div>

            <button
                id="topweb-chat-new-messages"
                type="button"
                class="secondary-button absolute bottom-28 left-1/2 hidden -translate-x-1/2 shadow-lg"
            >
                @lang('topweb_chat::app.messages.new_messages') ↓
            </button>

            @include('topweb_chat::conversations.partials.composer', [
                'conversation' => $conversation,
                'canAttachSensitive' => $sensitiveData->canView(),
            ])

            @include('topweb_chat::conversations.partials.crm-context', [
                'conversation' => $conversation,
                'pipelineStages' => $pipelineStages,
                'assignableUsers' => $assignableUsers,
                'nextAction' => $nextAction,
                'recentActions' => $recentActions,
                'isAdmin' => $isAdmin,
                'canReleaseConversation' => $canReleaseConversation,
            ])
    </div>

    @include('topweb_chat::conversations.partials.chat-runtime', [
                'conversation' => $conversation,
            ])
</x-admin::layouts>
