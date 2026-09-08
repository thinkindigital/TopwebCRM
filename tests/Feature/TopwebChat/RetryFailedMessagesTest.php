<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Webkul\TopwebChat\Jobs\SendMessage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;

beforeEach(function () {
    foreach (['topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('provider');
        $table->uuid('session_uuid')->nullable();
        $table->text('token')->nullable();
        $table->text('webhook_secret')->nullable();
        $table->string('base_url')->nullable();
        $table->string('status')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    Schema::create('topweb_chat_conversations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('instance_id');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('lead_id')->nullable();
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->unsignedInteger('assigned_group_id')->nullable();
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->string('priority')->default('normal');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_message_at')->nullable();
        $table->timestamp('history_cursor_at')->nullable();
        $table->timestamp('history_backfilled_at')->nullable();
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
    });

    Schema::create('topweb_chat_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->uuid('operation_key')->nullable();
        $table->text('provider_message_id')->nullable();
        $table->char('provider_message_key', 64)->nullable();
        $table->string('direction');
        $table->string('type');
        $table->longText('content')->nullable();
        $table->string('status');
        $table->unsignedInteger('attempts')->default(0);
        $table->string('source');
        $table->longText('metadata')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('delivered_at')->nullable();
        $table->timestamp('read_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->string('last_error')->nullable();
        $table->timestamps();
    });

    Storage::fake('private');
    Queue::fake();
});

function retryFailedContext(): array
{
    $ready = Instance::query()->create([
        'name' => 'Ready', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $down = Instance::query()->create([
        'name' => 'Down', 'provider' => 'openwa', 'status' => 'disconnected', 'enabled' => true,
    ]);
    $mkConv = fn (Instance $i) => Conversation::query()->create([
        'instance_id' => $i->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', $i->id.'5511999999999@s.whatsapp.net'),
    ]);

    return [$ready, $down, $mkConv];
}

it('requeues only disconnected failures when the session is ready again', function () {
    [$ready, $down, $mkConv] = retryFailedContext();
    $mkMessage = fn ($conv, $status, $error, $failedAt) => Message::query()->create([
        'conversation_id' => $conv->id, 'direction' => 'outgoing', 'type' => 'text',
        'content' => 'oi', 'status' => $status, 'source' => 'topweb_chat',
        'last_error' => $error, 'failed_at' => $failedAt,
    ]);

    $eligible = $mkMessage($mkConv($ready), 'failed', 'provider_instance_not_connected', now()->subHour());
    $unknown = $mkMessage($mkConv($ready), 'unknown', 'provider_request_outcome_unknown', now()->subHour());
    $otherError = $mkMessage($mkConv($ready), 'failed', 'provider_request_rejected', now()->subHour());
    $stale = $mkMessage($mkConv($ready), 'failed', 'provider_instance_not_connected', now()->subDays(30));
    $downInstance = $mkMessage($mkConv($down), 'failed', 'provider_instance_not_connected', now()->subHour());
    $sent = $mkMessage($mkConv($ready), 'sent', null, null);

    config()->set('topweb-chat.retry_failed_window_hours', 72);

    $this->artisan('topweb-chat:retry-failed')->assertSuccessful();

    expect($eligible->fresh()->status)->toBe('queued')
        ->and($unknown->fresh()->status)->toBe('unknown')
        ->and($otherError->fresh()->status)->toBe('failed')
        ->and($stale->fresh()->status)->toBe('failed')
        ->and($downInstance->fresh()->status)->toBe('failed')
        ->and($sent->fresh()->status)->toBe('sent');

    Queue::assertPushed(SendMessage::class, 1);
});
