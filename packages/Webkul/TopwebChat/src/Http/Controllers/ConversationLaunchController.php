<?php

namespace Webkul\TopwebChat\Http\Controllers;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\ConversationService;

class ConversationLaunchController
{
    public function __construct(
        protected ConversationService $conversations,
        protected ConversationAccessService $access
    ) {}

    public function fromPerson(Person $person): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.create'), 403);

        $user = auth()->guard('user')->user();

        abort_unless($this->access->canAccessPerson($user, $person), 403);

        return $this->launch($person);
    }

    public function fromLead(Lead $lead): RedirectResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.create'), 403);

        $user = auth()->guard('user')->user();

        abort_unless(
            $lead->person
            && $this->access->canAccessLead($user, $lead)
            && $this->access->canAccessPerson($user, $lead->person),
            403
        );

        return $this->launch($lead->person, $lead);
    }

    private function launch(Person $person, ?Lead $lead = null): RedirectResponse
    {
        try {
            $conversation = $this->conversations->launchForPerson(
                $person,
                $lead,
                auth()->guard('user')->user()
            );

            return redirect()->route('admin.topweb_chat.show', $conversation);
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }
    }
}
