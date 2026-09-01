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

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section
            class="relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            style="height: clamp(30rem, calc(100dvh - 10rem), 48rem); min-height: 0;"
        >
            <header class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
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
                class="flex min-h-0 flex-1 flex-col justify-start gap-2.5 overflow-y-auto bg-slate-50 p-4 sm:p-5 dark:bg-gray-950"
                style="min-height: 0; flex: 1 1 auto; overflow-y: auto;"
                data-messages-url="{{ route('admin.topweb_chat.messages.index', $conversation) }}"
                aria-live="polite"
            >
                @forelse ($conversation->messages as $message)
                    <article
                        class="flex {{ $message->direction === 'outgoing' ? 'justify-end' : 'justify-start' }}"
                        data-message-id="{{ $message->id }}"
                    >
                        <div class="max-w-[85%] rounded-2xl px-4 py-2.5 shadow-sm sm:max-w-[72%] {{ $message->direction === 'outgoing' ? 'rounded-br-md bg-brandColor text-white' : 'rounded-bl-md border border-gray-100 bg-white text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-white' }}">
                            @if ($message->hasMedia())
                                @php($mediaMime = (string) data_get($message->metadata, 'media_mime'))

                                @if ($canViewSensitiveMedia && $message->mediaIsStored())
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

            <button
                id="topweb-chat-new-messages"
                type="button"
                class="secondary-button absolute bottom-28 left-1/2 hidden -translate-x-1/2 shadow-lg"
            >
                @lang('topweb_chat::app.messages.new_messages') ↓
            </button>

            @if (bouncer()->hasPermission('topweb_chat.inbox.send'))
                <form
                    id="topweb-chat-send-form"
                    method="POST"
                    action="{{ route('admin.topweb_chat.messages.store', $conversation) }}"
                    class="flex shrink-0 items-end gap-3 border-t border-gray-200 bg-white p-3 sm:p-4 dark:border-gray-800 dark:bg-gray-900"
                    style="flex: 0 0 auto;"
                >
                    @csrf
                    <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

                    <textarea
                        name="content"
                        rows="1"
                        class="max-h-32 min-h-11 flex-1 resize-none rounded-2xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                        placeholder="@lang('topweb_chat::app.messages.placeholder')"
                        @disabled($conversation->instance?->status !== 'ready')
                        required
                    >{{ old('content') }}</textarea>

                    <div class="flex justify-end">
                        <button class="primary-button min-h-11 rounded-full px-5" @disabled($conversation->instance?->status !== 'ready')>
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
                const connectionBadge = document.getElementById('topweb-chat-connection-badge');
                const connectionWarning = document.getElementById('topweb-chat-connection-warning');
                const newMessages = document.getElementById('topweb-chat-new-messages');
                const canViewSensitiveMedia = @json($canViewSensitiveMedia);
                let refreshing = false;
                let lastMessageId = Number(
                    timeline?.querySelector('[data-message-id]:last-of-type')?.dataset.messageId || 0
                );

                if (!timeline) {
                    return;
                }

                const isNearBottom = () => (
                    timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 100
                );

                const mediaElement = (message) => {
                    if (!message.has_media) {
                        return null;
                    }

                    if (!message.media_url) {
                        const notice = document.createElement('p');
                        notice.className = 'mb-1 text-sm opacity-80';
                        notice.textContent = canViewSensitiveMedia
                            ? @json(trans('topweb_chat::app.messages.media_processing'))
                            : @json(trans('topweb_chat::app.messages.media_restricted'));

                        return notice;
                    }

                    if (message.media_mime?.startsWith('image/')) {
                        const link = document.createElement('a');
                        const image = document.createElement('img');
                        link.href = message.media_url;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        image.src = message.media_url;
                        image.alt = @json(trans('topweb_chat::app.messages.media_image'));
                        image.loading = 'lazy';
                        image.className = 'mb-2 max-h-80 w-auto max-w-full rounded-xl object-contain';
                        link.appendChild(image);

                        return link;
                    }

                    if (message.media_mime?.startsWith('audio/')) {
                        const audio = document.createElement('audio');
                        audio.controls = true;
                        audio.preload = 'metadata';
                        audio.src = message.media_url;
                        audio.className = 'mb-2 max-w-full';

                        return audio;
                    }

                    if (message.media_mime?.startsWith('video/')) {
                        const video = document.createElement('video');
                        video.controls = true;
                        video.preload = 'metadata';
                        video.src = message.media_url;
                        video.className = 'mb-2 max-h-80 max-w-full rounded-xl';

                        return video;
                    }

                    const link = document.createElement('a');
                    link.href = message.media_url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.className = 'mb-2 flex items-center gap-2 rounded-xl bg-black/10 px-3 py-2 font-medium hover:underline';
                    link.textContent = @json(trans('topweb_chat::app.messages.open_media'));

                    return link;
                };

                const renderMessages = (messages, forceScroll = false) => {
                    const wasNearBottom = isNearBottom();
                    const previousLastMessageId = lastMessageId;
                    const nextLastMessageId = Number(messages.at(-1)?.id || 0);
                    const receivedNewMessage = previousLastMessageId > 0
                        && nextLastMessageId !== previousLastMessageId;
                    const anchor = [...timeline.querySelectorAll('[data-message-id]')].find(
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

                    let previousDate = null;

                    messages.forEach((message) => {
                        const messageDate = message.sent_at ? new Date(message.sent_at) : null;
                        const dateKey = messageDate?.toLocaleDateString('en-CA');

                        if (dateKey && dateKey !== previousDate) {
                            const separator = document.createElement('div');
                            separator.className = 'my-2 flex justify-center';
                            separator.innerHTML = `<span class="rounded-full bg-white/90 px-3 py-1 text-xs font-medium text-gray-500 shadow-sm dark:bg-gray-900 dark:text-gray-300">${new Intl.DateTimeFormat(document.documentElement.lang || 'pt-BR', { dateStyle: 'medium' }).format(messageDate)}</span>`;
                            timeline.appendChild(separator);
                            previousDate = dateKey;
                        }

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
                        bubble.className = `max-w-[85%] rounded-2xl px-4 py-2.5 shadow-sm sm:max-w-[72%] ${
                            outgoing
                                ? 'rounded-br-md bg-brandColor text-white'
                                : 'rounded-bl-md border border-gray-100 bg-white text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-white'
                        }`;
                        content.className = 'whitespace-pre-wrap break-words';
                        content.textContent = message.content || (
                            message.has_media
                                ? ''
                                : @json(trans('topweb_chat::app.messages.unsupported'))
                        );
                        metadata.className = 'mt-2 flex gap-2 text-xs opacity-75';
                        timestamp.textContent = message.sent_at
                            ? new Intl.DateTimeFormat(document.documentElement.lang || 'pt-BR', {
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

                        const media = mediaElement(message);

                        if (media) {
                            bubble.appendChild(media);
                        }

                        if (content.textContent) {
                            bubble.appendChild(content);
                        }

                        bubble.appendChild(metadata);
                        article.appendChild(bubble);
                        timeline.appendChild(article);
                    });

                    lastMessageId = nextLastMessageId;

                    if (forceScroll || wasNearBottom) {
                        timeline.scrollTop = timeline.scrollHeight;
                        newMessages?.classList.add('hidden');
                    } else if (anchorId) {
                        const nextAnchor = timeline.querySelector(`[data-message-id="${anchorId}"]`);

                        if (nextAnchor) {
                            timeline.scrollTop = nextAnchor.offsetTop - anchorOffset;
                        }
                    }

                    if (receivedNewMessage && !wasNearBottom && !forceScroll) {
                        newMessages?.classList.remove('hidden');
                    }
                };

                const refresh = async () => {
                    if (document.hidden || refreshing) {
                        return;
                    }

                    refreshing = true;

                    try {
                        const response = await fetch(timeline.dataset.messagesUrl, {
                            cache: 'no-store',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('message_refresh_failed');
                        }

                        const payload = await response.json();
                        const connected = payload.instance?.status === 'ready';

                        renderMessages(payload.messages);

                        if (instanceStatus) {
                            instanceStatus.textContent = payload.instance?.status || 'unknown';
                        }

                        connectionBadge?.classList.toggle('bg-emerald-50', connected);
                        connectionBadge?.classList.toggle('text-emerald-700', connected);
                        connectionBadge?.classList.toggle('bg-amber-50', !connected);
                        connectionBadge?.classList.toggle('text-amber-700', !connected);
                        connectionWarning?.classList.toggle('hidden', connected);
                        form?.querySelector('textarea')?.toggleAttribute('disabled', !connected);
                        form?.querySelector('button')?.toggleAttribute('disabled', !connected);
                    } finally {
                        refreshing = false;
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

                timeline.addEventListener('scroll', () => {
                    if (isNearBottom()) {
                        newMessages?.classList.add('hidden');
                    }
                }, { passive: true });

                newMessages?.addEventListener('click', () => {
                    timeline.scrollTo({ top: timeline.scrollHeight, behavior: 'smooth' });
                    newMessages.classList.add('hidden');
                });

                const composer = form?.querySelector('textarea');

                composer?.addEventListener('input', () => {
                    composer.style.height = 'auto';
                    composer.style.height = `${Math.min(composer.scrollHeight, 128)}px`;
                });

                composer?.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        form?.requestSubmit();
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

                refresh().catch(() => {});
                window.setTimeout(poll, 3000);
            });
        </script>
    @endPushOnce
</x-admin::layouts>
