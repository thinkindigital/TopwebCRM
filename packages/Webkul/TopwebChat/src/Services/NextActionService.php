<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Webkul\Activity\Models\Activity;
use Webkul\User\Models\User;

/**
 * V-04: próxima ação comercial a partir de Activities reais.
 *
 * Regra final (§20a da spec): candidatas = do Lead autorizado AND is_done=false
 * AND ACTIONABLE AND sem vínculo com topweb_chat_attendances AND não
 * sistêmicas/automáticas. RECORD (inclui note) e SYSTEM nunca são candidatas.
 * A elegibilidade vive aqui, centralizada — futura casa: domínio Activity
 * (ActivityActionabilityPolicy). Exibição: SAFE ENVELOPE (§12), nunca strings
 * livres sem concessão.
 */
class NextActionService
{
    public const SYSTEM_TYPES = ['system'];

    public const RECORD_TYPES = ['note'];

    public const ACTIONABLE_KINDS = [
        'call' => 'CALL',
        'meeting' => 'MEETING',
        'visit' => 'VISIT',
        'follow-up' => 'FOLLOW_UP',
        'followup' => 'FOLLOW_UP',
        'task' => 'TASK',
    ];

    public static function isActionable(Activity $activity): bool
    {
        if ($activity->is_done) {
            return false;
        }

        $type = mb_strtolower((string) $activity->type);

        return ! in_array($type, self::SYSTEM_TYPES, true)
            && ! in_array($type, self::RECORD_TYPES, true);
    }

    public static function kindOf(Activity $activity): string
    {
        return self::ACTIONABLE_KINDS[mb_strtolower((string) $activity->type)] ?? 'OTHER';
    }

    /**
     * @return Collection<int, Activity>
     */
    public function candidatesForLead(int $leadId): Collection
    {
        $today = Carbon::today()->toDateString();

        return Activity::query()
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $leadId)
            ->where('activities.is_done', false)
            ->whereNotIn('activities.type', array_merge(self::SYSTEM_TYPES, self::RECORD_TYPES))
            ->whereNotIn('activities.id', fn ($query) => $query
                ->select('activity_id')->from('topweb_chat_attendances'))
            ->orderByRaw(
                'CASE WHEN activities.schedule_from IS NULL THEN 3 '
                .'WHEN DATE(activities.schedule_from) < ? THEN 0 '
                .'WHEN DATE(activities.schedule_from) = ? THEN 1 '
                .'ELSE 2 END, activities.schedule_from ASC',
                [$today, $today]
            )
            ->select('activities.*')
            ->with('user')
            ->get();
    }

    public function nextForLead(?int $leadId): ?Activity
    {
        if (! $leadId) {
            return null;
        }

        return $this->candidatesForLead($leadId)->first();
    }

    /**
     * @return array<int, array{kind: string, when: string, status: string, owner: ?string}>
     */
    public function recentEnvelopes(?int $leadId, User $viewer, bool $isAdmin, int $limit = 3): array
    {
        if (! $leadId) {
            return [];
        }

        return Activity::query()
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->where('lead_activities.lead_id', $leadId)
            ->whereNotIn('activities.type', self::SYSTEM_TYPES)
            ->orderByDesc('activities.updated_at')
            ->limit($limit)
            ->select('activities.*')
            ->with('user')
            ->get()
            ->map(fn (Activity $activity) => $this->envelope($activity, $viewer, $isAdmin))
            ->filter()
            ->values()
            ->all();
    }
    public function flagsForLeadIds(array $leadIds): array
    {
        $leadIds = array_values(array_unique(array_filter($leadIds)));

        if ($leadIds === []) {
            return [];
        }

        $today = Carbon::today()->toDateString();
        $flags = array_fill_keys($leadIds, ['overdue' => false, 'today' => false]);

        $rows = Activity::query()
            ->join('lead_activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->whereIn('lead_activities.lead_id', $leadIds)
            ->where('activities.is_done', false)
            ->whereNotIn('activities.type', array_merge(self::SYSTEM_TYPES, self::RECORD_TYPES))
            ->whereNotIn('activities.id', fn ($query) => $query
                ->select('activity_id')->from('topweb_chat_attendances'))
            ->whereNotNull('activities.schedule_from')
            ->selectRaw('lead_activities.lead_id, DATE(activities.schedule_from) as day')
            ->get();

        foreach ($rows as $row) {
            if ($row->day < $today) {
                $flags[$row->lead_id]['overdue'] = true;
            } elseif ($row->day === $today) {
                $flags[$row->lead_id]['today'] = true;
            }
        }

        return $flags;
    }

    /**
     * @return array{kind: string, when: string, status: string, owner: ?string}|null
     */
    public function envelope(?Activity $activity, User $viewer, bool $isAdmin): ?array
    {
        if (! $activity) {
            return null;
        }

        $at = $activity->schedule_from ? Carbon::parse($activity->schedule_from) : null;
        $today = Carbon::today();

        if (! $at) {
            $status = 'unscheduled';
            $when = '';
        } elseif ($at->toDateString() === $today->toDateString()) {
            $status = 'today';
            $when = 'Hoje · '.$at->format('H:i');
        } elseif ($at->toDateString() < $today->toDateString()) {
            $status = 'overdue';
            $when = $at->format('d/m · H:i');
        } else {
            $status = 'future';
            $when = $at->format('d/m · H:i');
        }

        $ownerId = $activity->user_id;

        return [
            'kind' => self::kindOf($activity),
            'when' => $when,
            'status' => $status,
            'owner' => ($ownerId && ($isAdmin || (int) $ownerId === (int) $viewer->id))
                ? (string) ($activity->user?->name ?? '')
                : null,
        ];
    }
}
