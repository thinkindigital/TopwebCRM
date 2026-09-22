{{-- E-02 C3: composer (form + clip menu + preview + textarea + envio).
     Interface: $conversation, $canAttachSensitive. Sem mudança visual/comportamento. --}}
@if (bouncer()->hasPermission('topweb_chat.inbox.send'))
    <form
        id="topweb-chat-send-form"
        method="POST"
        action="{{ route('admin.topweb_chat.messages.store', $conversation) }}"
        data-batch-url="{{ route('admin.topweb_chat.messages.batch', $conversation) }}"
        data-max-file-kb="{{ (int) (config('topweb-chat.openwa.media_max_bytes', 104858624) / 1024) }}"
        class="flex shrink-0 items-end gap-3 border-t border-gray-200 bg-white p-3 sm:p-4 dark:border-gray-800 dark:bg-gray-900 flex-shrink-0"
    >
        @csrf
        <input type="hidden" name="operation_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

        <details id="topweb-chat-attach-menu" class="relative">
            <summary
                class="grid max-h-11 min-h-11 min-w-11 cursor-pointer list-none place-items-center rounded-full border border-gray-300 bg-gray-50 text-gray-600 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                aria-label="@lang('topweb_chat::app.messages.attach_menu')"
                title="@lang('topweb_chat::app.messages.attach_menu')"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m21 12-8.5 8.5a5.5 5.5 0 0 1-7.78-7.78L12 5.4a3.67 3.67 0 0 1 5.2 5.2l-7.07 7.07a1.83 1.83 0 0 1-2.6-2.6L14.6 8"/></svg>
            </summary>
            <div class="absolute left-0 z-20 w-52 rounded-xl border border-gray-200 bg-white p-1.5 text-sm shadow-xl dark:border-gray-800 dark:bg-gray-900" style="bottom:3rem">
                <button
                    id="topweb-chat-attach-image"
                    type="button"
                    class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800"
                    aria-label="@lang('topweb_chat::app.messages.attach_image')"
                    data-requires-channel
                    @disabled($conversation->instance?->status !== 'ready' || ! $canAttachSensitive)
                >@lang('topweb_chat::app.messages.attach_image')</button>
                <input
                    id="topweb-chat-media-input"
                    type="file"
                    name="media"
                    class="hidden"
                    accept="image/*,video/*,audio/*"
                    multiple
                    data-requires-channel
                    @disabled(! $canAttachSensitive)
                >

                <button
                    id="topweb-chat-attach-document"
                    type="button"
                    class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800"
                    aria-label="@lang('topweb_chat::app.messages.attach_document')"
                    data-requires-channel
                    @disabled($conversation->instance?->status !== 'ready' || ! $canAttachSensitive)
                >@lang('topweb_chat::app.messages.attach_document')</button>
                <input
                    id="topweb-chat-document-input"
                    type="file"
                    name="document"
                    class="hidden"
                    accept=".pdf,.doc,.docx,.txt,.xls,.xlsx"
                    multiple
                    data-requires-channel
                    @disabled(! $canAttachSensitive)
                >

                @if ($canAttachSensitive)
                    <button
                        id="topweb-chat-attach-location"
                        type="button"
                        class="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-label="@lang('topweb_chat::app.messages.attach_location')"
                        data-requires-channel
                        @disabled($conversation->instance?->status !== 'ready')
                    >@lang('topweb_chat::app.messages.attach_location')</button>
                    <input
                        id="topweb-chat-location-input"
                        type="hidden"
                        name="location"
                    >
                @endif
            </div>
        </details>

        <div id="topweb-chat-media-preview" class="hidden max-h-11 items-center gap-2 overflow-hidden text-xs text-gray-600 dark:text-gray-300"></div>

        <textarea
            id="topweb-chat-content"
            name="content"
            rows="1"
            class="max-h-32 min-h-11 flex-1 resize-none rounded-2xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 focus:border-brandColor dark:border-gray-800 dark:bg-gray-950 dark:text-white"
            placeholder="@lang('topweb_chat::app.messages.placeholder')"
            data-requires-channel
            @disabled($conversation->instance?->status !== 'ready')
        >{{ old('content') }}</textarea>

        <div class="flex justify-end">
            <button type="submit" class="primary-button min-h-11 rounded-full px-5" data-requires-channel @disabled($conversation->instance?->status !== 'ready')>
                @lang('topweb_chat::app.messages.send')
            </button>
        </div>
        <p data-channel-error class="hidden w-full text-xs text-amber-700 dark:text-amber-300">@lang('topweb_chat::app.channel.unavailable_hint')</p>
    </form>
@endif
