<x-admin::layouts>
    <x-slot:title>
        @lang('topweb_chat::app.settings.title')
    </x-slot>

    <div class="grid gap-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.title')</h1>
            <p class="text-sm text-gray-600 dark:text-gray-300">@lang('topweb_chat::app.settings.description')</p>
        </div>

        @include('topweb_chat::settings.openwa-sessions')

        <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.instance_title')</h2>

            <form
                method="POST"
                action="{{ route('admin.topweb_chat.settings.instances.store') }}"
                class="mt-4 grid gap-4 lg:grid-cols-2"
            >
                @csrf

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.instance_name')</label>
                    <input
                        name="name"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.session_uuid')</label>
                    <input
                        name="session_uuid"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.base_url')</label>
                    <input
                        name="base_url"
                        type="url"
                        value="{{ old('base_url', config('topweb-chat.base_url')) }}"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                        required
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.instance_token')</label>
                    <input
                        name="token"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-950 dark:text-white"
                        required
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" checked>
                    @lang('topweb_chat::app.settings.instance_enabled')
                </label>

                <div class="lg:text-right">
                    <button class="primary-button">@lang('topweb_chat::app.settings.save_instance')</button>
                </div>
            </form>

            <p class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                @lang('topweb_chat::app.settings.public_url_help', [
                    'variable' => 'TOPWEB_CHAT_PUBLIC_URL',
                    'url' => config('topweb-chat.public_url') ?: config('app.url'),
                ])
            </p>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="p-3">@lang('topweb_chat::app.settings.name')</th>
                            <th class="p-3">@lang('topweb_chat::app.settings.provider')</th>
                            <th class="p-3">@lang('topweb_chat::app.settings.status')</th>
                            <th class="p-3">@lang('topweb_chat::app.settings.last_sync')</th>
                            <th class="p-3">@lang('topweb_chat::app.settings.enabled')</th>
                            <th class="p-3">@lang('topweb_chat::app.settings.actions')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($instances as $instance)
                            <tr class="border-b border-gray-200 dark:border-gray-800">
                                <td class="p-3 text-gray-800 dark:text-white">{{ $instance->name }}</td>
                                <td class="p-3">{{ $instance->provider }}</td>
                                <td class="p-3">{{ $instance->status }}</td>
                                <td class="p-3">{{ $instance->last_synced_at?->diffForHumans() ?? '—' }}</td>
                                <td class="p-3">{{ $instance->enabled ? trans('topweb_chat::app.common.yes') : trans('topweb_chat::app.common.no') }}</td>
                                <td class="p-3">
                                    <div class="flex flex-wrap gap-2">
                                        <form method="POST" action="{{ route('admin.topweb_chat.settings.instances.reconcile', $instance) }}">
                                            @csrf
                                            <button class="secondary-button">@lang('topweb_chat::app.settings.reconcile')</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.topweb_chat.settings.instances.webhook', $instance) }}">
                                            @csrf
                                            <button class="secondary-button">@lang('topweb_chat::app.settings.configure_webhook')</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.sensitive_title')</h2>
            <p class="text-sm text-gray-600 dark:text-gray-300">@lang('topweb_chat::app.settings.sensitive_description')</p>

            <div class="mt-4 grid gap-3">
                @foreach ($users as $managedUser)
                    <form
                        method="POST"
                        action="{{ route('admin.topweb_chat.settings.sensitive_access.update') }}"
                        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="user_id" value="{{ $managedUser->id }}">
                        <input type="hidden" name="can_view_sensitive_data" value="{{ $managedUser->can_view_sensitive_data ? 0 : 1 }}">

                        <div>
                            <p class="font-medium text-gray-800 dark:text-white">{{ $managedUser->name }}</p>
                            <p class="text-xs text-gray-500">{{ $managedUser->email }} · {{ $managedUser->role?->name }}</p>
                        </div>

                        <button class="{{ $managedUser->can_view_sensitive_data ? 'secondary-button' : 'primary-button' }}">
                            {{ $managedUser->can_view_sensitive_data
                                ? trans('topweb_chat::app.settings.revoke_sensitive')
                                : trans('topweb_chat::app.settings.allow_sensitive') }}
                        </button>
                    </form>
                @endforeach
            </div>
        </section>
    </div>
</x-admin::layouts>
