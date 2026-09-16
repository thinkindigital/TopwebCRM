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
    if (!document.documentElement.dataset.topwebchatWorkspaceBound) {
        document.documentElement.dataset.topwebchatWorkspaceBound = 'true';

        const setContext = (root, visible) => {
            root.classList.toggle('is-context-open', visible);
            root.querySelector('[data-context-open]')?.setAttribute('aria-expanded', visible ? 'true' : 'false');
        };

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-context-open], [data-context-close]');
            const root = trigger?.closest('[data-topwebchat-workspace]')
                ?? document.querySelector('[data-topwebchat-workspace]');

            if (!trigger || !root) return;

            setContext(root, trigger.hasAttribute('data-context-open'));
        });

        document.addEventListener('keydown', (event) => {
            const root = document.querySelector('[data-topwebchat-workspace]');
            if (event.key === 'Escape' && root) setContext(root, false);
        });

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-note-delete]');
            if (!button) return;

            if (!window.confirm(button.dataset.confirmMessage || '')) return;

            button.setAttribute('disabled', 'true');

            try {
                const token = document.querySelector('[data-topwebchat-workspace] input[name="_token"]')?.value ?? '';
                const response = await fetch(button.dataset.deleteUrl, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });

                if (!response.ok) throw new Error(`note_delete_failed:${response.status}`);

                document.querySelectorAll(`[data-note-id="${button.dataset.noteId}"]`)
                    .forEach((element) => element.remove());
            } catch (error) {
                button.removeAttribute('disabled');
                console.error('TopwebChat note delete failed.', error);
            }
        });
    }
</script>
