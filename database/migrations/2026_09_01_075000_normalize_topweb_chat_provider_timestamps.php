<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $timezone = config('app.timezone', 'UTC');

        if (Schema::hasTable('topweb_chat_messages')) {
            DB::table('topweb_chat_messages')
                ->whereNotNull('sent_at')
                ->orderBy('id')
                ->chunkById(200, function ($messages) use ($timezone) {
                    foreach ($messages as $message) {
                        $updates = [];

                        foreach (['sent_at', 'delivered_at', 'read_at'] as $column) {
                            if ($message->{$column}) {
                                $updates[$column] = $this->fromUtcWallTime(
                                    $message->{$column},
                                    $timezone
                                );
                            }
                        }

                        DB::table('topweb_chat_messages')
                            ->where('id', $message->id)
                            ->update($updates);
                    }
                });
        }

        if (Schema::hasTable('topweb_chat_conversations')) {
            DB::table('topweb_chat_conversations')
                ->where(function ($query) {
                    $query->whereNotNull('last_message_at')
                        ->orWhereNotNull('history_cursor_at');
                })
                ->orderBy('id')
                ->chunkById(200, function ($conversations) use ($timezone) {
                    foreach ($conversations as $conversation) {
                        $updates = [];

                        foreach (['last_message_at', 'history_cursor_at'] as $column) {
                            if ($conversation->{$column}) {
                                $updates[$column] = $this->fromUtcWallTime(
                                    $conversation->{$column},
                                    $timezone
                                );
                            }
                        }

                        DB::table('topweb_chat_conversations')
                            ->where('id', $conversation->id)
                            ->update($updates);
                    }
                });
        }
    }

    public function down(): void
    {
        // A reversal would also shift records created after this migration.
    }

    private function fromUtcWallTime(string $value, string $timezone): string
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'UTC')
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    }
};
