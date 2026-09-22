{{-- E-02 C4: runtime JS (polling + scroll + composer + retry + telemetria). --}}
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
                let refreshQueued = false;
                let lastMessageId = Number(
                    timeline?.querySelector('[data-message-id]:last-of-type')?.dataset.messageId || 0
                );

                if (!timeline) {
                    return;
                }

                const reportClientEvent = (level, event, context = {}, errorCode = null, traceId = null) => {
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
                        body: JSON.stringify({ level, event, error_code: errorCode, trace_id: traceId, context }),
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
                        refreshQueued = true;
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
                            const badge = document.getElementById('topweb-chat-connection-badge');
                            instanceStatus.textContent = connected
                                ? (badge?.dataset.labelConnected ?? instanceStatus.textContent)
                                : (badge?.dataset.labelUnavailable ?? instanceStatus.textContent);
                            instanceStatus.title = connected
                                ? (badge?.dataset.titleConnected ?? instanceStatus.textContent)
                                : (badge?.dataset.titleUnavailable ?? instanceStatus.textContent);
                        }

                        connectionBadge?.classList.toggle('bg-emerald-50', connected);
                        connectionBadge?.classList.toggle('text-emerald-700', connected);
                        connectionBadge?.classList.toggle('bg-amber-50', !connected);
                        connectionBadge?.classList.toggle('text-amber-700', !connected);
                        connectionWarning?.classList.toggle('hidden', connected);
                        form?.querySelectorAll('[data-requires-channel]').forEach((control) => {
                            control.toggleAttribute('disabled', !connected);
                        });
                    } finally {
                        refreshing = false;
                        if (refreshQueued) {
                            refreshQueued = false;
                            void refresh();
                        }
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

                const batchLabels = {
                    ready: @json(trans('topweb_chat::app.messages.batch_state_ready')),
                    sending: @json(trans('topweb_chat::app.messages.batch_state_sending')),
                    sent: @json(trans('topweb_chat::app.messages.batch_state_sent')),
                    failed: @json(trans('topweb_chat::app.messages.batch_state_failed')),
                    itemError: @json(trans('topweb_chat::app.messages.batch_item_error')),
                    tooLarge: @json(trans('topweb_chat::app.messages.media_too_large')),
                    commFailed: @json(trans('topweb_chat::app.messages.comm_failed')),
                };

                // #111: pré-checagem local — arquivo acima do limite nem é enviado.
                const maxFileKb = Number(form?.dataset.maxFileKb || 0);
                const oversized = () => pendingAttachments.some(
                    (item) => maxFileKb > 0 && item.file.size > maxFileKb * 1024
                );
                const rejectOversized = () => {
                    pendingAttachments.forEach((item) => setTrayState(item.key, 'failed'));
                    window.alert(batchLabels.tooLarge);
                    reportClientEvent('error', 'client.send_failed', {
                        timeline_connected: timeline.isConnected,
                        form_connected: form?.isConnected || false,
                        browser_locale: browserLocale,
                    }, 'FIL-6001');
                };

                const pendingAttachments = [];

                const formatFileSize = (bytes) => {
                    if (!bytes) return '0 B';
                    if (bytes < 1024) return `${bytes} B`;
                    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
                    return `${(bytes / 1048576).toFixed(1)} MB`;
                };

                const renderTray = () => {
                    if (!mediaPreview) return;

                    mediaPreview.replaceChildren();

                    if (!pendingAttachments.length) {
                        clearMediaPreview();
                        return;
                    }

                    mediaPreview.classList.remove('hidden');
                    mediaPreview.classList.add('flex');
                    mediaPreview.classList.toggle('flex-col', pendingAttachments.length > 1);
                    mediaPreview.style.maxHeight = pendingAttachments.length >= 4 ? '154px' : '';
                    mediaPreview.style.overflowY = pendingAttachments.length >= 4 ? 'auto' : '';

                    pendingAttachments.forEach((item) => {
                        const row = document.createElement('div');
                        row.className = 'flex w-full items-center gap-2';
                        row.dataset.trayItem = item.key;

                        const name = document.createElement('span');
                        name.className = 'max-w-40 flex-1 truncate';
                        name.textContent = item.file.name;
                        row.appendChild(name);

                        const meta = document.createElement('span');
                        meta.className = 'opacity-75';
                        meta.textContent = formatFileSize(item.file.size);
                        row.appendChild(meta);

                        const state = document.createElement('span');
                        state.dataset.trayState = item.key;
                        state.textContent = batchLabels[item.state] ?? item.state;
                        row.appendChild(state);

                        if (item.error) {
                            const error = document.createElement('span');
                            error.className = 'font-mono text-red-600';
                            error.textContent = `Error Code: ${item.error}`;
                            row.appendChild(error);
                        }

                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'font-bold text-red-600 hover:text-red-800';
                        remove.textContent = '×';
                        remove.dataset.trayRemove = item.key;
                        remove.setAttribute('aria-label', @json(trans('topweb_chat::app.messages.remove_attachment')));
                        remove.addEventListener('click', () => {
                            const index = pendingAttachments.findIndex((entry) => entry.key === item.key);
                            if (index >= 0) pendingAttachments.splice(index, 1);
                            renderTray();
                        });
                        row.appendChild(remove);

                        mediaPreview.appendChild(row);
                    });
                };

                const setTrayState = (key, state) => {
                    const item = pendingAttachments.find((entry) => entry.key === key);
                    if (item) item.state = state;
                    const label = mediaPreview?.querySelector(`[data-tray-state="${key}"]`);
                    if (label) label.textContent = batchLabels[state] ?? state;
                };

                mediaInput?.addEventListener('change', () => {
                    Array.from(mediaInput.files ?? []).forEach((file) => {
                        pendingAttachments.push({ key: crypto.randomUUID(), file, state: 'ready' });
                    });
                    mediaInput.value = '';
                    renderTray();
                });
                documentInput?.addEventListener('change', () => {
                    Array.from(documentInput.files ?? []).forEach((file) => {
                        pendingAttachments.push({ key: crypto.randomUUID(), file, state: 'ready' });
                    });
                    documentInput.value = '';
                    renderTray();
                });

                const submitBatch = async (form, contentField, submit) => {
                    const payload = new FormData();
                    payload.append('_token', form.querySelector('[name="_token"]')?.value ?? '');

                    const keys = pendingAttachments.map(() => crypto.randomUUID());
                    pendingAttachments.forEach((item, index) => {
                        payload.append(`attachments[${index}][file]`, item.file);
                        payload.append(`attachments[${index}][operation_key]`, keys[index]);
                        setTrayState(item.key, 'sending');
                    });

                    const content = contentField?.value.trim() ?? '';
                    if (content) {
                        payload.append('content', content);
                        payload.append('content_operation_key', crypto.randomUUID());
                    }

                    const response = await fetch(form.dataset.batchUrl, {
                        method: 'POST',
                        body: payload,
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        pendingAttachments.forEach((item) => setTrayState(item.key, 'failed'));
                        throw new Error(`message_batch_failed:${response.status}`);
                    }

                    const body = await response.json();
                    const sentKeys = new Set((body.messages ?? []).map((entry) => entry.operation_key));
                    const failedByKey = new Map((body.rejected ?? []).map((entry) => [entry.operation_key, entry]));

                    for (let index = pendingAttachments.length - 1; index >= 0; index--) {
                        const item = pendingAttachments[index];
                        const key = keys[pendingAttachments.indexOf(item)];
                        if (sentKeys.has(key)) {
                            setTrayState(item.key, 'sent');
                            pendingAttachments.splice(index, 1);
                        } else {
                            setTrayState(item.key, 'failed');
                            item.error = failedByKey.get(key)?.error_code ?? 'FIL-4001';
                        }
                    }

                    if (contentField) contentField.value = '';
                    form.querySelector('[name="operation_key"]').value = crypto.randomUUID();
                    renderTray();
                    await refresh();
                    timeline.scrollTop = timeline.scrollHeight;
                };

                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    const submit = form.querySelector('button[type="submit"], button:not([type])');
                    submit?.setAttribute('disabled', 'disabled');

                    try {
                        const mediaInput = document.getElementById('topweb-chat-media-input');
                        const documentInput = document.getElementById('topweb-chat-document-input');
                        const contentField = document.getElementById('topweb-chat-content');

                        if (!pendingAttachments.length && !contentField?.value.trim()) {
                            contentField?.focus();

                            return;
                        }

                        if (oversized()) {
                            rejectOversized();

                            return;
                        }

                        if (pendingAttachments.length >= 2) {
                            await submitBatch(form, contentField, submit);

                            return;
                        }

                        if (pendingAttachments.length === 1) {
                            const single = pendingAttachments[0];
                            const transfer = new DataTransfer();
                            transfer.items.add(single.file);
                            const target = single.file.type.startsWith('image/')
                                || single.file.type.startsWith('video/')
                                || single.file.type.startsWith('audio/')
                                ? mediaInput
                                : documentInput;
                            target.files = transfer.files;
                        }

                        const payload = new FormData(form);

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
                        pendingAttachments.length = 0;
                        clearMediaPreview();
                        form.querySelector('[name="operation_key"]').value = crypto.randomUUID();
                        await refresh();
                        timeline.scrollTop = timeline.scrollHeight;
                    } catch (error) {
                        reportClientEvent('error', 'client.send_failed', {
                            timeline_connected: timeline.isConnected,
                            form_connected: form?.isConnected || false,
                            browser_locale: browserLocale,
                        }, error instanceof TypeError ? 'NET-3001' : 'API-9001');
                        if (String(error?.message || '').endsWith(':419')) {
                            window.alert(@json(trans('topweb_chat::app.messages.session_expired')));
                            window.location.reload();

                            return;
                        }
                        // #111: sem resposta (rede morta) tem código próprio.
                        if (error instanceof TypeError) {
                            window.alert(batchLabels.commFailed);

                            return;
                        }
                        const channelDown = document.getElementById('topweb-chat-poll-meta')
                            ?.dataset.instanceStatus !== 'ready';
                        const channelError = form?.querySelector('[data-channel-error]');

                        if (channelDown && channelError) {
                            channelError.classList.remove('hidden');
                        } else {
                            window.alert(@json(trans('topweb_chat::app.messages.send_failed')));
                        }
                    } finally {
                        const channelReady = document.getElementById('topweb-chat-poll-meta')
                            ?.dataset.instanceStatus === 'ready';
                        submit?.toggleAttribute('disabled', ! channelReady);
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

                document.addEventListener('topwebchat:refresh-timeline', () => {
                    refresh().catch((error) => {
                        console.error('TopwebChat manual refresh failed.', error);
                    });
                });

                let pollCount = 0;

                const isContextQuiet = () => {
                    const context = document.getElementById('topweb-chat-context');
                    if (!context) return false;

                    if (context.querySelector('details[open]')) return false;

                    return !Array.from(
                        context.querySelectorAll('textarea, input:not([type="hidden"]):not([type="submit"]):not([type="button"])')
                    ).some((field) => (field.value ?? '').trim() !== '');
                };

                const poll = async () => {
                    try {
                        await refresh();
                        pollCount += 1;

                        if (pollCount % 5 === 0 && isContextQuiet()) {
                            document.dispatchEvent(new CustomEvent('topwebchat:refresh-context'));
                        }
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
