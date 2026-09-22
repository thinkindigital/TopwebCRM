<?php

// Matriz negativa de acesso a conversas (#23, slice 1).
// Documenta o comportamento atual por perfil; divergências vs
// AUTHORIZATION_POLICY e TopwebChat STATE são achados, não auto-fix.
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\InternalNote;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    // Matriz valida comportamento de produção (debug desligado: handler de erro real).
    config()->set('app.debug', false);

    foreach (['topweb_chat_messages', 'topweb_chat_internal_notes', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config', 'attributes'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('roles', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');
        $table->string('description')->nullable();
        $table->string('permission_type');
        $table->text('permissions')->nullable();
        $table->timestamps();
    });

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

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    DB::table('core_config')->insert([
        ['code' => 'general.design.admin_logo.favicon', 'value' => null],
        ['code' => 'general.design.admin_logo.logo_image', 'value' => null],
    ]);

    Schema::create('topweb_chat_internal_notes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->text('content');
        $table->timestamps();
    });

    Schema::create('attributes', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code')->nullable();
        $table->string('name')->nullable();
        $table->string('type')->nullable();
        $table->string('entity_type')->nullable();
        $table->boolean('quick_add')->default(false);
        $table->timestamps();
    });

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function accessMatrixContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $agentRole = Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat.inbox.view', 'dashboard'],
    ]);
    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $adminRole->id, 'status' => true,
    ]);
    $owner = User::query()->create([
        'name' => 'Dono', 'email' => 'dono@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $stranger = User::query()->create([
        'name' => 'Outro', 'email' => 'outro@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'E2E', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => false,
    ]);
    $owned = Conversation::query()->create([
        'instance_id' => $instance->id,
        'assigned_user_id' => $owner->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);
    $unassigned = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511888888888@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511888888888@s.whatsapp.net'),
    ]);

    return compact('admin', 'owner', 'stranger', 'owned', 'unassigned');
}

it('grants admins everywhere and owners on their own conversations', function () {
    ['admin' => $admin, 'owner' => $owner, 'owned' => $owned] = accessMatrixContext();

    $this->actingAs($admin, 'user');
    $this->get(route('admin.topweb_chat.show', $owned))->assertOk();
    $this->getJson(route('admin.topweb_chat.messages.index', $owned))->assertOk();

    $this->actingAs($owner, 'user');
    $this->get(route('admin.topweb_chat.show', $owned))->assertOk();
    $this->getJson(route('admin.topweb_chat.messages.index', $owned))->assertOk();
});

it('blocks strangers on conversations owned by someone else', function () {
    ['stranger' => $stranger, 'owned' => $owned] = accessMatrixContext();
    $this->actingAs($stranger, 'user');

    $this->get(route('admin.topweb_chat.show', $owned))->assertForbidden();
    $this->getJson(route('admin.topweb_chat.messages.index', $owned))->assertForbidden();
});

it('accepts canonical client telemetry with bounded context', function () {
    ['owner' => $owner, 'owned' => $owned] = accessMatrixContext();

    $this->actingAs($owner, 'user');

    $this->postJson(route('admin.topweb_chat.client_events.store', $owned), [
        'level' => 'error',
        'event' => 'client.send_failed',
        'error_code' => 'NET-9001',
        'trace_id' => '01J00000000000000000000000',
        'context' => [
            'attempt' => 1,
            'http_status' => 503,
            'operation' => 'message.send',
            'retryable' => 'conditional',
            'cause_code' => 'API-9001',
            'unexpected' => 'discarded',
        ],
    ])->assertAccepted();
});

it('rejects unknown client telemetry events and error codes', function () {
    ['owner' => $owner, 'owned' => $owned] = accessMatrixContext();

    $this->actingAs($owner, 'user');

    $this->postJson(route('admin.topweb_chat.client_events.store', $owned), [
        'level' => 'error',
        'event' => 'client.arbitrary',
        'error_code' => 'BAD-9999',
    ])->assertUnprocessable();
});

it('documents unassigned conversation access for common users', function () {
    ['stranger' => $stranger, 'unassigned' => $unassigned, 'admin' => $admin] = accessMatrixContext();

    $this->actingAs($admin, 'user');
    $this->get(route('admin.topweb_chat.show', $unassigned))->assertOk();

    // Comportamento atual: usuário comum enxerga conversa sem responsável.
    // Diverge de AUTHORIZATION_POLICY / TopwebChat STATE — achado #23,
    // sem auto-fix: mudar quebra o fluxo de claim (assignment).
    $this->actingAs($stranger, 'user');
    $this->get(route('admin.topweb_chat.show', $unassigned))->assertOk();
});

it('renders the configured official workspace style', function (string $style) {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();

    config()->set('topweb-chat.workspace_style', $style);
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->assertSee('data-topwebchat-workspace', false)
        ->assertSee('data-workspace-style="'.$style.'"', false)
        ->assertSee('data-workspace-region="queue"', false)
        ->assertSee('data-workspace-region="conversation"', false)
        ->assertSee('data-workspace-region="context"', false)
        ->assertDontSee('data-prototype-root', false);
})->with(['R1', 'R1K']);

