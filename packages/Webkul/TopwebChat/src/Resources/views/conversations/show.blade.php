<x-admin::layouts>
    <x-slot:title>
        {{ $conversation->person?->name ?? trans('topweb_chat::app.menu.title') }}
    </x-slot>

    @php
        $user = auth()->guard('user')->user();
        $isAdmin = $user->role?->permission_type === 'all';
        $sensitiveData = app(\App\Services\SensitiveDataService::class);
        $remoteId = $sensitiveData->canView()
            ? $conversation->remote_jid
            : $sensitiveData->maskPhone($conversation->remote_jid);
    @endphp

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section
            class="flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            style="height: clamp(30rem, calc(100dvh - 10rem), 48rem); min-height: 0;"
        >
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-800">
                <div>
                    <a href="{{ route('admin.topweb_chat.index') }}" class="text-sm text-brandColor">
                        @lang('topweb_chat::app.conversations.back')
                    </a>

                    <h1 class="mt-1 text-xl font-bold text-gray-800 dark:text-white">
                        {{ $conversation->person?->name ?? trans('topweb_chat::app.contacts.unknown') }}
                    </h1>

                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $remoteId }}</p>
                </div>

                <div class="text-sm text-gray-600 dark:text-gray-300">
                    <p>@lang('topweb_chat::app.conversations.assignee'): {{ $conversation->assignedUser?->name ?? trans('topweb_chat::app.conversations.unassigned') }}</p>
                    <p>@lang('topweb_chat::app.conversations.status'): {{ $conversation->status }}</p>
                    <p>
                        @lang('topweb_chat::app.conversations.connection'):
                        <span id="topweb-chat-instance-status">{{ $conversation->instance?->status ?? 'unknown' }}</span>
                    </p>
                </div>
            </header>

            <div
                id="topweb-chat-connection-warning"
                class="{{ $conversation->instance?->status === 'ready' ? 'hidden' : '' }} border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                @lang('topweb_chat::app.messages.instance_not_connected')
            </div>

            @if ($historyUnavailable || $readUnavailable || $providerUnavailable)
                <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                    @lang('topweb_chat::app.messages.integration_unavailable')
                </div>
            @endif

            <div
                id="topweb-chat-timeline"
                class="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto bg-gray-50 p-4 dark:bg-gray-950"
                style="min-height: 0; flex: 1 1 auto; overflow-y: auto;"
                data-messages-url="{{ route('admin.topweb_chat.messages.index', $conversation) }}"
                aria-live="polite"
            >
                @forelse ($conversation->messages as $message)
                    <article
                        class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}"
                        data-message-id="{{ $message->id }}"
                    >
                        <div class="max-w-[78%] rounded-lg px-4 py-3 {{ $message->direction === 'outgoing' ? 'bg-brandColor text-white' : 'bg-white text-gray-800 dark:bg-gray-900 dark:text-white' }}">
                            <p class="whitespace-pre-wrap break-words">{{ $message->content ?: trans('topweb_chat::app.messages.media') }}</p>
                            <div class="mt-2 flex items-center gap-2 text-xs opacity-75">
                                <span>{{ $message->sent_at?->format('d/m/Y H:i') ?? $message->created_at?->format('d/m/Y H:i') }}</span>
                                <span>{{ $message->status }}</span>
                                @if (app(\Webkul\TopwebChat\Services\MessageService::class)->canRetry($message))
                                    <button
                                        type="button"
                                        class="underline"
                                        data-retry-url="{{ route('admin.topweb_chat.messages.retry', [$conversation, $message]) }}"
                                    >
                                        @lang('topweb_chat::app.messages.retry')
                                    </button>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="py-10 text-center text-gray-600 dark:text-gray-300">@lang('topweb_chat::app.messages.empty')</p>
                @endforelse
            </div>

            @if (bouncer()->hasPermission('topweb_chat.inbox.send'))
                <form
                    id="topweb-chat-send-form"
                    method="POST"
                    action="{{ route('admin.topweb_chat.messages.store', $conversation) }}"
                    class="grid shrink-0 gap-3 border-t border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                    style="flex: 0 0 auto;"
                >
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <textarea
                        name="content"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 bg-white p-3 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                        placeholder="@lang('topweb_chat::app.messages.placeholder')"
                        @disabled($conversation->instance?->status !== 'ready')
                        required
                    >{{ old('content') }}</textarea>

                    <div class="flex justify-end">
                        <button class="primary-button" @disabled($conversation->instance?->status !== 'ready')>
                            @lang('topweb_chat::app.messages.send')
                        </button>
                    </div>
                </form>
            @endif
        </section>

        <aside class="grid content-start gap-4">
            <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.crm.title')</h2>

                <div class="mt-3 grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <p>@lang('topweb_chat::app.crm.person'): {{ $conversation->person?->name ?? trans('topweb_chat::app.crm.not_linked') }}</p>
                    <p>@lang('topweb_chat::app.crm.lead'): {{ $conversation->lead?->title ?? trans('topweb_chat::app.crm.not_linked') }}</p>
                    <p>@lang('topweb_chat::app.crm.instance'): {{ $conversation->instance?->name }}</p>
                </div>

                @if (
                    $conversation->lead
                    && bouncer()->hasPermission('topweb_chat.inbox.stage')
                    && bouncer()->hasPermission('leads.edit')
                )
                    <form
                        method="POST"
                        action="{{ route('admin.topweb_chat.lead_stage.update', $conversation) }}"
                        class="mt-4 grid gap-3"
                    >
                        @csrf
                        @method('PUT')

                        <label class="text-sm font-medium text-gray-800 dark:text-white" for="lead_pipeline_stage_id">
                            @lang('topweb_chat::app.leads.stage')
                        </label>

                        <select id="lead_pipeline_stage_id" name="lead_pipeline_stage_id" class="custom-select" required>
                            @foreach ($pipelineStages as $stage)
                                <option value="{{ $stage->id }}" @selected($conversation->lead->lead_pipeline_stage_id === $stage->id)>
                                    {{ $stage->name }}
                                </option>
                            @endforeach
                        </select>

                        <button class="secondary-button">@lang('topweb_chat::app.leads.update_stage')</button>
                    </form>
                @endif
            </section>

            @if (bouncer()->hasPermission('topweb_chat.inbox.assign'))
                <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.assignment.title')</h2>

                    <form
                        method="POST"
                        action="{{ route('admin.topweb_chat.assignment.update', $conversation) }}"
                        class="mt-3 grid gap-3"
                    >
                        @csrf
                        @method('PUT')

                        @if ($isAdmin)
                            <select name="assigned_user_id" class="custom-select" required>
                                @foreach ($assignableUsers as $assignableUser)
                                    <option value="{{ $assignableUser->id }}" @selected($conversation->assigned_user_id === $assignableUser->id)>
                                        {{ $assignableUser->name }}
                                    </option>
                                @endforeach
                            </select>
                        @else
                            <input type="hidden" name="assigned_user_id" value="{{ $user->id }}">
                        @endif

                        <button class="primary-button">
                            {{ $isAdmin ? trans('topweb_chat::app.assignment.save') : trans('topweb_chat::app.assignment.claim') }}
                        </button>
                    </form>
                </section>
            @endif

            @if (bouncer()->hasPermission('topweb_chat.inbox.notes'))
                <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.notes.title')</h2>
                    <p class="text-xs text-gray-500">@lang('topweb_chat::app.notes.description')</p>

                    <form
                        method="POST"
                        action="{{ route('admin.topweb_chat.notes.store', $conversation) }}"
                        class="mt-3 grid gap-3"
                    >
                        @csrf

                        <textarea
                            name="content"
                            rows="3"
                            class="w-full rounded-lg border border-gray-300 bg-white p-3 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                            required
                        ></textarea>

                        <button class="primary-button">@lang('topweb_chat::app.notes.add')</button>
                    </form>

                    <div class="mt-4 grid max-h-72 gap-3 overflow-y-auto">
                        @foreach ($conversation->internalNotes as $note)
                            <article class="rounded-lg bg-amber-50 p-3 text-sm text-gray-800 dark:bg-gray-950 dark:text-gray-200">
                                <p class="whitespace-pre-wrap break-words">{{ $note->content }}</p>
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ $note->user?->name }} · {{ $note->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
    </div>

    @pushOnce('scripts')
        <script>
            window.addEventListener('load', () => {
                const timeline = document.getElementById('topweb-chat-timeline');
                const form = document.getElementById('topweb-chat-send-form');
                const instanceStatus = document.getElementById('topweb-chat-instance-status');
                const connectionWarning = document.getElementById('topweb-chat-connection-warning');

                if (!timeline) {
                    return;
                }

                const renderMessages = (messages, forceScroll = false) => {
                    const wasNearBottom = timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 80;
                    const anchor = [...timeline.children].find(
                        (element) => element.offsetTop + element.offsetHeight >= timeline.scrollTop
                    );
                    const anchorId = anchor?.dataset.messageId;
                    const anchorOffset = anchor ? anchor.offsetTop - timeline.scrollTop : 0;
                    timeline.replaceChildren();

                    if (!messages.length) {
                        const empty = document.createElement('p');
                        empty.className = 'py-10 text-center text-gray-600 dark:text-gray-300';
                        empty.textContent = @json(trans('topweb_chat::app.messages.empty'));
                        timeline.appendChild(empty);

                        return;
                    }

                    messages.forEach((message) => {
                        const article = document.createElement('article');
                        const bubble = document.createElement('div');
                        const content = document.createElement('p');
                        const metadata = document.createElement('div');
                        const timestamp = document.createElement('span');
                        const status = document.createElement('span');
                        const retry = document.createElement('button');
                        const outgoing = message.direction === 'outgoing';

                        article.dataset.messageId = message.id;
                        article.className = `flex ${outgoing ? 'justify-end' : 'justify-start'}`;
                        bubble.className = `max-w-[78%] rounded-lg px-4 py-3 ${
                            outgoing
                                ? 'bg-brandColor text-white'
                                : 'bg-white text-gray-800 dark:bg-gray-900 dark:text-white'
                        }`;
                        content.className = 'whitespace-pre-wrap break-words';
                        content.textContent = message.content || @json(trans('topweb_chat::app.messages.media'));
                        metadata.className = 'mt-2 flex gap-2 text-xs opacity-75';
                        timestamp.textContent = message.sent_at
                            ? new Intl.DateTimeFormat(document.documentElement.lang || 'pt-BR', {
                                dateStyle: 'short',
                                timeStyle: 'short',
                            }).format(new Date(message.sent_at))
                            : '';
                        status.textContent = message.status;

                        metadata.append(timestamp, status);

                        if (message.can_retry && message.retry_url) {
                            retry.type = 'button';
                            retry.className = 'underline';
                            retry.dataset.retryUrl = message.retry_url;
                            retry.textContent = @json(trans('topweb_chat::app.messages.retry'));
                            metadata.appendChild(retry);
                        }

                        bubble.append(content, metadata);
                        article.appendChild(bubble);
                        timeline.appendChild(article);
                    });

                    if (forceScroll || wasNearBottom) {
                        timeline.scrollTop = timeline.scrollHeight;
                    } else if (anchorId) {
                        const nextAnchor = timeline.querySelector(`[data-message-id="${anchorId}"]`);

                        if (nextAnchor) {
                            timeline.scrollTop = nextAnchor.offsetTop - anchorOffset;
                        }
                    }
                };

                const refresh = async () => {
                    if (document.hidden) {
                        return;
                    }

                    const response = await fetch(timeline.dataset.messagesUrl, {
                        cache: 'no-store',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (response.ok) {
                        const payload = await response.json();
                        const connected = payload.instance?.status === 'ready';

                        renderMessages(payload.messages);

                        if (instanceStatus) {
                            instanceStatus.textContent = payload.instance?.status || 'unknown';
                        }

                        connectionWarning?.classList.toggle('hidden', connected);
                        form?.querySelector('textarea')?.toggleAttribute('disabled', !connected);
                        form?.querySelector('button')?.toggleAttribute('disabled', !connected);
                    }
                };

                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const submit = form.querySelector('button[type="submit"], button:not([type])');
                    submit?.setAttribute('disabled', 'disabled');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('message_queue_failed');
                        }

                        form.querySelector('textarea').value = '';
                        form.querySelector('[name="operation_key"]').value = crypto.randomUUID();
                        await refresh();
                        timeline.scrollTop = timeline.scrollHeight;
                    } catch (error) {
                        window.alert(@json(trans('topweb_chat::app.messages.send_failed')));
                    } finally {
                        submit?.toggleAttribute(
                            'disabled',
                            instanceStatus?.textContent !== 'ready'
                        );
                    }
                });

                timeline.addEventListener('click', async (event) => {
                    const retry = event.target.closest('[data-retry-url]');

                    if (!retry) {
                        return;
                    }

                    retry.setAttribute('disabled', 'disabled');

                    try {
                        const response = await fetch(retry.dataset.retryUrl, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': form?.querySelector('[name="_token"]')?.value || '',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('message_retry_failed');
                        }

                        await refresh();
                    } catch (error) {
                        retry.removeAttribute('disabled');
                        window.alert(@json(trans('topweb_chat::app.messages.retry_not_available')));
                    }
                });

                timeline.scrollTop = timeline.scrollHeight;

                const poll = async () => {
                    try {
                        await refresh();
                    } finally {
                        window.setTimeout(poll, 3000);
                    }
                };

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        refresh().catch(() => {});
                    }
                });

                window.setTimeout(poll, 3000);
            });
        </script>
    @endPushOnce
</x-admin::layouts>
