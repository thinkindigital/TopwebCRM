<x-admin::layouts>
    <x-slot:title>
        {{ $conversation->person?->name ?? trans('topweb_chat::app.menu.title') }}
    </x-slot>

    @php
        $user = auth()->guard('user')->user();
        $isAdmin = $user->role?->permission_type === 'all';
        $canReleaseConversation = $conversation->assigned_user_id
            && ($isAdmin || $conversation->assigned_user_id === $user->id);
        $sensitiveData = app(\App\Services\SensitiveDataService::class);
        $remoteId = $sensitiveData->canView()
            ? $conversation->remote_jid
            : $sensitiveData->maskPhone($conversation->remote_jid);
    @endphp

    @include('topweb_chat::conversations.partials.workspace')

    @include('topweb_chat::conversations.partials.chat-runtime', [
        'conversation' => $conversation,
    ])
</x-admin::layouts>
