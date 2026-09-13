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

        <aside class="grid content-start gap-4">
            <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.crm.title')</h2>

                <div class="mt-3 grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <p>@lang('topweb_chat::app.crm.person'): {{ $conversation->person?->name ?? trans('topweb_chat::app.crm.not_linked') }}</p>
                    <p>@lang('topweb_chat::app.crm.lead'): {{ $conversation->lead?->title ?? trans('topweb_chat::app.crm.not_linked') }}</p>
                    @if (bouncer()->hasPermission('topweb_chat.settings.index'))
                        <p>@lang('topweb_chat::app.crm.instance'): {{ $conversation->instance?->name }}</p>
                    @endif
                </div>

                <div class="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-950">
                    <p class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.next_action.title')</p>
                    @if ($nextAction)
                        <p class="mt-1 text-gray-700 dark:text-gray-200">
                            {{ trans('topweb_chat::app.next_action.kind_'.$nextAction['kind']) }}
                            @if ($nextAction['when']) · {{ $nextAction['when'] }}@endif
                            @if ($nextAction['status'] === 'overdue') · @lang('topweb_chat::app.next_action.overdue_dot')@endif
                        </p>
                        @if ($nextAction['owner'])
                            <p class="text-xs text-gray-500">{{ $nextAction['owner'] }}</p>
                        @endif
                    @else
                        <p class="mt-1 text-gray-500">@lang('topweb_chat::app.next_action.none')</p>
                    @endif
                    @if ($conversation->lead)
                        <a href="{{ route('admin.leads.view', $conversation->lead) }}" class="mt-1 inline-block text-xs font-medium text-brandColor hover:underline">
                            @lang('topweb_chat::app.next_action.manage')
                        </a>
                    @endif
                </div>

                @if ($recentActions !== [])
                    <div class="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-950">
                        <p class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.next_action.recent')</p>
                        <ul class="mt-1 grid gap-1.5">
                            @foreach ($recentActions as $recent)
                                <li class="text-gray-700 dark:text-gray-200">
                                    {{ trans('topweb_chat::app.next_action.kind_'.$recent['kind']) }}@if ($recent['when']) · {{ $recent['when'] }}@endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

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

                    @if ($isAdmin || $conversation->assigned_user_id === null)
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
                    @endif

                    @if ($canReleaseConversation)
                        <form
                            method="POST"
                            action="{{ route('admin.topweb_chat.assignment.update', $conversation) }}"
                            class="mt-3"
                            onsubmit="return confirm(@json(trans('topweb_chat::app.assignment.release_confirm')));"
                        >
                            @csrf
                            @method('PUT')

                            <input type="hidden" name="assigned_user_id" value="">

                            <button class="secondary-button w-full justify-center">
                                @lang('topweb_chat::app.assignment.release')
                            </button>
                        </form>
                    @endif
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
                window.setTimeout(() => {
                const timeline = document.getElementById('topweb-chat-timeline');
                const form = document.getElementById('topweb-chat-send-form');
                const instanceStatus = document.getElementById('topweb-chat-instance-status');
                const connectionBadge = document.getElementById('topweb-chat-connection-badge');
                const connectionWarning = document.getElementById('topweb-chat-connection-warning');
                const newMessages = document.getElementById('topweb-chat-new-messages');
                const syncStatus = document.getElementById('topweb-chat-sync-status');
                const clientLogUrl = @json(route('admin.topweb_chat.client_events.store', $conversation));
                const rawBrowserLocale = (document.documentElement.lang || 'pt-BR').trim().replaceAll('_', '-');
                let browserLocale = 'pt-BR';
                try { browserLocale = Intl.getCanonicalLocales(rawBrowserLocale)[0] ?? 'pt-BR'; } catch { browserLocale = 'pt-BR'; }
                let refreshing = false;
                let lastMessageId = Number(
                    timeline?.querySelector('[data-message-id]:last-of-type')?.dataset.messageId || 0
                );

                if (!timeline) {
                    return;
                }

                const reportClientEvent = (level, event, context = {}) => {
                    const token = form?.querySelector('[name="_token"]')?.value;

                    if (!token) {
                        return;
                    }

                    fetch(clientLogUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ level, event, context }),
                    }).catch(() => {});
                };

                // Âncora de scroll (T1.1): pinned = sentinela visível. Fallback = threshold existente.
                let isPinned = true;
                const ensureAnchor = () => {
                    let anchor = document.getElementById('topweb-chat-anchor');
                    if (!anchor) {
                        anchor = document.createElement('div');
                        anchor.id = 'topweb-chat-anchor';
                        anchor.style.height = '1px';
                        anchor.setAttribute('aria-hidden', 'true');
                    }
                    if ('IntersectionObserver' in window) {
                        new IntersectionObserver(([entry]) => {
                            isPinned = entry.isIntersecting;
                        }, { root: timeline }).observe(anchor);
                    }
                    return anchor;
                };

                const updateSyncStatus = (ok) => {
                    if (!syncStatus) {
                        return;
                    }

                    syncStatus.textContent = ok
                        ? `${@json(trans('topweb_chat::app.messages.sync_ok'))} ${new Date().toLocaleTimeString(browserLocale)}`
                        : @json(trans('topweb_chat::app.messages.sync_error'));
                    syncStatus.classList.toggle('text-red-700', !ok);
                    syncStatus.classList.toggle('bg-red-50', !ok);
                };

                reportClientEvent('info', 'client.initialized', {
                    timeline_connected: timeline.isConnected,
                    form_connected: form?.isConnected || false,
                });

                const isNearBottom = () => (
                    timeline.scrollHeight - timeline.scrollTop - timeline.clientHeight < 100
                );

                const renderFragment = (html) => {
                    const wasNearBottom = isNearBottom();
                    const distanceFromBottom = timeline.scrollHeight
                        - timeline.scrollTop
                        - timeline.clientHeight;
                    const previousLastMessageId = lastMessageId;

                    timeline.innerHTML = html;
                    ensureAnchor();

                    const meta = document.getElementById('topweb-chat-poll-meta');

                    if (!meta) {
                        throw new Error('message_fragment_malformed');
                    }

                    const nextLastMessageId = Number(meta.dataset.lastId || 0);
                    const connected = (meta.dataset.instanceStatus || 'unknown') === 'ready';
                    const receivedNewMessage = previousLastMessageId > 0
                        && nextLastMessageId !== previousLastMessageId;

                    lastMessageId = nextLastMessageId;

                    const restoreScroll = () => {
                        if (wasNearBottom && isPinned) {
                            timeline.scrollTop = timeline.scrollHeight;
                        } else {
                            timeline.scrollTop = Math.max(
                                0,
                                timeline.scrollHeight - timeline.clientHeight - distanceFromBottom
                            );
                        }
                    };

                    window.requestAnimationFrame(restoreScroll);

                    if (wasNearBottom && isPinned) {
                        newMessages?.classList.add('hidden');
                    }

                    if (receivedNewMessage && !(wasNearBottom && isPinned)) {
                        newMessages?.classList.remove('hidden');
                    }

                    return connected;
                };

                const refresh = async () => {
                    if (refreshing) {
                        return;
                    }

                    refreshing = true;

                    try {
                        const url = new URL(timeline.dataset.messagesUrl, window.location.origin);
                        url.searchParams.set('_poll', Date.now().toString());
                        url.searchParams.set('fragment', 'timeline');

                        const response = await fetch(url, {
                            cache: 'no-store',
                            credentials: 'same-origin',
                            headers: {
                                Accept: 'text/html',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok || !response.headers.get('content-type')?.includes('text/html')) {
                            throw new Error('message_refresh_failed');
                        }

                        const connected = renderFragment(await response.text());
                        updateSyncStatus(true);

                        if (instanceStatus) {
                            instanceStatus.textContent = connected ? 'ready' : instanceStatus.textContent;
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

                const attachImageButton = document.getElementById('topweb-chat-attach-image');
                const mediaInput = document.getElementById('topweb-chat-media-input');
                const attachDocumentButton = document.getElementById('topweb-chat-attach-document');
                const documentInput = document.getElementById('topweb-chat-document-input');
                const attachLocationButton = document.getElementById('topweb-chat-attach-location');
                const attachContactButton = document.getElementById('topweb-chat-attach-contact');
                const mediaPreview = document.getElementById('topweb-chat-media-preview');
                const contentField = document.getElementById('topweb-chat-content');

                const clearMediaPreview = () => {
                    if (mediaPreview) {
                        mediaPreview.replaceChildren();
                        mediaPreview.classList.add('hidden');
                        mediaPreview.classList.remove('flex');
                    }
                    contentField?.setAttribute('placeholder', @json(trans('topweb_chat::app.messages.placeholder')));
                };

                const setupAttachButton = (button, input, accept) => {
                    button?.addEventListener('click', () => {
                        input.accept = accept;
                        input?.click();
                    });
                };

                setupAttachButton(attachImageButton, mediaInput, 'image/*,video/*');
                setupAttachButton(attachDocumentButton, documentInput, '.pdf,.doc,.docx,.txt,.xls,.xlsx');

                if (attachLocationButton) {
                    attachLocationButton.addEventListener('click', () => {
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    const locationData = JSON.stringify({
                                        latitude: position.coords.latitude,
                                        longitude: position.coords.longitude,
                                    });
                                    const locationInput = document.getElementById('topweb-chat-location-input');
                                    locationInput.value = locationData;
                                    document.getElementById('topweb-chat-send-form').requestSubmit();
                                },
                                (error) => {
                                    window.alert(@json(trans('topweb_chat::app.messages.location_failed')));
                                }
                            );
                        } else {
                            window.alert(@json(trans('topweb_chat::app.messages.location_not_supported')));
                        }
                    });
                }

                if (attachContactButton) {
                    attachContactButton.addEventListener('click', () => {
                        window.alert(@json(trans('topweb_chat::app.messages.contact_not_available')));
                    });
                }

                const handleFileInput = (input, preview) => {
                    const file = input.files?.[0];
                    if (!file || !preview) {
                        clearMediaPreview();
                        return;
                    }

                    preview.replaceChildren();
                    preview.classList.remove('hidden');
                    preview.classList.add('flex');

                    if (file.type.startsWith('image/')) {
                        const thumb = document.createElement('img');
                        thumb.src = URL.createObjectURL(file);
                        thumb.alt = file.name;
                        thumb.className = 'max-h-11 w-auto rounded-lg object-contain';
                        preview.appendChild(thumb);
                    } else if (file.type.startsWith('video/')) {
                        const thumb = document.createElement('video');
                        thumb.src = URL.createObjectURL(file);
                        thumb.className = 'max-h-11 w-auto rounded-lg object-contain';
                        preview.appendChild(thumb);
                    } else {
                        const icon = document.createElement('span');
                        icon.className = 'text-2xl';
                        icon.textContent = '📄';
                        preview.appendChild(icon);
                    }

                    const name = document.createElement('span');
                    name.className = 'max-w-40 truncate';
                    name.textContent = file.name;
                    preview.appendChild(name);

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'font-bold text-red-600 hover:text-red-800';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', @json(trans('topweb_chat::app.messages.remove_attachment')));
                    remove.addEventListener('click', () => {
                        input.value = '';
                        clearMediaPreview();
                    });
                    preview.appendChild(remove);
                };

                mediaInput?.addEventListener('change', () => handleFileInput(mediaInput, mediaPreview));
                documentInput?.addEventListener('change', () => handleFileInput(documentInput, mediaPreview));

                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const submit = form.querySelector('button[type="submit"], button:not([type])');
                    submit?.setAttribute('disabled', 'disabled');

                    try {
                        const mediaInput = document.getElementById('topweb-chat-media-input');
                        const documentInput = document.getElementById('topweb-chat-document-input');
                        const contentField = document.getElementById('topweb-chat-content');
                        const payload = new FormData(form);

                        if (!mediaInput?.files?.length && !documentInput?.files?.length && !contentField?.value.trim()) {
                            contentField?.focus();

                            return;
                        }

                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: payload,
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            throw new Error(`message_queue_failed:${response.status}`);
                        }

                        form.querySelector('textarea').value = '';
                        if (mediaInput) {
                            mediaInput.value = '';
                        }
                        if (documentInput) {
                            documentInput.value = '';
                        }
                        clearMediaPreview();
                        form.querySelector('[name="operation_key"]').value = crypto.randomUUID();
                        await refresh();
                        timeline.scrollTop = timeline.scrollHeight;
                    } catch (error) {
                        reportClientEvent('error', 'client.send_failed', {
                            timeline_connected: timeline.isConnected,
                            form_connected: form?.isConnected || false,
                            message: String(error?.message || error).slice(0, 240),
                            browser_locale: browserLocale,
                        });
                        if (String(error?.message || '').endsWith(':419')) {
                            window.alert(@json(trans('topweb_chat::app.messages.session_expired')));
                            window.location.reload();

                            return;
                        }
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
                    isPinned = true;
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
                    } catch (error) {
                        updateSyncStatus(false);
                        reportClientEvent('error', 'client.refresh_failed', {
                            timeline_connected: timeline.isConnected,
                            form_connected: form?.isConnected || false,
                            message: String(error?.message || error).slice(0, 240),
                            browser_locale: browserLocale,
                        });
                        console.error('TopwebChat refresh failed.', error);
                    } finally {
                        window.setTimeout(poll, 3000);
                    }
                };

                document.addEventListener('visibilitychange', () => {
                    if (!document.hidden) {
                        refresh().catch((error) => {
                            reportClientEvent('error', 'client.refresh_failed', {
                                timeline_connected: timeline.isConnected,
                                form_connected: form?.isConnected || false,
                                message: String(error?.message || error).slice(0, 240),
                                browser_locale: browserLocale,
                            });
                            console.error('TopwebChat refresh failed after visibility change.', error);
                        });
                    }
                });

                refresh().catch((error) => {
                    reportClientEvent('error', 'client.refresh_failed', {
                        timeline_connected: timeline.isConnected,
                        form_connected: form?.isConnected || false,
                        message: String(error?.message || error).slice(0, 240),
                        browser_locale: browserLocale,
                    });
                    console.error('TopwebChat initial refresh failed.', error);
                });
                window.setTimeout(poll, 3000);
                }, 0);
            });
        </script>
    @endPushOnce
</x-admin::layouts>
