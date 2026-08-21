<section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="font-semibold text-gray-800 dark:text-white">@lang('topweb_chat::app.settings.openwa_sessions')</h2>

        @if ($openWaHealth)
            <span class="text-sm text-gray-600 dark:text-gray-300">
                {{ $openWaHealth['status'] }} · OpenWA {{ $openWaHealth['version'] }}
            </span>
        @endif
    </div>

    @if ($openWaUnavailable ?? false)
        <p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
            @lang('topweb_chat::app.settings.openwa_unavailable')
        </p>
    @endif

    <div class="mt-4 grid gap-3">
        @forelse ($openWaSessions as $session)
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                <div>
                    <p class="font-medium text-gray-800 dark:text-white">{{ $session['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $session['id'] }}</p>
                </div>

                <span class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $session['status'] }} · @lang('topweb_chat::app.settings.engine_loaded'): {{ $session['engine_loaded'] ? trans('topweb_chat::app.common.yes') : trans('topweb_chat::app.common.no') }}
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-600 dark:text-gray-300">@lang('topweb_chat::app.settings.no_openwa_sessions')</p>
        @endforelse
    </div>
</section>