it('shows the same note in the timeline and in the side history', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();
    InternalNote::query()->create([
        'conversation_id' => $owned->id, 'user_id' => $admin->id,
        'content' => 'nota-dupla-projecao',
    ]);
    $this->actingAs($admin, 'user');

    $html = $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'nota-dupla-projecao'))->toBeGreaterThanOrEqual(2);
    expect($html)->toContain('data-note-delete');
});

it('exposes the activity creation entry in the context', function () {
    $partial = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/crm-context.blade.php')
    );

    expect($partial)->toContain('data-activity-create')
        ->and($partial)->toContain("route('admin.topweb_chat.activities.store'")
        ->and($partial)->toContain("route('admin.topweb_chat.activities.complete'")
        ->and($partial)->toContain("route('admin.topweb_chat.activities.update'");
});

it('shows the channel state in human language, not raw status', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();
    $this->actingAs($admin, 'user');

    $html = $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-label-connected')
        ->and($html)->toContain('Connected')
        ->and($html)->not->toContain('id="topweb-chat-instance-status">ready<');
});

it('distinguishes sync degradation from channel outage', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();
    $this->actingAs($admin, 'user');

    Cache::put(
        "topweb-chat:provider-unavailable:{$owned->instance_id}", true, now()->addMinutes(5)
    );

    $html = $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Sync temporarily unavailable');
    expect(preg_match('/id="topweb-chat-connection-warning"\s+class="hidden/', $html))->toBe(1);

    $owned->instance->forceFill(['status' => 'disconnected'])->save();
    Cache::forget("topweb-chat:provider-unavailable:{$owned->instance_id}");

    $html = $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Channel unavailable — history preserved');
});

it('serves the context as an authorized server fragment', function () {
    ['admin' => $admin, 'owner' => $owner, 'stranger' => $stranger, 'owned' => $owned] = accessMatrixContext();
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.context.show', $owned).'?fragment=context')
        ->assertOk()
        ->assertSee('twp-context-stack', false)
        ->assertDontSee('data-topwebchat-workspace', false);

    $this->actingAs($stranger, 'user');
    $this->get(route('admin.topweb_chat.context.show', $owned).'?fragment=context')
        ->assertForbidden();
});

it('wires inline context mutations without reload', function () {
    $context = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/crm-context.blade.php')
    );
    $workspace = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/workspace.blade.php')
    );
    $runtime = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/chat-runtime.blade.php')
    );
    $composer = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/composer.blade.php')
    );

    expect($context)->toContain('data-note-form')
        ->and($context)->toContain('data-activity-form')
        ->and($workspace)->toContain('data-context-url')
        ->and($workspace)->toContain('topwebchat:refresh-context')
        ->and($runtime)->toContain('topwebchat:refresh-timeline')
        ->and($runtime)->toContain('isContextQuiet')
        ->and($composer)->toContain('data-channel-error');
});

it('exposes the inline stage form with pipeline selection', function () {
    $partial = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/partials/crm-context.blade.php')
    );

    expect($partial)->toContain('data-stage-form')
        ->and($partial)->toContain('data-pipeline-select')
        ->and($partial)->toContain('data-stage-select')
        ->and($partial)->toContain('data-stage-error');
});

it('disables attachment controls without the sensitive-data grant', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();
    $this->actingAs($admin, 'user');

    $response = $this->get(route('admin.topweb_chat.show', $owned))->assertOk();
    $response->assertSee('id="topweb-chat-attach-menu"', false);

    $menu = substr($response->getContent(), strpos($response->getContent(), 'id="topweb-chat-attach-menu"'), 3000);
    expect($menu)->toContain('disabled');

    $admin->forceFill(['can_view_sensitive_data' => true])->save();

    $response = $this->get(route('admin.topweb_chat.show', $owned))->assertOk();
    $menu = substr($response->getContent(), strpos($response->getContent(), 'id="topweb-chat-attach-menu"'), 3000);
    expect($menu)->not->toContain('disabled');
});

it('prefers the persisted appearance over the environment', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();
    DB::table('core_config')->insert([
        'code' => 'topwebchat.appearance.style.workspace_style', 'value' => 'R1',
    ]);
    config()->set('topweb-chat.workspace_style', 'R1K');
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->assertSee('data-workspace-style="R1"', false);
});

it('ignores invalid persisted styles and keeps the fallback chain', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();
    DB::table('core_config')->insert([
        'code' => 'topwebchat.appearance.style.workspace_style', 'value' => 'R9',
    ]);
    config()->set('topweb-chat.workspace_style', 'R1');
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.show', $owned))
        ->assertOk()
        ->assertSee('data-workspace-style="R1"', false);
});

it('falls back to R1K and ignores prototype query parameters', function () {
    ['admin' => $admin, 'owned' => $owned] = accessMatrixContext();

    config()->set('topweb-chat.workspace_style', 'unsupported');
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.show', [
        'conversation' => $owned,
        'variant' => 'R1',
        'scenario' => 'offline',
    ]))
        ->assertOk()
        ->assertSee('data-workspace-style="R1K"', false)
        ->assertDontSee('data-prototype-root', false)
        ->assertDontSee('PROTOTYPE', false);
});
