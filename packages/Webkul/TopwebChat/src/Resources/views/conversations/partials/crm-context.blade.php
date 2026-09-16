{{-- E-02 C4: contexto comercial com a mesma fonte de dados do show e do fragmento. --}}
<aside class="twp-context-stack">
    @if ($conversation->lead)
        <section class="twp-context-section twp-context-lead">
            <p class="twp-context-overline">{{ $conversation->lead->pipeline?->name ?? trans('topweb_chat::app.crm.title') }}</p>
            <h2 class="twp-context-lead-title">{{ $conversation->lead->title }}</h2>

            @if (bouncer()->hasPermission('topweb_chat.inbox.stage') && bouncer()->hasPermission('leads.edit'))
                <form
                    method="POST"
                    action="{{ route('admin.topweb_chat.lead_stage.update', $conversation) }}"
                    class="twp-stage-form"
                    data-stage-form
                    data-confirmed-pipeline="{{ $conversation->lead->lead_pipeline_id }}"
                    data-confirmed-stage="{{ $conversation->lead->lead_pipeline_stage_id }}"
                    data-stages-url-template="{{ route('admin.topweb_chat.lead_stage.stages', [$conversation, '__PIPELINE__']) }}"
                >
                    @csrf
                    @method('PUT')

                    <label class="twp-field-label" for="lead_pipeline_id">@lang('topweb_chat::app.leads.pipeline')</label>
                    <select id="lead_pipeline_id" name="lead_pipeline_id" class="custom-select twp-context-select" data-pipeline-select required>
                        @foreach ($leadPipelines as $availablePipeline)
                            <option value="{{ $availablePipeline->id }}" @selected($conversation->lead->lead_pipeline_id === $availablePipeline->id)>
                                {{ $availablePipeline->name }}
                            </option>
                        @endforeach
                    </select>

                    <label class="twp-field-label" for="lead_pipeline_stage_id">@lang('topweb_chat::app.leads.stage')</label>
                    <select id="lead_pipeline_stage_id" name="lead_pipeline_stage_id" class="custom-select twp-context-select" data-stage-select required>
                        @foreach ($pipelineStages as $stage)
                            <option value="{{ $stage->id }}" @selected($conversation->lead->lead_pipeline_stage_id === $stage->id)>
                                {{ $stage->name }}
                            </option>
                        @endforeach
                    </select>

                    <p class="hidden twp-form-error" data-stage-error></p>
                    <button class="secondary-button twp-context-action">@lang('topweb_chat::app.leads.update_stage')</button>
                </form>
            @endif

            <div class="twp-context-owner">
                <p class="twp-field-label">@lang('topweb_chat::app.assignment.title')</p>
                @if ($canTransferLead)
                    <form method="POST" action="{{ route('admin.topweb_chat.assignment.update', $conversation) }}" class="twp-owner-form" data-owner-transfer-form>
                        @csrf
                        @method('PUT')
                        <label class="sr-only" for="topweb-chat-owner">@lang('topweb_chat::app.assignment.title')</label>
                        <select id="topweb-chat-owner" name="assigned_user_id" class="custom-select twp-context-select" required>
                            @foreach ($assignableUsers as $assignableUser)
                                <option value="{{ $assignableUser->id }}" @selected($conversation->lead->user_id === $assignableUser->id)>
                                    {{ $assignableUser->name }}
                                </option>
                            @endforeach
                        </select>
                        <button class="primary-button twp-context-action">@lang('topweb_chat::app.assignment.save')</button>
                    </form>
                @else
                    <p class="twp-context-value">{{ $conversation->assignedUser?->name ?? trans('topweb_chat::app.conversations.unassigned') }}</p>
                @endif
            </div>

            @if ($conversation->lead)
                <a href="{{ route('admin.leads.view', $conversation->lead) }}" class="twp-context-link">
                    @lang('topweb_chat::app.next_action.manage')
                </a>
            @endif
        </section>
    @else
        <section class="twp-context-section">
            <h2 class="twp-context-heading">@lang('topweb_chat::app.crm.title')</h2>
            <dl class="twp-context-facts">
                <div><dt>@lang('topweb_chat::app.crm.person')</dt><dd>{{ $conversation->person?->name ?? trans('topweb_chat::app.crm.not_linked') }}</dd></div>
                <div><dt>@lang('topweb_chat::app.crm.lead')</dt><dd>@lang('topweb_chat::app.crm.not_linked')</dd></div>
                @if (bouncer()->hasPermission('topweb_chat.settings.index'))
                    <div><dt>@lang('topweb_chat::app.crm.instance')</dt><dd>{{ $conversation->instance?->name }}</dd></div>
                @endif
            </dl>
        </section>
    @endif

    @if ($conversation->lead)
        <section class="twp-context-section">
            <h2 class="twp-context-heading">@lang('topweb_chat::app.next_action.title')</h2>
            <div class="twp-next-action" data-next-action-card @if ($nextAction) data-next-action-id="{{ $nextAction['id'] }}" @endif>
                @if ($nextAction)
                    <p class="twp-next-action-title">
                        {{ trans('topweb_chat::app.next_action.kind_'.$nextAction['kind']) }}
                        @if ($nextAction['when']) · {{ $nextAction['when'] }}@endif
                    </p>
                    <p class="twp-context-meta">
                        {{ $nextAction['status'] === 'overdue' ? trans('topweb_chat::app.next_action.overdue_dot') : trans('topweb_chat::app.next_action.recent') }}
                        @if ($nextAction['owner']) · {{ $nextAction['owner'] }}@endif
                    </p>
                    <div class="twp-inline-actions">
                        @if (bouncer()->hasPermission('topweb_chat.inbox.activities'))
                            <form method="POST" action="{{ route('admin.topweb_chat.activities.complete', [$conversation, $nextAction['id']]) }}" data-activity-form>
                                @csrf
                                <button class="secondary-button">@lang('topweb_chat::app.activities.complete_action')</button>
                            </form>
                            <details class="twp-inline-details">
                                <summary class="secondary-button cursor-pointer">@lang('topweb_chat::app.activities.reschedule_action')</summary>
                                <form method="POST" action="{{ route('admin.topweb_chat.activities.update', [$conversation, $nextAction['id']]) }}" class="twp-overlay-form" data-activity-form>
                                    @csrf
                                    @method('PUT')
                                    <label class="twp-field-label">@lang('topweb_chat::app.activities.schedule_from')
                                        <input type="datetime-local" name="schedule_from" required>
                                    </label>
                                    <label class="twp-field-label">@lang('topweb_chat::app.activities.schedule_to')
                                        <input type="datetime-local" name="schedule_to" required>
                                    </label>
                                    <button class="secondary-button">@lang('topweb_chat::app.activities.save')</button>
                                </form>
                            </details>
                        @endif
                    </div>
                @else
                    <p class="twp-context-meta">@lang('topweb_chat::app.next_action.none')</p>
                @endif
            </div>

            @if (bouncer()->hasPermission('topweb_chat.inbox.activities'))
                <details class="twp-activity-create" data-activity-create>
                    <summary class="twp-context-link cursor-pointer">+ @lang('topweb_chat::app.activities.create_action')</summary>
                    <form method="POST" action="{{ route('admin.topweb_chat.activities.store', $conversation) }}" class="twp-overlay-form" data-activity-form>
                        @csrf
                        <label class="twp-field-label">@lang('topweb_chat::app.activities.type')
                            <select name="type" required class="custom-select">
                                @foreach (\Webkul\TopwebChat\Services\ActivityActionabilityPolicy::ACTIONABLE_TYPES as $actionableType)
                                    <option value="{{ $actionableType }}">{{ trans('topweb_chat::app.next_action.kind_'.\Webkul\TopwebChat\Services\NextActionService::kindOf(new \Webkul\Activity\Models\Activity(['type' => $actionableType]))) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="twp-field-label">@lang('topweb_chat::app.activities.schedule_from')
                            <input type="datetime-local" name="schedule_from" required>
                        </label>
                        <label class="twp-field-label">@lang('topweb_chat::app.activities.schedule_to')
                            <input type="datetime-local" name="schedule_to" required>
                        </label>
                        @if ($isAdmin ?? false)
                            <label class="twp-field-label">@lang('topweb_chat::app.assignment.title')
                                <select name="user_id" class="custom-select">
                                    @foreach ($assignableUsers as $assignableUser)
                                        <option value="{{ $assignableUser->id }}">{{ $assignableUser->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                        <button class="primary-button">@lang('topweb_chat::app.activities.create_action')</button>
                    </form>
                </details>
            @endif
        </section>
    @endif

    @if ($recentActions !== [])
        <section class="twp-context-section">
            <h2 class="twp-context-heading">@lang('topweb_chat::app.next_action.recent')</h2>
            <ul class="twp-recent-actions">
                @foreach ($recentActions as $recent)
                    <li data-recent-activity-id="{{ $recent['id'] }}">
                        <strong>{{ trans('topweb_chat::app.next_action.kind_'.$recent['kind']) }}</strong>
                        <span>{{ $recent['when'] ?: trans('topweb_chat::app.activities.completed') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (bouncer()->hasPermission('topweb_chat.inbox.assign') && ! $conversation->lead && ($isAdmin || $conversation->assigned_user_id === null))
        <section class="twp-context-section">
            <h2 class="twp-context-heading">@lang('topweb_chat::app.assignment.title')</h2>
            <form method="POST" action="{{ route('admin.topweb_chat.assignment.update', $conversation) }}" class="twp-overlay-form">
                @csrf
                @method('PUT')
                @if ($isAdmin)
                    <select name="assigned_user_id" class="custom-select" required>
                        @foreach ($assignableUsers as $assignableUser)
                            <option value="{{ $assignableUser->id }}" @selected($conversation->assigned_user_id === $assignableUser->id)>{{ $assignableUser->name }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="assigned_user_id" value="{{ $user->id }}">
                @endif
                <button class="primary-button">{{ $isAdmin ? trans('topweb_chat::app.assignment.save') : trans('topweb_chat::app.assignment.claim') }}</button>
            </form>
        </section>
    @endif

    @if (! $conversation->lead && $canReleaseConversation)
        <form method="POST" action="{{ route('admin.topweb_chat.assignment.update', $conversation) }}" class="twp-release-form" onsubmit="return confirm(@json(trans('topweb_chat::app.assignment.release_confirm')));">
            @csrf
            @method('PUT')
            <input type="hidden" name="assigned_user_id" value="">
            <button class="secondary-button twp-context-action">@lang('topweb_chat::app.assignment.release')</button>
        </form>
    @endif

    @if (bouncer()->hasPermission('topweb_chat.inbox.notes'))
        <section class="twp-context-section twp-notes-section">
            <h2 class="twp-context-heading">@lang('topweb_chat::app.notes.title')</h2>
            <p class="twp-context-meta">@lang('topweb_chat::app.notes.description')</p>

            <details class="twp-note-editor" data-note-editor>
                <summary class="twp-context-link cursor-pointer">+ @lang('topweb_chat::app.notes.add')</summary>
                <form method="POST" action="{{ route('admin.topweb_chat.notes.store', $conversation) }}" class="twp-overlay-form" data-note-form>
                    @csrf
                    <label class="sr-only" for="topweb-chat-note-content">@lang('topweb_chat::app.notes.add')</label>
                    <textarea id="topweb-chat-note-content" name="content" rows="3" required></textarea>
                    <button class="primary-button">@lang('topweb_chat::app.notes.add')</button>
                </form>
            </details>

            <div class="twp-note-list">
                @foreach ($conversation->internalNotes as $note)
                    <article class="twp-note-item" data-note-id="{{ $note->id }}">
                        <p class="whitespace-pre-wrap break-words">{{ $note->content }}</p>
                        <p class="twp-context-meta">{{ $note->user?->name }} · {{ $note->created_at?->format('d/m/Y H:i') }}</p>
                        @if (bouncer()->hasPermission('topweb_chat.inbox.notes.delete'))
                            <button type="button" class="twp-context-link twp-note-delete" data-note-delete data-note-id="{{ $note->id }}" data-delete-url="{{ route('admin.topweb_chat.notes.destroy', [$conversation, $note]) }}" data-confirm-message="{{ trans('topweb_chat::app.notes.delete_confirm') }}">
                                @lang('topweb_chat::app.notes.delete')
                            </button>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</aside>
