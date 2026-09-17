<?php

namespace Webkul\TopwebChat\Services;

use App\Services\SensitiveDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\User\Models\User;

/**
 * E14-R2 S3: fronteira do TopwebChat para mutações de Activity.
 *
 * Centraliza conversation access, ownership do Lead, Bouncer, can_view,
 * actionability, vínculos Lead/Person e eventos nativos do Krayin. O browser
 * nunca chama o ActivityController genérico por esta superfície.
 */
class ConversationActivityService
{
    public function __construct(
        protected ConversationAccessService $access,
        protected ActivityRepository $activities,
        protected SensitiveDataService $sensitiveData
    ) {}

    public function create(Conversation $conversation, User $actor, array $data): Activity
    {
        $lead = $conversation->lead;

        abort_unless($lead, 422);
        abort_unless(
            bouncer()->hasPermission('topweb_chat.inbox.activities')
            && bouncer()->hasPermission('activities.create'),
            403
        );
        $this->access->authorizeView($actor, $conversation);
        abort_unless($this->access->canAccessLead($actor, $lead), 403);

        $type = ActivityActionabilityPolicy::normalize((string) ($data['type'] ?? ''));
        abort_unless(ActivityActionabilityPolicy::category($type) === 'actionable', 422);

        $payload = $this->sensitiveData->sanitizeInput('activities', $data, $actor);
        unset($payload['is_done'], $payload['lead_id']);

        $payload['type'] = $type;
        $payload['is_done'] = false;
        $payload['user_id'] = $this->resolveOwner($actor, $data);

        Event::dispatch('activity.create.before');

        $activity = $this->activities->create($payload);
        $activity->leads()->syncWithoutDetaching([$lead->id]);

        if ($conversation->person_id) {
            $activity->persons()->syncWithoutDetaching([$conversation->person_id]);
        }

        Event::dispatch('activity.create.after', $activity);

        Log::info('TopwebChat activity created.', [
            'activity_id' => $activity->id,
            'conversation_id' => $conversation->id,
            'lead_id' => $lead->id,
            'actor_user_id' => $actor->id,
            'owner_user_id' => $activity->user_id,
        ]);

        return $activity;
    }

    public function reschedule(
        Conversation $conversation,
        Activity $activity,
        User $actor,
        array $data
    ): Activity {
        $this->authorizeMutation($conversation, $activity, $actor, 'activities.edit');

        $payload = $this->sensitiveData->sanitizeInput('activities', $data, $actor);
        unset($payload['is_done'], $payload['lead_id'], $payload['type'], $payload['user_id']);

        $old = [$activity->schedule_from, $activity->schedule_to];

        Event::dispatch('activity.update.before', $activity->id);

        $activity = $this->activities->update($payload, $activity->id);

        Event::dispatch('activity.update.after', $activity);

        Log::info('TopwebChat activity rescheduled.', [
            'activity_id' => $activity->id,
            'conversation_id' => $conversation->id,
            'actor_user_id' => $actor->id,
            'old_schedule_from' => (string) $old[0],
            'old_schedule_to' => (string) $old[1],
            'new_schedule_from' => (string) $activity->schedule_from,
            'new_schedule_to' => (string) $activity->schedule_to,
        ]);

        return $activity;
    }

    public function complete(Conversation $conversation, Activity $activity, User $actor): Activity
    {
        $this->authorizeMutation($conversation, $activity, $actor, 'activities.edit');

        Event::dispatch('activity.update.before', $activity->id);

        $activity = $this->activities->update(['is_done' => true], $activity->id);

        Event::dispatch('activity.update.after', $activity);

        Log::info('TopwebChat activity completed.', [
            'activity_id' => $activity->id,
            'conversation_id' => $conversation->id,
            'actor_user_id' => $actor->id,
        ]);

        return $activity;
    }

    private function authorizeMutation(
        Conversation $conversation,
        Activity $activity,
        User $actor,
        string $permission
    ): void {
        $lead = $conversation->lead;

        abort_unless($lead, 422);
        abort_unless(
            bouncer()->hasPermission('topweb_chat.inbox.activities')
            && bouncer()->hasPermission($permission),
            403
        );
        $this->access->authorizeView($actor, $conversation);
        abort_unless($this->access->canAccessLead($actor, $lead), 403);
        abort_unless(
            $activity->leads()->where('leads.id', $lead->id)->exists(),
            404
        );
        abort_unless(
            ActivityActionabilityPolicy::isActionable($activity)
            && ! $this->isAttendance($activity),
            422
        );
    }

    private function isAttendance(Activity $activity): bool
    {
        return DB::table('topweb_chat_attendances')
            ->where('activity_id', $activity->id)
            ->exists();
    }

    private function resolveOwner(User $actor, array $data): int
    {
        if ($this->access->isAdministrator($actor) && isset($data['user_id'])) {
            return (int) $data['user_id'];
        }

        return (int) $actor->id;
    }
}
