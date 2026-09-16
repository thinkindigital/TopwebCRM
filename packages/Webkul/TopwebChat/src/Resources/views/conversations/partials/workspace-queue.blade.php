<div class="twp-queue-head">
    <p class="twp-eyebrow">@lang('topweb_chat::app.conversations.description')</p>
    <h1 class="twp-title">@lang('topweb_chat::app.menu.title')</h1>

    @if ($queueAvailable)
        <nav class="twp-tabs" aria-label="@lang('topweb_chat::app.menu.title')">
            <a href="{{ route('admin.topweb_chat.index', ['queue' => 'mine']) }}" class="twp-tab" @if ($queue === 'mine') aria-current="page" @endif>
                <span>@lang('topweb_chat::app.queues.mine')</span>
                <strong class="topweb-chat-queue-count">{{ $queueCounts['mine'] ?? 0 }}</strong>
            </a>
            <a href="{{ route('admin.topweb_chat.index', ['queue' => 'unassigned']) }}" class="twp-tab" @if ($queue === 'unassigned') aria-current="page" @endif>
                <span>@lang('topweb_chat::app.queues.unassigned')</span>
                <strong class="topweb-chat-queue-count">{{ $queueCounts['unassigned'] ?? 0 }}</strong>
            </a>
            @if ($isAdmin)
                <a href="{{ route('admin.topweb_chat.index', ['queue' => 'all']) }}" class="twp-tab" @if ($queue === 'all') aria-current="page" @endif>
                    <span>@lang('topweb_chat::app.queues.all')</span>
                    <strong class="topweb-chat-queue-count">{{ $queueCounts['all'] ?? 0 }}</strong>
                </a>
            @endif
        </nav>

        <div class="twp-search" data-topwebchat-search>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
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
                role="combobox"
                aria-expanded="false"
                aria-controls="topwebchat-search-results"
                aria-autocomplete="list"
            >
            <div id="topwebchat-search-results" role="listbox" aria-label="@lang('topweb_chat::app.search.label')"></div>
        </div>
    @endif
</div>

<div class="twp-list">
    @if ($queueAvailable)
        @php
            $blindQueue = $queue === 'unassigned' && ! $isAdmin;
        @endphp
        @forelse ($queueConversations as $queueConversation)
            @if ($blindQueue)
                <div class="twp-row topweb-chat-blind-item">
                    <div class="twp-row-top">
                        <p class="twp-row-name">@lang('topweb_chat::app.queues.unassigned')</p>
                        <span class="twp-time">{{ $queueConversation->last_message_at?->diffForHumans() }}</span>
                    </div>
                    @if (bouncer()->hasPermission('topweb_chat.inbox.assign'))
                        <form method="POST" action="{{ route('admin.topweb_chat.assignment.update', $queueConversation) }}" class="twp-claim">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="assigned_user_id" value="{{ $user->id }}">
                            <button type="submit" class="primary-button">@lang('topweb_chat::app.assignment.claim')</button>
                        </form>
                    @endif
                </div>
            @else
                @php
                    $queueRemoteId = $sensitiveData->canView()
                        ? $queueConversation->remote_jid
                        : $sensitiveData->maskPhone($queueConversation->remote_jid);
                    $actionFlag = $queueConversation->lead_id
                        ? ($nextActionFlags[$queueConversation->lead_id] ?? null)
                        : null;
                @endphp
                <a
                    href="{{ route('admin.topweb_chat.show', ['conversation' => $queueConversation, 'queue' => $queue]) }}"
                    class="twp-row {{ $selectedConversation?->is($queueConversation) ? 'is-active' : '' }}"
                >
                    <div class="twp-row-top">
                        <p class="twp-row-name">{{ $queueConversation->person?->name ?? trans('topweb_chat::app.contacts.unknown') }}</p>
                        <span class="twp-time">{{ $queueConversation->last_message_at?->diffForHumans() }}</span>
                        @if ($queueConversation->unread_count)
                            <span class="twp-badge">{{ $queueConversation->unread_count }}</span>
                        @endif
                    </div>
                    <p class="twp-preview">{{ $queueRemoteId }}</p>
                    <div class="twp-row-meta">
                        <span>{{ $queueConversation->lead?->title ?? trans('topweb_chat::app.conversations.unassigned') }}</span>
                        <span>{{ $queueConversation->assignedUser?->name ?? trans('topweb_chat::app.conversations.unassigned') }}</span>
                        @if ($actionFlag && ($actionFlag['overdue'] || $actionFlag['today']))
                            <span class="topweb-chat-action-dot twp-action-dot {{ $actionFlag['overdue'] ? 'is-overdue' : '' }}" title="@lang($actionFlag['overdue'] ? 'topweb_chat::app.next_action.overdue_dot' : 'topweb_chat::app.next_action.today_dot')"></span>
                        @endif
                    </div>
                </a>
            @endif
        @empty
            <p class="twp-queue-empty">@lang('topweb_chat::app.conversations.empty')</p>
        @endforelse
    @endif
</div>

@if ($queueAvailable && method_exists($queueConversations, 'links'))
    <div class="twp-pagination">{{ $queueConversations->links() }}</div>
@endif
