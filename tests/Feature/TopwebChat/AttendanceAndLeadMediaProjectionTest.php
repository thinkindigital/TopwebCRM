<?php

use App\Services\SensitiveFileService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Models\File as ActivityFile;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\TopwebChat\Jobs\DownloadMessageMedia;
use Webkul\TopwebChat\Models\Attendance;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\MediaProjection;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Models\WebhookEvent;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;
use Webkul\TopwebChat\Providers\ModuleServiceProvider as TopwebChatModuleServiceProvider;
use Webkul\TopwebChat\Services\AttendanceService;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\LeadMediaProjector;
use Webkul\TopwebChat\Services\MediaProjectionAccessService;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\TopwebChat\Services\RemoteIdentityService;
use Webkul\TopwebChat\Services\WebhookProcessor;
use Webkul\User\Models\User;

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    foreach ([
        'topweb_chat_media_projections',
        'topweb_chat_attendances',
        'topweb_chat_webhook_events',
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
        $table->uuid('session_uuid')->nullable();
        $table->text('token');
        $table->text('webhook_secret');
        $table->string('base_url')->nullable();
        $table->string('status')->nullable();
        $table->boolean('enabled')->default(true);
        $table->timestamps();
    });

    Schema::create('topweb_chat_conversations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('instance_id');
        $table->unsignedInteger('person_id');
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

    Schema::create('topweb_chat_webhook_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('instance_id');
        $table->char('event_key', 64)->unique();
        $table->string('event_type');
        $table->longText('payload');
        $table->string('status')->default('pending');
        $table->unsignedInteger('attempts')->default(0);
        $table->timestamp('processed_at')->nullable();
        $table->timestamp('failed_at')->nullable();
        $table->text('last_error')->nullable();
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

    Queue::fake();
    Carbon::setTestNow('2026-09-03 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('keeps the TopwebChat model proxies registered after the installer publishes config', function () {
    $publishedConfig = require base_path('packages/Webkul/Core/src/Config/concord.php');

    expect($publishedConfig['modules'])->toContain(TopwebChatModuleServiceProvider::class);
});

function attendanceFixture(): array
{
    $userId = DB::table('users')->insertGetId([
        'name' => 'QA User',
        'email' => 'qa@example.test',
        'status' => true,
        'view_permission' => 'individual',
        'can_view_sensitive_data' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $personId = DB::table('persons')->insertGetId([
        'name' => 'Test Contact',
        'contact_numbers' => json_encode([['value' => '5500000000000']]),
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $leadId = DB::table('leads')->insertGetId([
        'title' => 'Test Lead',
        'person_id' => $personId,
        'user_id' => $userId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $instance = Instance::query()->create([
        'name' => 'OpenWA test',
        'provider' => 'openwa',
        'session_uuid' => (string) Str::uuid(),
        'token' => 'test-token',
        'webhook_secret' => 'test-webhook-secret',
        'base_url' => 'http://openwa.test:2785',
        'status' => 'ready',
        'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'person_id' => $personId,
        'lead_id' => $leadId,
        'remote_jid' => '5500000000000',
        'remote_jid_key' => app(RemoteIdentityService::class)->key('5500000000000'),
        'status' => 'open',
    ]);

    return [
        User::query()->findOrFail($userId),
        Person::query()->findOrFail($personId),
        Lead::query()->findOrFail($leadId),
        $conversation,
    ];
}

it('creates, renews, closes and continues one native activity per attendance window', function () {
    [$user, $person, $lead, $conversation] = attendanceFixture();
    $attendanceService = app(AttendanceService::class);
    $messageService = new MessageService(
        app(ConversationAccessService::class),
        $attendanceService
    );

    $operationKey = (string) Str::uuid();
    $outbound = $messageService->queueText(
        $conversation,
        $user,
        'Test message',
        $operationKey
    );

    $attendance = Attendance::query()->firstOrFail();

    expect($attendance->sequence)->toBe(1)
        ->and($attendance->opened_by_message_id)->toBe($outbound->id)
        ->and($attendance->closed_at)->toBeNull()
        ->and($attendance->activity->title)->toBe(trans('topweb_chat::app.attendance.initial_title'))
        ->and((bool) $attendance->activity->is_done)->toBeFalse()
        ->and($attendance->activity->leads()->whereKey($lead->id)->exists())->toBeTrue()
        ->and($attendance->activity->persons()->whereKey($person->id)->exists())->toBeTrue();

    Carbon::setTestNow('2026-09-04 08:00:00');
    $incoming = Message::query()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'provider-inbound-test',
        'provider_message_key' => hash('sha256', 'provider-inbound-test'),
        'direction' => 'incoming',
        'type' => 'text',
        'content' => 'Test response',
        'status' => 'received',
        'source' => 'openwa',
        'sent_at' => now(),
    ]);
    $attendanceService->recordRealMessage($incoming);

    Carbon::setTestNow('2026-09-05 07:59:59');
    expect($attendanceService->closeStale())->toBe(0);

    Carbon::setTestNow('2026-09-05 08:00:01');
    expect($attendanceService->closeStale())->toBe(1);

    $attendance->refresh();
    expect($attendance->closed_at)->not->toBeNull()
        ->and((bool) $attendance->activity->fresh()->is_done)->toBeTrue()
        ->and($attendance->activity->fresh()->comment)->not->toBeNull()
        ->and($conversation->fresh()->status)->toBe('closed');

    Carbon::setTestNow('2026-09-05 09:00:00');
    $messageService->queueText(
        $conversation->fresh(),
        $user,
        'Test message',
        $operationKey
    );
    expect(Attendance::query()->count())->toBe(1);

    $messageService->queueText(
        $conversation->fresh(),
        $user,
        'Continuation test',
        (string) Str::uuid()
    );

    expect(Attendance::query()->count())->toBe(2)
        ->and(Attendance::query()->latest('sequence')->first()->activity->title)
        ->toBe(trans('topweb_chat::app.attendance.continued_title'))
        ->and($conversation->fresh()->status)->toBe('open');
});

it('does not open or renew attendance from imported history', function () {
    [$user, , , $conversation] = attendanceFixture();
    $attendanceService = app(AttendanceService::class);
    $history = Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'incoming',
        'type' => 'text',
        'content' => 'Historical test message',
        'status' => 'received',
        'source' => 'openwa_history',
        'sent_at' => now()->subDay(),
    ]);

    $attendanceService->recordRealMessage($history);
    expect(Attendance::query()->count())->toBe(0);

    $messageService = new MessageService(
        app(ConversationAccessService::class),
        $attendanceService
    );
    $messageService->queueText(
        $conversation,
        $user,
        'Live test message',
        (string) Str::uuid()
    );
    $lastRealMessageAt = Attendance::query()->firstOrFail()->last_real_message_at;

    Carbon::setTestNow(now()->addHours(2));
    $history->sent_at = now();
    $attendanceService->recordRealMessage($history);

    expect(Attendance::query()->firstOrFail()->last_real_message_at->equalTo($lastRealMessageAt))
        ->toBeTrue();
});

it('renews an active attendance through the live webhook processor', function () {
    [$user, , , $conversation] = attendanceFixture();
    $messageService = app(MessageService::class);
    $messageService->queueText(
        $conversation,
        $user,
        'Live test message',
        (string) Str::uuid()
    );
    $attendance = Attendance::query()->firstOrFail();

    Carbon::setTestNow('2026-09-03 10:00:00');
    $event = WebhookEvent::query()->create([
        'instance_id' => $conversation->instance_id,
        'event_key' => hash('sha256', 'webhook-test-event'),
        'event_type' => 'message.received',
        'payload' => [
            'data' => [
                'id' => 'provider-webhook-test',
                'from' => '5500000000000',
                'to' => 'test-session',
                'body' => 'Webhook test response',
                'type' => 'text',
                'timestamp' => now()->timestamp,
                'fromMe' => false,
            ],
        ],
        'status' => 'pending',
    ]);

    app(WebhookProcessor::class)->process($event);

    expect($attendance->fresh()->last_real_message_at->equalTo(now()))->toBeTrue()
        ->and(Attendance::query()->count())->toBe(1)
        ->and(Message::query()->where('provider_message_key', hash(
            'sha256',
            $conversation->instance_id.'|provider-webhook-test'
        ))->exists())->toBeTrue();
});

it('projects received media to the lead files without duplicating the private object', function () {
    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');
    [, $person, $lead, $conversation] = attendanceFixture();
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'provider-media-test',
        'provider_message_key' => hash('sha256', 'provider-media-test'),
        'direction' => 'incoming',
        'type' => 'image',
        'status' => 'received',
        'source' => 'openwa',
        'metadata' => [
            'chat_id' => 'test-chat',
            'has_media' => true,
            'media_status' => 'queued',
            'media_original_name' => 'test-image.png',
        ],
    ]);
    $image = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    $provider = mock(MessagingProvider::class);
    $provider->shouldReceive('downloadMedia')->once()->andReturn($image);
    app()->instance(MessagingProvider::class, $provider);
    $job = new DownloadMessageMedia($message->id);

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    $message->refresh();
    $projection = MediaProjection::query()->firstOrFail();
    $file = ActivityFile::query()->findOrFail($projection->activity_file_id);

    expect(Activity::query()->where('type', 'file')->count())->toBe(1)
        ->and(ActivityFile::query()->count())->toBe(1)
        ->and(MediaProjection::query()->count())->toBe(1)
        ->and($file->path)->toBe(data_get($message->metadata, 'media_path'))
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1)
        ->and($file->activity->leads()->whereKey($lead->id)->exists())->toBeTrue()
        ->and($file->activity->persons()->whereKey($person->id)->exists())->toBeTrue();
});

