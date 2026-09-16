<x-admin::layouts>
    <x-slot:title>
        @lang('topweb_chat::app.menu.title')
    </x-slot>

    @php
        $user = auth()->guard('user')->user();
        $isAdmin = $user->role?->permission_type === 'all';
        $sensitiveData = app(\App\Services\SensitiveDataService::class);
    @endphp

    @include('topweb_chat::conversations.partials.workspace')
</x-admin::layouts>
