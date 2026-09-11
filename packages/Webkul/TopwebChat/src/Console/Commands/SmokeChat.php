<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\TopwebChat\Services\RemoteIdentityService;
use Webkul\User\Models\User;

class SmokeChat extends Command
{
    protected $signature = 'topwebchat:smoke
        {--instance= : CRM chat instance id (the identifying variable)}
        {--to=5511993193118 : target phone number}
        {--user-email= : owner user email (defaults to first active user)}
        {--text= : text message (defaults to a smoke message)}
        {--media= : local file path to send as media}
        {--dry-run : resolve instance/user/target without queueing}';

    protected $description = 'Instance-parameterized end-to-end smoke: queues text/media to a target phone without deleting anything';

    public function handle(
        MessageService $messages,
        RemoteIdentityService $remote,
        MessagingProvider $provider
    ): int {
        $instance = Instance::query()->find($this->option('instance'));

        if (! $instance) {
            $this->error("Instance {$this->option('instance')} not found");

            return self::FAILURE;
        }

        $ownerQuery = DB::table('users')->where('status', 1);

        if ($email = $this->option('user-email')) {
            $ownerQuery->where('email', $email);
        }

        $owner = $ownerQuery->orderBy('id')->first();

        if (! $owner) {
            throw new RuntimeException('No active user found for the smoke test.');
        }

        $ownerModel = User::query()->findOrFail($owner->id);
        $to = (string) $this->option('to');

        if ($this->option('dry-run')) {
            $this->line("instance: #{$instance->id} {$instance->name}");
            $this->line("owner: {$owner->email}");
            $this->line("target: {$to}");
            $this->line('engine: '.config('topweb-chat.engine', 'whatsapp-web.js'));

            return self::SUCCESS;
        }

        $contact = $provider->checkContact($instance, $to);
        $chatId = $contact['whatsappId'] ?? $to;

        $personId = DB::table('persons')->where('name', 'Smoke Test')->value('id');

        if (! $personId) {
            $now = Carbon::now();
            $personId = DB::table('persons')->insertGetId([
                'name' => 'Smoke Test',
                'emails' => json_encode([['value' => 'smoke@example.com', 'label' => 'work']]),
                'contact_numbers' => json_encode([['value' => $to, 'label' => 'mobile']]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $conversation = Conversation::query()->firstOrCreate(
            [
                'instance_id' => $instance->id,
                'remote_jid_key' => $remote->key($chatId),
            ],
            [
                'person_id' => $personId,
                'remote_jid' => $chatId,
                'status' => 'open',
                'priority' => 'normal',
                'assigned_user_id' => $ownerModel->id,
            ]
        );

        $operationKey = (string) Str::uuid();

        if ($media = $this->option('media')) {
            $message = $messages->queueMedia(
                $conversation,
                $ownerModel,
                new UploadedFile($media, basename($media), mime_content_type($media) ?: 'application/octet-stream', null, true),
                $this->option('text'),
                $operationKey
            );
        } else {
            $message = $messages->queueText(
                $conversation,
                $ownerModel,
                (string) ($this->option('text') ?: 'Smoke TopwebChat '.now()->toDateTimeString()),
                $operationKey
            );
        }

        $this->line("message: {$message->id}");
        $this->line("conversation: {$conversation->id}");
        $this->line("status: {$message->status}");
        $this->line("chat: {$chatId}");

        return self::SUCCESS;
    }
}
