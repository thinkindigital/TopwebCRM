<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\TopwebChat\Models\MediaProjection;
use Webkul\User\Models\User;

class MediaProjectionAccessService
{
    public function __construct(protected ConversationAccessService $access) {}

    public function authorize(User $user, ActivityFile $file): void
    {
        if (! $this->canAccess($user, $file)) {
            throw new AuthorizationException;
        }
    }

    public function canAccess(?User $user, ActivityFile $file): bool
    {
        $projection = MediaProjection::query()
            ->where('activity_file_id', $file->id)
            ->first();

        if (! $projection) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($this->access->isAdministrator($user)) {
            return true;
        }

        $lead = $projection->lead()->first();

        if ($lead && $this->access->canAccessLead($user, $lead)) {
            return true;
        }

        $person = $projection->person()->first();

        if ($person && $this->access->canAccessPerson($user, $person)) {
            return true;
        }

        return false;
    }
}
