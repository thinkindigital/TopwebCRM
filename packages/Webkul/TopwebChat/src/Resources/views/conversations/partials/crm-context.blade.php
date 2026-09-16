{{-- E-02 C4: painel CRM (info + stage + assignment + notas + next-action + recentes). --}}
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
                        @if ($conversation->lead && bouncer()->hasPermission('topweb_chat.inbox.activities'))
                            <div class="mt-2 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('admin.topweb_chat.activities.complete', [$conversation, $nextAction['id']]) }}" data-activity-form>
                                    @csrf
                                    <button class="secondary-button">@lang('topweb_chat::app.activities.complete_action')</button>
                                </form>
                                <details>
                                    <summary class="secondary-button cursor-pointer">@lang('topweb_chat::app.activities.reschedule_action')</summary>
                                    <form method="POST" action="{{ route('admin.topweb_chat.activities.update', [$conversation, $nextAction['id']]) }}" class="mt-2 grid gap-2" data-activity-form>
                                        @csrf
                                        @method('PUT')
                                        <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                            @lang('topweb_chat::app.activities.schedule_from')
                                            <input type="datetime-local" name="schedule_from" required class="mt-1 w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                                        </label>
                                        <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                            @lang('topweb_chat::app.activities.schedule_to')
                                            <input type="datetime-local" name="schedule_to" required class="mt-1 w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                                        </label>
                                        <button class="secondary-button">@lang('topweb_chat::app.activities.save')</button>
                                    </form>
                                </details>
                            </div>
                        @endif
                    @else
                        <p class="mt-1 text-gray-500">@lang('topweb_chat::app.next_action.none')</p>
                    @endif
                    @if ($conversation->lead)
                        <a href="{{ route('admin.leads.view', $conversation->lead) }}" class="mt-1 inline-block text-xs font-medium text-brandColor hover:underline">
                            @lang('topweb_chat::app.next_action.manage')
                        </a>
                    @endif
                    @if ($conversation->lead && bouncer()->hasPermission('topweb_chat.inbox.activities'))
                        <details class="mt-2" data-activity-create>
                            <summary class="primary-button cursor-pointer">@lang('topweb_chat::app.activities.create_action')</summary>
                            <form method="POST" action="{{ route('admin.topweb_chat.activities.store', $conversation) }}" class="mt-2 grid gap-2" data-activity-form>
                                @csrf
                                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                    @lang('topweb_chat::app.activities.type')
                                    <select name="type" required class="custom-select mt-1 w-full">
                                        @foreach (\Webkul\TopwebChat\Services\ActivityActionabilityPolicy::ACTIONABLE_TYPES as $actionableType)
                                            <option value="{{ $actionableType }}">{{ trans('topweb_chat::app.next_action.kind_'.\Webkul\TopwebChat\Services\NextActionService::kindOf(new \Webkul\Activity\Models\Activity(['type' => $actionableType]))) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                    @lang('topweb_chat::app.activities.schedule_from')
                                    <input type="datetime-local" name="schedule_from" required class="mt-1 w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                                </label>
                                <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                    @lang('topweb_chat::app.activities.schedule_to')
                                    <input type="datetime-local" name="schedule_to" required class="mt-1 w-full rounded-lg border border-gray-300 bg-white p-2 text-sm dark:border-gray-800 dark:bg-gray-950">
                                </label>
                                @if ($isAdmin ?? false)
                                    <label class="text-xs font-medium text-gray-700 dark:text-gray-200">
                                        @lang('topweb_chat::app.assignment.title')
                                        <select name="user_id" class="custom-select mt-1 w-full">
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
                        data-stage-form
                        data-stages-url-template="{{ route('admin.topweb_chat.lead_stage.stages', [$conversation, '__PIPELINE__']) }}"
                    >
                        @csrf
                        @method('PUT')

                        <label class="text-sm font-medium text-gray-800 dark:text-white" for="lead_pipeline_id">
                            @lang('topweb_chat::app.leads.pipeline')
                        </label>

                        <select id="lead_pipeline_id" name="lead_pipeline_id" class="custom-select" data-pipeline-select required>
                            @foreach ($leadPipelines as $availablePipeline)
                                <option value="{{ $availablePipeline->id }}" @selected($conversation->lead->lead_pipeline_id === $availablePipeline->id)>
                                    {{ $availablePipeline->name }}
                                </option>
                            @endforeach
                        </select>

                        <label class="text-sm font-medium text-gray-800 dark:text-white" for="lead_pipeline_stage_id">
                            @lang('topweb_chat::app.leads.stage')
                        </label>

                        <select id="lead_pipeline_stage_id" name="lead_pipeline_stage_id" class="custom-select" data-stage-select required>
                            @foreach ($pipelineStages as $stage)
                                <option value="{{ $stage->id }}" @selected($conversation->lead->lead_pipeline_stage_id === $stage->id)>
                                    {{ $stage->name }}
                                </option>
                            @endforeach
                        </select>

                        <p class="hidden text-xs text-red-700 dark:text-red-400" data-stage-error></p>

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
                        data-note-form
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
                            <article class="rounded-lg bg-amber-50 p-3 text-sm text-gray-800 dark:bg-gray-950 dark:text-gray-200" data-note-id="{{ $note->id }}">
                                <p class="whitespace-pre-wrap break-words">{{ $note->content }}</p>
                                <p class="mt-2 text-xs text-gray-500">
                                    {{ $note->user?->name }} · {{ $note->created_at?->format('d/m/Y H:i') }}
                                </p>
                                @if (bouncer()->hasPermission('topweb_chat.inbox.notes.delete'))
                                    <button
                                        type="button"
                                        class="mt-2 text-xs font-medium text-red-700 underline dark:text-red-400"
                                        data-note-delete
                                        data-note-id="{{ $note->id }}"
                                        data-delete-url="{{ route('admin.topweb_chat.notes.destroy', [$conversation, $note]) }}"
                                        data-confirm-message="{{ trans('topweb_chat::app.notes.delete_confirm') }}"
                                    >
                                        @lang('topweb_chat::app.notes.delete')
                                    </button>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        </aside>
