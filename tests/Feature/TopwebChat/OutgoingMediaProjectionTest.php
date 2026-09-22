<?php

// OBS2/Slice 4a: mídia ENVIADA também é projetada nos arquivos do lead/pessoa
// (antes só inbound). Evita reenvio: admin e comercial veem o ID; nome e
// bytes seguem sob a concessão (ActivityResource/MediaProjectionAccessService).

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Jobs\ProjectLeadMedia;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\MediaProjection;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Services\LeadMediaProjector;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\User\Models\User;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    foreach ([
        'topweb_chat_media_projections',
        'topweb_chat_attendances',
        'activity_files',
        'activity_participants',
        'lead_activities',
        'person_activities',
        'activities',
        'topweb_chat_messages',
        'topweb_chat_conversations',
        'topweb_chat_instances',
        'leads',
        'persons',
        'attributes',
        'users',
    ] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::enableForeignKeyConstraints();

    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->unsignedInteger('role_id')->nullable();
        $table->boolean('status')->default(true);
        $table->string('view_permission')->default('individual');
        $table->boolean('can_view_sensitive_data')->default(false);
        $table->timestamps();
    });

    Schema::create('persons', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->text('contact_numbers')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('organization_id')->nullable();
        $table->timestamps();
    });

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->unique();
        $table->string('name');
        $table->string('type');
        $table->string('entity_type');
        $table->timestamps();
    });

    Schema::create('leads', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title');
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('user_id')->nullable();
        $table->timestamps();
    });

    Schema::create('activities', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->nullable();
        $table->string('type');
        $table->text('comment')->nullable();
        $table->json('additional')->nullable();
        $table->dateTime('schedule_from')->nullable();
        $table->dateTime('schedule_to')->nullable();
        $table->boolean('is_done')->default(false);
        $table->unsignedInteger('user_id');
        $table->timestamps();
    });

    Schema::create('lead_activities', function (Blueprint $table) {
        $table->unsignedInteger('lead_id');
        $table->unsignedInteger('activity_id');
        $table->unique(['lead_id', 'activity_id']);
    });

    Schema::create('person_activities', function (Blueprint $table) {
        $table->unsignedInteger('person_id');
        $table->unsignedInteger('activity_id');
        $table->unique(['person_id', 'activity_id']);
    });

    Schema::create('activity_files', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('path');
        $table->unsignedInteger('activity_id');
        $table->timestamps();
    });

    Schema::create('activity_participants', function (Blueprint $table) {
        $table->increments('id');
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->unsignedInteger('person_id')->nullable();
        $table->timestamps();
    });

    Schema::create('topweb_chat_instances', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('provider');
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
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->string('priority')->default('normal');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_message_at')->nullable();
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
        $table->timestamps();
    });

    Schema::create('topweb_chat_attendances', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('activity_id');
        $table->unsignedInteger('sequence');
        $table->unsignedBigInteger('opened_by_message_id')->nullable();
        $table->unsignedBigInteger('last_message_id')->nullable();
        $table->timestamp('opened_at');
        $table->timestamp('last_real_message_at');
        $table->timestamp('closed_at')->nullable();
        $table->timestamps();
        $table->unique(['conversation_id', 'sequence']);
    });

    Schema::create('topweb_chat_media_projections', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('message_id')->nullable()->unique();
        $table->unsignedInteger('activity_id')->unique();
        $table->unsignedInteger('activity_file_id')->unique();
        $table->unsignedInteger('lead_id')->nullable();
        $table->unsignedInteger('person_id')->nullable();
        $table->timestamps();
    });

    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');
    Queue::fake();
});

function outgoingProjectionContext(): array
{
    $user = User::query()->create(['name' => 'Comercial', 'email' => 'c@example.com']);
    // LogsActivity cria activity system no create do Lead: exige auth.
    test()->actingAs($user, 'user');
    $person = Person::query()->create(['name' => 'Cliente Alto Padrao']);
    $lead = Lead::query()->create(['title' => 'Cobertura 500m²', 'person_id' => $person->id, 'user_id' => $user->id]);
    $instance = Instance::query()->create([
        'name' => 'Proj', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'person_id' => $person->id,
        'lead_id' => $lead->id,
        'assigned_user_id' => $user->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);

    return [$user, $person, $lead, $conversation];
}

it('projects outgoing sent media to the lead files without duplicating bytes', function () {
    [$user, $person, $lead, $conversation] = outgoingProjectionContext();
    Storage::disk('private')->put('topweb-chat/outbound/proposta.pdf', 'bytes-da-proposta');
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'operation_key' => (string) Str::uuid(),
        'direction' => 'outgoing',
        'type' => 'document',
        'status' => 'sent',
        'source' => 'topweb_chat',
        'sent_at' => now(),
        'metadata' => [
            'has_media' => true,
            'media_status' => 'stored',
            'media_path' => 'topweb-chat/outbound/proposta.pdf',
            'media_mime' => 'application/pdf',
            'media_original_name' => 'proposta-cobertura.pdf',
        ],
    ]);

    $projection = app(LeadMediaProjector::class)->project($message);

    expect($projection)->not->toBeNull();
    // Idempotente: projetar de novo não duplica.
    expect(app(LeadMediaProjector::class)->project($message->fresh())->message_id)->toBe($message->id);

    $file = ActivityFile::query()->findOrFail($projection->activity_file_id);

    expect(MediaProjection::query()->count())->toBe(1)
        ->and($file->path)->toBe('topweb-chat/outbound/proposta.pdf')
        ->and($file->name)->toBe('proposta-cobertura.pdf')
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1)
        ->and($file->activity->leads()->whereKey($lead->id)->exists())->toBeTrue()
        ->and($file->activity->persons()->whereKey($person->id)->exists())->toBeTrue();
});

it('queues projection when new outbound media is stored', function () {
    [$user, , , $conversation] = outgoingProjectionContext();

    app(MessageService::class)->queueMedia(
        $conversation,
        $user,
        UploadedFile::fake()->image('proposta.jpg'),
        null,
        (string) Str::uuid(),
    );

    Queue::assertPushed(ProjectLeadMedia::class);
});
