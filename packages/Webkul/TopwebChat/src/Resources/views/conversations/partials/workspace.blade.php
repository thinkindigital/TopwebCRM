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
        <aside
            id="topweb-chat-context"
            class="twp-context"
            data-workspace-region="context"
            @if ($selectedConversation)
                data-context-url="{{ route('admin.topweb_chat.context.show', $conversation) }}"
            @endif
        >
            @if ($selectedConversation)
                <button type="button" class="twp-context-close" data-context-close aria-label="@lang('topweb_chat::app.conversations.back')">×</button>
                <div data-context-content>
                    @include('topweb_chat::conversations.partials.crm-context', [
                    'conversation' => $conversation,
                    'pipelineStages' => $pipelineStages,
                    'leadPipelines' => $leadPipelines,
                    'assignableUsers' => $assignableUsers,
                        'nextAction' => $nextAction,
                        'recentActions' => $recentActions,
                        'isAdmin' => $isAdmin,
                        'canReleaseConversation' => $canReleaseConversation,
                        'canTransferLead' => $canTransferLead,
                    ])
                </div>
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
                refreshTimeline();
                await refreshContext();
            } catch (error) {
                button.removeAttribute('disabled');
                console.error('TopwebChat note delete failed.', error);
            }
        });

        const refreshTimeline = () => {
            document.dispatchEvent(new CustomEvent('topwebchat:refresh-timeline'));
        };

        const refreshContext = async () => {
            const container = document.getElementById('topweb-chat-context');
            const url = container?.dataset.contextUrl;
            if (!container || !url) return;

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error(`context_refresh_failed:${response.status}`);

                const content = container.querySelector('[data-context-content]');
                if (!content) throw new Error('context_content_missing');
                content.innerHTML = await response.text();
            } catch (error) {
                console.error('TopwebChat context refresh failed.', error);
            }
        };

        document.addEventListener('topwebchat:refresh-context', () => {
            refreshContext();
        });

        const submitInlineForm = async (form) => {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
            });

            if (!response.ok) {
                const error = new Error(`inline_submit_failed:${response.status}`);
                error.status = response.status;
                throw error;
            }

            form.querySelectorAll('textarea').forEach((field) => { field.value = ''; });
            refreshTimeline();
            await refreshContext();
        };

        document.addEventListener('submit', async (event) => {
            const inlineForm = event.target.closest('[data-note-form], [data-activity-form]');
            if (inlineForm) {
                event.preventDefault();

                try {
                    await submitInlineForm(inlineForm);
                } catch (error) {
                    console.error('TopwebChat inline submit failed.', error);
                }

                return;
            }
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-stage-form]');
            if (!form) return;

            event.preventDefault();

            const pipelineSelect = form.querySelector('[data-pipeline-select]');
            const stageSelect = form.querySelector('[data-stage-select]');
            const errorBox = form.querySelector('[data-stage-error]');
            const previous = {
                pipeline: form.dataset.confirmedPipeline,
                stage: form.dataset.confirmedStage,
            };

            const showError = () => {
                pipelineSelect.value = previous.pipeline;
                stageSelect.value = previous.stage;
                if (errorBox) errorBox.classList.remove('hidden');
            };

            if (errorBox) errorBox.classList.add('hidden');

            try {
                const response = await fetch(form.action, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]')?.value ?? '',
                    },
                    body: new FormData(form),
                });

                if (!response.ok) throw new Error(`stage_update_failed:${response.status}`);

                const body = await response.json();
                pipelineSelect.value = String(body.lead_pipeline_id);
                stageSelect.value = String(body.lead_pipeline_stage_id);
                form.dataset.confirmedPipeline = String(body.lead_pipeline_id);
                form.dataset.confirmedStage = String(body.lead_pipeline_stage_id);
                await refreshContext();
            } catch (error) {
                showError();
                console.error('TopwebChat stage update failed.', error);
            }
        });

        document.addEventListener('change', async (event) => {
            const pipelineSelect = event.target.closest('[data-pipeline-select]');
            if (!pipelineSelect) return;

            const form = pipelineSelect.closest('[data-stage-form]');
            const stageSelect = form?.querySelector('[data-stage-select]');
            const errorBox = form?.querySelector('[data-stage-error]');
            if (!form || !stageSelect) return;

            const previous = {
                pipeline: form.dataset.confirmedPipeline,
                stage: form.dataset.confirmedStage,
                stageOptions: stageSelect.innerHTML,
            };

            try {
                const url = form.dataset.stagesUrlTemplate.replace('__PIPELINE__', pipelineSelect.value);
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) throw new Error(`stages_load_failed:${response.status}`);

                const body = await response.json();
                stageSelect.replaceChildren(
                    ...(body.data ?? []).map((stage) => {
                        const option = document.createElement('option');
                        option.value = String(stage.id);
                        option.textContent = stage.name;
                        return option;
                    })
                );
                if (stageSelect.options.length > 0) {
                    stageSelect.value = stageSelect.options[0].value;
                }
                if (errorBox) errorBox.classList.add('hidden');
            } catch (error) {
                pipelineSelect.value = previous.pipeline;
                stageSelect.innerHTML = previous.stageOptions;
                stageSelect.value = previous.stage;
                if (errorBox) errorBox.classList.remove('hidden');
                console.error('TopwebChat stages load failed.', error);
            }
        });
    }
</script>
