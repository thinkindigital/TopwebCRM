<?php

// Incidente 20MB: provider_request_rejected era silencioso no servidor
// (só last_error no banco). Rejeição loga status para triagem rápida e a
// timeline exibe código visível (A5002/A5003, #111).

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Jobs\SendMessage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

beforeEach(function () {
    foreach (['topweb_chat_messages', 'topweb_chat_conversations', 'topweb_chat_instances'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('provider')->default('openwa');
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
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->timestamps();
    });

    Schema::create('topweb_chat_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->string('direction');
        $table->string('type');
        $table->longText('content')->nullable();
        $table->string('status');
        $table->unsignedInteger('attempts')->default(0);
        $table->string('source');
        $table->longText('metadata')->nullable();
        $table->text('provider_message_id')->nullable();
        $table->char('provider_message_key', 64)->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->string('last_error')->nullable();
        $table->timestamps();
    });

    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');
});

function rejectionContext(): Message
{
    $instance = Instance::query()->create([
        'name' => 'Rej', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'outgoing',
        'type' => 'document',
        'content' => 'proposta',
        'status' => 'queued',
        'source' => 'topweb_chat',
    ]);
}

it('logs provider rejections with status for fast triage', function () {
    $message = rejectionContext();
    Log::spy();

    $provider = mock(MessagingProvider::class);
    $provider->shouldReceive('sendMedia')->once()->andThrow(
        new ProviderRequestException('rejected', false, 413)
    );
    app()->instance(MessagingProvider::class, $provider);

    Storage::disk('private')->put('topweb-chat/outbound/rejeitado.pdf', 'bytes');
    $message->forceFill(['metadata' => [
        'has_media' => true,
        'media_status' => 'stored',
        'media_path' => 'topweb-chat/outbound/rejeitado.pdf',
        'media_mime' => 'application/pdf',
        'media_original_name' => 'rejeitado.pdf',
    ]])->save();

    app()->call([new SendMessage($message->id), 'handle']);

    expect($message->fresh()->last_error)->toBe('provider_request_rejected');
    Log::shouldHaveReceived('warning')->withArgs(function ($text, $context) {
        return str_contains((string) $text, 'TopwebChat')
            && ($context['status'] ?? null) === 413;
    });
});

it('shows a visible code for rejected and unknown sends', function () {
    $partial = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/timeline-messages.blade.php')
    );

    expect($partial)->toContain('error_');
    expect(trans('topweb_chat::app.messages.error_provider_request_rejected'))->toContain('A5003');
    expect(trans('topweb_chat::app.messages.error_provider_request_outcome_unknown'))->toContain('A5002');
});