it('reconciles stored media after a lead is associated with the conversation', function () {
    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');
    [$user, , $lead, $conversation] = attendanceFixture();
    $conversation->update(['lead_id' => null]);
    app(MessageService::class)->queueText(
        $conversation->fresh(),
        $user,
        'Lead association test',
        (string) Str::uuid()
    );
    $attendance = Attendance::query()->firstOrFail();
    expect($attendance->activity->leads()->exists())->toBeFalse();

    Storage::disk('private')->put('topweb-chat/test/stored.pdf', 'test-pdf');
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'incoming',
        'type' => 'document',
        'status' => 'received',
        'source' => 'openwa',
        'metadata' => [
            'has_media' => true,
            'media_status' => 'stored',
            'media_path' => 'topweb-chat/test/stored.pdf',
            'media_mime' => 'application/pdf',
            'media_name' => 'test-document.pdf',
        ],
    ]);
    $projector = app(LeadMediaProjector::class);

    expect($projector->project($message))->toBeNull();

    $conversation->update(['lead_id' => $lead->id]);
    app(AttendanceService::class)->syncConversationAssociations(
        $conversation->fresh()
    );

    expect($projector->projectConversation($conversation->fresh()))->toBe(1)
        ->and(MediaProjection::query()->where('message_id', $message->id)->exists())->toBeTrue()
        ->and($attendance->activity->leads()->whereKey($lead->id)->exists())->toBeTrue()
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1);
});

