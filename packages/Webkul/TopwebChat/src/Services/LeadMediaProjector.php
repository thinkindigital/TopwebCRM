<?php

namespace Webkul\TopwebChat\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\MediaProjection;
use Webkul\TopwebChat\Models\Message;

class LeadMediaProjector
{
    public function project(Message $message): ?MediaProjection
    {
        return DB::transaction(function () use ($message) {
            $lockedMessage = Message::query()
                ->lockForUpdate()
                ->findOrFail($message->id);
            $existing = MediaProjection::query()
                ->where('message_id', $lockedMessage->id)
                ->first();

            if ($existing) {
                return $existing;
            }

            if (
                $lockedMessage->direction !== 'incoming'
                || $lockedMessage->source === 'openwa_history'
                || ! $lockedMessage->mediaIsStored()
            ) {
                return null;
            }

            $conversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($lockedMessage->conversation_id);

            if (! $conversation->lead_id) {
                return null;
            }

            $userId = $conversation->assigned_user_id
                ?: DB::table('leads')->where('id', $conversation->lead_id)->value('user_id')
                ?: DB::table('persons')->where('id', $conversation->person_id)->value('user_id');

            if (! $userId) {
                return null;
            }

            $activity = Activity::query()->create([
                'title' => trans('topweb_chat::app.media.received_activity'),
                'type' => 'file',
                'is_done' => true,
                'user_id' => $userId,
            ]);
            $activity->leads()->syncWithoutDetaching([$conversation->lead_id]);

            if ($conversation->person_id) {
                $activity->persons()->syncWithoutDetaching([$conversation->person_id]);
            }

            $file = ActivityFile::query()->create([
                'name' => $this->fileName($lockedMessage),
                'path' => (string) data_get($lockedMessage->metadata, 'media_path'),
                'activity_id' => $activity->id,
            ]);

            return MediaProjection::query()->create([
                'message_id' => $lockedMessage->id,
                'activity_id' => $activity->id,
                'activity_file_id' => $file->id,
                'lead_id' => $conversation->lead_id,
                'person_id' => $conversation->person_id,
            ]);
        }, 3);
    }

    public function projectConversation(Conversation $conversation): int
    {
        $projected = 0;

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->where('source', '!=', 'openwa_history')
            ->whereIn('type', Message::MEDIA_TYPES)
            ->orderBy('id')
            ->get()
            ->each(function (Message $message) use (&$projected) {
                if ($this->project($message)) {
                    $projected++;
                }
            });

        return $projected;
    }

    private function fileName(Message $message): string
    {
        $name = basename(str_replace('\\', '/', (string) (
            data_get($message->metadata, 'media_original_name')
            ?: data_get($message->metadata, 'media_name')
        )));
        $name = Str::of($name)
            ->replaceMatches('/[\x00-\x1F\x7F]/u', '')
            ->trim()
            ->limit(180, '')
            ->toString();

        if ($name !== '') {
            return $name;
        }

        $extension = pathinfo(
            (string) data_get($message->metadata, 'media_path'),
            PATHINFO_EXTENSION
        ) ?: 'bin';

        return 'arquivo-whatsapp-'.$message->id.'.'.$extension;
    }
}
