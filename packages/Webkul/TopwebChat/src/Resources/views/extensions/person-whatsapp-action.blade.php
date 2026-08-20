@if (bouncer()->hasPermission('topweb_chat.inbox.create'))
    <form method="POST" action="{{ route('admin.topweb_chat.start.person', $person) }}">
        @csrf

        <button
            type="submit"
            class="secondary-button flex items-center gap-2"
            title="@lang('topweb_chat::app.actions.send_whatsapp')"
        >
            <svg aria-hidden="true" class="h-5 w-5 fill-current" viewBox="0 0 32 32">
                <path d="M19.1 17.3c-.3-.2-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-.9 1.2-.2.2-.3.2-.7.1-1.9-.9-3.2-1.7-4.5-3.9-.3-.5.3-.5.9-1.7.1-.2.1-.4 0-.6-.1-.2-.7-1.7-1-2.3-.3-.7-.6-.6-.8-.6h-.7c-.3 0-.7.1-1 .5-.3.4-1.3 1.3-1.3 3.2s1.4 3.7 1.6 4c.2.3 2.7 4.2 6.7 5.7 2.5 1.1 3.5 1.2 4.8 1 .8-.1 2.5-1 2.8-2 .4-1 .4-1.9.3-2.1-.1-.2-.4-.3-.7-.5z"/>
                <path d="M27.2 4.6A15.4 15.4 0 0 0 3 23.2L.8 31l8-2.1A15.4 15.4 0 0 0 27.2 4.6zm-11 22.9c-2.5 0-4.9-.7-7-2l-.5-.3-4.7 1.2 1.3-4.6-.3-.5A12.5 12.5 0 1 1 16.2 27.5z"/>
            </svg>

            <span>@lang('topweb_chat::app.actions.send_whatsapp')</span>
        </button>
    </form>
@endif
