<x-admin::layouts>
    <x-slot:title>
        @lang('topweb_chat::app.menu.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-300 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div>
                <p class="text-xl font-bold text-gray-800 dark:text-white">@lang('topweb_chat::app.menu.title')</p>
                <p class="text-sm text-gray-600 dark:text-gray-300">@lang('topweb_chat::app.conversations.description')</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('admin.topweb_chat.index', ['queue' => 'mine']) }}"
                    class="{{ $queue === 'mine' ? 'primary-button' : 'secondary-button' }}"
                >
                    @lang('topweb_chat::app.queues.mine')
                </a>

                <a
                    href="{{ route('admin.topweb_chat.index', ['queue' => 'unassigned']) }}"
                    class="{{ $queue === 'unassigned' ? 'primary-button' : 'secondary-button' }}"
                >
                    @lang('topweb_chat::app.queues.unassigned')
                </a>

                @if (auth()->guard('user')->user()->role?->permission_type === 'all')
                    <a
                        href="{{ route('admin.topweb_chat.index', ['queue' => 'all']) }}"
                        class="{{ $queue === 'all' ? 'primary-button' : 'secondary-button' }}"
                    >
                        @lang('topweb_chat::app.queues.all')
                    </a>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            @forelse ($conversations as $conversation)
                @php
                    $sensitiveData = app(\App\Services\SensitiveDataService::class);
                    $remoteId = $sensitiveData->canView()
                        ? $conversation->remote_jid
                        : $sensitiveData->maskPhone($conversation->remote_jid);
                @endphp

                <a
                    href="{{ route('admin.topweb_chat.show', $conversation) }}"
                    class="grid gap-2 border-b border-gray-200 p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950 md:grid-cols-[1fr_auto]"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate font-semibold text-gray-800 dark:text-white">
                                {{ $conversation->person?->name ?? trans('topweb_chat::app.contacts.unknown') }}
                            </p>

                            @if ($conversation->unread_count)
                                <span class="label-active">{{ $conversation->unread_count }}</span>
                            @endif
                        </div>

                        <p class="truncate text-sm text-gray-600 dark:text-gray-300">{{ $remoteId }}</p>

                        @if ($conversation->lead)
                            <p class="truncate text-xs text-gray-500">{{ $conversation->lead->title }}</p>
                        @endif
                    </div>

                    <div class="text-sm text-gray-500 md:text-right">
                        <p>{{ $conversation->assignedUser?->name ?? trans('topweb_chat::app.conversations.unassigned') }}</p>
                        <p>{{ $conversation->last_message_at?->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <div class="p-8 text-center text-gray-600 dark:text-gray-300">
                    @lang('topweb_chat::app.conversations.empty')
                </div>
            @endforelse
        </div>

        {{ $conversations->links() }}
    </div>
</x-admin::layouts>
