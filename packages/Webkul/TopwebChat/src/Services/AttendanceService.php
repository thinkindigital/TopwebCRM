<?php

namespace Webkul\TopwebChat\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Webkul\Activity\Models\Activity;
use Webkul\TopwebChat\Models\Attendance;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;

class AttendanceService
{
    private const TECHNICAL_TYPES = [
        'ack',
        'edited',
        'reaction',
        'revoked',
        'status',
        'technical',
    ];

    public function recordHumanOutbound(Message $message): ?Attendance
    {
        if (! $this->isHumanOutbound($message)) {
            return null;
        }

        return $this->record($message, true);
    }

    public function recordRealMessage(Message $message): ?Attendance
    {
        if (! $this->isRealMessage($message)) {
            return null;
        }

        return $this->record($message, false);
    }

    public function closeStale(): int
    {
        $threshold = now()->subMinutes($this->inactivityMinutes());
        $ids = Attendance::query()
            ->whereNull('closed_at')
            ->where('last_real_message_at', '<=', $threshold)
            ->orderBy('last_real_message_at')
            ->limit((int) config('topweb-chat.attendance.close_batch_size', 100))
            ->pluck('id');
        $closed = 0;

        foreach ($ids as $id) {
            $closed += DB::transaction(function () use ($id, $threshold) {
                $attendance = Attendance::query()
                    ->lockForUpdate()
                    ->find($id);

                if (
                    ! $attendance
                    || $attendance->closed_at
                    || $attendance->last_real_message_at->greaterThan($threshold)
                ) {
                    return 0;
                }

                $closedAt = $attendance->last_real_message_at
                    ->copy()
                    ->addMinutes($this->inactivityMinutes());
                $attendance->update(['closed_at' => $closedAt]);

                $activity = $attendance->activity()->lockForUpdate()->first();

                if ($activity) {
                    $updates = [
                        'is_done' => true,
                        'schedule_to' => $closedAt,
                    ];

                    if (blank($activity->comment)) {
                        $updates['comment'] = trans(
                            'topweb_chat::app.attendance.closed_automatically'
                        );
                    }

                    $activity->update($updates);
                }

                $conversation = $attendance->conversation()
                    ->lockForUpdate()
                    ->first();

                if ($conversation) {
                    $hasNewerOpenAttendance = Attendance::query()
                        ->where('conversation_id', $conversation->id)
                        ->whereNull('closed_at')
                        ->where('id', '!=', $attendance->id)
                        ->exists();

                    if (! $hasNewerOpenAttendance) {
                        $conversation->update([
                            'status' => 'closed',
                            'closed_at' => $closedAt,
                        ]);
                    }
                }

                return 1;
            }, 3);
        }

        return $closed;
    }

    public function syncConversationAssociations(Conversation $conversation): int
    {
        if (! $conversation->lead_id && ! $conversation->person_id) {
            return 0;
        }

        $synced = 0;

        $conversation->attendances()
            ->with('activity')
            ->each(function (Attendance $attendance) use ($conversation, &$synced) {
                if (! $attendance->activity) {
                    return;
                }

                if ($conversation->lead_id) {
                    $attendance->activity->leads()
                        ->syncWithoutDetaching([$conversation->lead_id]);
                }

                if ($conversation->person_id) {
                    $attendance->activity->persons()
                        ->syncWithoutDetaching([$conversation->person_id]);
                }

                $synced++;
            });

        return $synced;
    }

    private function record(Message $message, bool $mayOpen): ?Attendance
    {
        return DB::transaction(function () use ($message, $mayOpen) {
            $lockedMessage = Message::query()
                ->lockForUpdate()
                ->findOrFail($message->id);
            $conversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($lockedMessage->conversation_id);

            if ($mayOpen) {
                $attendanceOpenedByMessage = Attendance::query()
                    ->where('opened_by_message_id', $lockedMessage->id)
                    ->lockForUpdate()
                    ->first();

                if ($attendanceOpenedByMessage) {
                    return $attendanceOpenedByMessage;
                }
            }

            $attendance = Attendance::query()
                ->where('conversation_id', $conversation->id)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();
            $occurredAt = $this->occurredAt($lockedMessage);

            if (! $attendance || $attendance->closed_at) {
                if (! $mayOpen) {
                    return null;
                }

                return $this->open(
                    $conversation,
                    $lockedMessage,
                    $occurredAt,
                    ($attendance?->sequence ?? 0) + 1
                );
            }

            if ($occurredAt->greaterThan($attendance->last_real_message_at)) {
                $attendance->update([
                    'last_message_id' => $lockedMessage->id,
                    'last_real_message_at' => $occurredAt,
                ]);
            }

            return $attendance->fresh();
        }, 3);
    }

    private function open(
        Conversation $conversation,
        Message $message,
        CarbonInterface $occurredAt,
        int $sequence
    ): Attendance {
        $activity = Activity::query()->create([
            'title' => trans(
                $sequence === 1
                    ? 'topweb_chat::app.attendance.initial_title'
                    : 'topweb_chat::app.attendance.continued_title'
            ),
            'type' => 'note',
            'schedule_from' => $occurredAt,
            'is_done' => false,
            'user_id' => $message->user_id,
        ]);

        if ($conversation->lead_id) {
            $activity->leads()->syncWithoutDetaching([$conversation->lead_id]);
        }

        if ($conversation->person_id) {
            $activity->persons()->syncWithoutDetaching([$conversation->person_id]);
        }

        $conversation->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return Attendance::query()->create([
            'conversation_id' => $conversation->id,
            'activity_id' => $activity->id,
            'sequence' => $sequence,
            'opened_by_message_id' => $message->id,
            'last_message_id' => $message->id,
            'opened_at' => $occurredAt,
            'last_real_message_at' => $occurredAt,
        ]);
    }

    private function occurredAt(Message $message): CarbonInterface
    {
        return ($message->sent_at ?? $message->created_at ?? now())->copy();
    }

    private function isHumanOutbound(Message $message): bool
    {
        return $message->direction === 'outgoing'
            && $message->source === 'topweb_chat'
            && $message->user_id !== null
            && $this->isRealMessage($message);
    }

    private function isRealMessage(Message $message): bool
    {
        return $message->source !== 'openwa_history'
            && ! in_array(strtolower($message->type), self::TECHNICAL_TYPES, true);
    }

    private function inactivityMinutes(): int
    {
        return max(1, (int) config(
            'topweb-chat.attendance.inactivity_minutes',
            1440
        ));
    }
}