it('materializes a received vcard as a private lead file', function () {
    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');
    [, , , $conversation] = attendanceFixture();
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'provider-contact-test',
        'provider_message_key' => hash('sha256', 'provider-contact-test'),
        'direction' => 'incoming',
        'type' => 'contact',
        'content' => "BEGIN:VCARD\nVERSION:3.0\nFN:Test Contact\nEND:VCARD",
        'status' => 'received',
        'source' => 'openwa',
        'metadata' => [
            'has_media' => true,
            'media_status' => 'queued',
        ],
    ]);
    $provider = mock(MessagingProvider::class);
    $provider->shouldNotReceive('downloadMedia');

    (new DownloadMessageMedia($message->id))->handle(
        $provider,
        app(SensitiveFileService::class),
        app(LeadMediaProjector::class)
    );

    $message->refresh();

    expect(data_get($message->metadata, 'media_mime'))->toBe('text/vcard')
        ->and(data_get($message->metadata, 'media_path'))->toEndWith('.vcf')
        ->and(MediaProjection::query()->where('message_id', $message->id)->exists())->toBeTrue()
        ->and(Storage::disk('private')->allFiles())->toHaveCount(1);
});

it('hides projected file metadata and rejects cross-record access', function () {
    Storage::fake('private');
    config()->set('sensitive-data.storage.disk', 'private');
    [$user, , , $conversation] = attendanceFixture();
    Storage::disk('private')->put('topweb-chat/test/private.pdf', 'private-test');
    $message = Message::query()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'incoming',
        'type' => 'document',
        'status' => 'received',
        'source' => 'openwa',
        'metadata' => [
            'has_media' => true,
            'media_status' => 'stored',
            'media_path' => 'topweb-chat/test/private.pdf',
            'media_mime' => 'application/pdf',
            'media_name' => 'private-test.pdf',
        ],
    ]);
    $projection = app(LeadMediaProjector::class)->project($message);
    $activity = Activity::query()->with('files')->findOrFail($projection->activity_id);

    $user->update(['can_view_sensitive_data' => false]);
    $this->actingAs($user->fresh(), 'user');
    $resource = (new ActivityResource($activity))->toArray(request());

    expect($resource['files'])->toHaveCount(0);

    $deniedAccess = mock(ConversationAccessService::class);
    $deniedAccess->shouldReceive('isAdministrator')->twice()->andReturnFalse();
    $deniedAccess->shouldReceive('canAccessLead')->twice()->andReturnFalse();
    $deniedAccess->shouldReceive('canAccessPerson')->twice()->andReturnFalse();
    $guard = new MediaProjectionAccessService($deniedAccess);
    $file = ActivityFile::query()->findOrFail($projection->activity_file_id);
    app()->instance(MediaProjectionAccessService::class, $guard);

    $user->update(['can_view_sensitive_data' => true]);
    $this->actingAs($user->fresh(), 'user');
    $crossRecordResource = (new ActivityResource($activity))->toArray(request());

    expect($crossRecordResource['files'])->toHaveCount(0)
        ->and(fn () => $guard->authorize($user->fresh(), $file))
        ->toThrow(AuthorizationException::class);

    $ownerAccess = mock(ConversationAccessService::class);
    $ownerAccess->shouldReceive('isAdministrator')->once()->andReturnFalse();
    $ownerAccess->shouldReceive('canAccessLead')->once()->andReturnTrue();
    $ownerAccess->shouldNotReceive('canAccessPerson');

    expect((new MediaProjectionAccessService($ownerAccess))->canAccess(
        $user->fresh(),
        $file
    ))->toBeTrue();
});
