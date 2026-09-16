@include('topweb_chat::conversations.partials.workspace-styles')

<div
    class="twp-root {{ $workspaceStyle === 'R1K' ? 'is-r1k' : 'is-r1' }} {{ $selectedConversation ? 'has-conversation' : '' }}"
    data-topwebchat-workspace
    data-workspace-style="{{ $workspaceStyle }}"
>
    <div class="twp-shell">
        <aside class="twp-queue" data-workspace-region="queue">
            @include('topweb_chat::conversations.partials.workspace-queue')
        </aside>

        <main class="twp-conversation" data-workspace-region="conversation">
            @if ($selectedConversation)
                @include('topweb_chat::conversations.partials.conversation-header', [
                    'conversation' => $conversation,
                    'remoteId' => $remoteId,
                    'historyUnavailable' => $historyUnavailable,
                    'readUnavailable' => $readUnavailable,
                    'providerUnavailable' => $providerUnavailable,
                ])

                <div
                    id="topweb-chat-timeline"
                    class="flex min-h-0 flex-1 flex-col justify-start gap-2.5 overflow-y-auto p-4 sm:p-5"
                    data-messages-url="{{ route('admin.topweb_chat.messages.index', $conversation) }}"
                    aria-live="polite"
                >
                    @include('topweb_chat::conversations.partials.timeline-messages', [
                        'conversation' => $conversation,
                        'canViewSensitiveMedia' => $canViewSensitiveMedia,
                        'canViewNotes' => $canViewNotes ?? false,
                    ])
                </div>

                <button id="topweb-chat-new-messages" type="button" class="secondary-button absolute bottom-28 left-1/2 hidden -translate-x-1/2 shadow-lg">
                    @lang('topweb_chat::app.messages.new_messages') ↓
                </button>

                @include('topweb_chat::conversations.partials.composer', [
                    'conversation' => $conversation,
                    'canAttachSensitive' => $sensitiveData->canView(),
                ])
            @else
                <div class="twp-empty-workspace">@lang('topweb_chat::app.conversations.empty')</div>
            @endif
        </main>

        <button type="button" class="twp-scrim" data-context-close tabindex="-1" aria-label="@lang('topweb_chat::app.conversations.back')"></button>
        <aside id="topweb-chat-context" class="twp-context" data-workspace-region="context">
            @if ($selectedConversation)
                <button type="button" class="twp-context-close" data-context-close aria-label="@lang('topweb_chat::app.conversations.back')">×</button>
                @include('topweb_chat::conversations.partials.crm-context', [
                    'conversation' => $conversation,
                    'pipelineStages' => $pipelineStages,
                    'assignableUsers' => $assignableUsers,
                    'nextAction' => $nextAction,
                    'recentActions' => $recentActions,
                    'isAdmin' => $isAdmin,
                    'canReleaseConversation' => $canReleaseConversation,
                ])
            @endif
        </aside>
    </div>
</div>

<script src="{{ asset('js/topwebchat-search.js') }}" defer></script>
<script>
    window.addEventListener('load', () => {
        const root = document.querySelector('[data-topwebchat-workspace]');
        if (!root) return;
        const open = root.querySelector('[data-context-open]');
        const close = root.querySelectorAll('[data-context-close]');
        const setContext = (visible) => {
            root.classList.toggle('is-context-open', visible);
            open?.setAttribute('aria-expanded', visible ? 'true' : 'false');
        };
        open?.addEventListener('click', () => setContext(true));
        close.forEach((element) => element.addEventListener('click', () => setContext(false)));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') setContext(false);
        });
    }, { once: true });
</script>
