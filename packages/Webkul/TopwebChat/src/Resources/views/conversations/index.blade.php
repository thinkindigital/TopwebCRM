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
                    <span class="topweb-chat-queue-count">{{ $queueCounts['mine'] ?? 0 }}</span>
                </a>

                <a
                    href="{{ route('admin.topweb_chat.index', ['queue' => 'unassigned']) }}"
                    class="{{ $queue === 'unassigned' ? 'primary-button' : 'secondary-button' }}"
                >
                    @lang('topweb_chat::app.queues.unassigned')
                    <span class="topweb-chat-queue-count">{{ $queueCounts['unassigned'] ?? 0 }}</span>
                </a>

                @if (auth()->guard('user')->user()->role?->permission_type === 'all')
                    <a
                        href="{{ route('admin.topweb_chat.index', ['queue' => 'all']) }}"
                        class="{{ $queue === 'all' ? 'primary-button' : 'secondary-button' }}"
                    >
                        @lang('topweb_chat::app.queues.all')
                        <span class="topweb-chat-queue-count">{{ $queueCounts['all'] ?? 0 }}</span>
                    </a>
                @endif
            </div>

            {{-- V-06: busca segura — carteira primeiro; sem identidade fora do escopo. --}}
            <div class="relative w-full md:w-72" data-topwebchat-search>
                <label for="topwebchat-search-input" class="sr-only">@lang('topweb_chat::app.search.label')</label>
                <input
                    id="topwebchat-search-input"
                    type="search"
                    autocomplete="off"
                    minlength="2"
                    placeholder="@lang('topweb_chat::app.search.placeholder')"
                    title="@lang('topweb_chat::app.search.hint')"
                    data-search-url="{{ route('admin.topweb_chat.search') }}"
                    data-search-empty="@lang('topweb_chat::app.search.empty')"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                    role="combobox"
                    aria-expanded="false"
                    aria-controls="topwebchat-search-results"
                    aria-autocomplete="list"
                >
                <div
                    id="topwebchat-search-results"
                    role="listbox"
                    aria-label="@lang('topweb_chat::app.search.label')"
                    class="absolute z-[1001] mt-1 hidden w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                ></div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            @php
                $user = auth()->guard('user')->user();
                $isAdmin = $user->role?->permission_type === 'all';
                $blindQueue = $queue === 'unassigned' && ! $isAdmin;
                $canClaimBlindQueue = bouncer()->hasPermission('topweb_chat.inbox.view');
            @endphp
            @forelse ($conversations as $conversation)
                @if ($blindQueue)
                    {{-- V-01/A3: item cego — sem identidade, sem preview, sem link para a conversa. --}}
                    <div class="topweb-chat-blind-item grid gap-2 border-b border-gray-200 p-4 dark:border-gray-800 md:grid-cols-[1fr_auto]">
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 dark:text-white">
                                @lang('topweb_chat::app.queues.unassigned')
                            </p>
                            <p class="text-sm text-gray-500">{{ $conversation->last_message_at?->diffForHumans() }}</p>
                        </div>

                        @if ($canClaimBlindQueue)
                            <form
                                method="POST"
                                action="{{ route('admin.topweb_chat.assignment.claim', $conversation) }}"
                                class="md:text-right"
                            >
                                @csrf
                                @method('PUT')
                                <button type="submit" class="primary-button">
                                    @lang('topweb_chat::app.assignment.claim')
                                </button>
                            </form>
                        @endif
                    </div>
                @else
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
                            @php
                                $actionFlag = $conversation->lead_id
                                    ? ($nextActionFlags[$conversation->lead_id] ?? null)
                                    : null;
                            @endphp
                            @if ($actionFlag && ($actionFlag['overdue'] || $actionFlag['today']))
                                <span
                                    class="topweb-chat-action-dot inline-block h-2.5 w-2.5 rounded-full {{ $actionFlag['overdue'] ? 'bg-red-600' : 'bg-amber-500' }}"
                                    title="@lang($actionFlag['overdue'] ? 'topweb_chat::app.next_action.overdue_dot' : 'topweb_chat::app.next_action.today_dot')"
                                    aria-label="@lang($actionFlag['overdue'] ? 'topweb_chat::app.next_action.overdue_dot' : 'topweb_chat::app.next_action.today_dot')"
                                ></span>
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
                @endif
            @empty
                <div class="p-8 text-center text-gray-600 dark:text-gray-300">
                    @lang('topweb_chat::app.conversations.empty')
                </div>
            @endforelse
        </div>

        {{ $conversations->links() }}
    </div>

<script src="{{ asset('js/topwebchat-search.js') }}" defer></script>
</x-admin::layouts>
