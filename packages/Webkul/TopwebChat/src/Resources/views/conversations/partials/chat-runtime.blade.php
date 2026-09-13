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
