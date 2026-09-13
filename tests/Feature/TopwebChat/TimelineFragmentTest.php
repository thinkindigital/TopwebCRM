<?php

// E-03: fragmento do servidor como representação canônica da timeline.
// Uma fonte visual (Blade); o JS só troca HTML e preserva scroll.

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

beforeEach(function () {
    config()->set('app.debug', false);

    foreach (['topweb_chat_messages', 'topweb_chat_internal_notes', 'topweb_chat_conversations', 'topweb_chat_instances', 'users', 'roles', 'core_config'] as $table) {
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
        $table->unsignedInteger('person_id')->nullable();
        $table->unsignedInteger('lead_id')->nullable();
        $table->unsignedInteger('assigned_user_id')->nullable();
        $table->text('remote_jid');
        $table->char('remote_jid_key', 64);
        $table->string('status')->default('open');
        $table->unsignedInteger('unread_count')->default(0);
        $table->timestamp('last_message_at')->nullable();
        $table->timestamps();
    });

    Schema::create('topweb_chat_messages', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->uuid('operation_key')->nullable();
        $table->text('provider_message_id')->nullable();
        $table->string('direction');
        $table->string('type')->default('text');
        $table->longText('content')->nullable();
        $table->string('status')->default('sent');
        $table->string('source')->default('openwa');
        $table->longText('metadata')->nullable();
        $table->timestamp('sent_at')->nullable();
        $table->timestamps();
    });

    Schema::create('core_config', function (Blueprint $table) {
        $table->increments('id');
        $table->string('code');
        $table->string('value')->nullable();
        $table->timestamps();
    });

    Schema::create('topweb_chat_internal_notes', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('conversation_id');
        $table->unsignedInteger('user_id')->nullable();
        $table->text('content');
        $table->timestamps();
    });

    touch(storage_path('installed'));
});

afterEach(function () {
    @unlink(storage_path('installed'));
});

function fragmentContext(): array
{
    $adminRole = Role::query()->create(['name' => 'Admin', 'permission_type' => 'all']);
    $admin = User::query()->create([
        'name' => 'Admin', 'email' => 'admin@example.com',
        'role_id' => $adminRole->id, 'status' => true,
    ]);
    $instance = Instance::query()->create([
        'name' => 'Frag', 'provider' => 'openwa', 'status' => 'ready', 'enabled' => true,
    ]);
    $conversation = Conversation::query()->create([
        'instance_id' => $instance->id,
        'remote_jid' => '5511999999999@s.whatsapp.net',
        'remote_jid_key' => hash('sha256', '5511999999999@s.whatsapp.net'),
    ]);
    $first = Message::query()->create([
        'conversation_id' => $conversation->id, 'direction' => 'incoming',
        'type' => 'text', 'content' => 'ola', 'status' => 'received',
        'source' => 'openwa', 'sent_at' => now()->subDay(),
    ]);
    $second = Message::query()->create([
        'conversation_id' => $conversation->id, 'direction' => 'outgoing',
        'type' => 'text', 'content' => 'oi', 'status' => 'sent',
        'source' => 'topweb_chat', 'sent_at' => now(),
    ]);

    return compact('admin', 'conversation', 'first', 'second');
}

it('serves the timeline as a server-rendered fragment', function () {
    ['admin' => $admin, 'conversation' => $conversation, 'first' => $first, 'second' => $second] = fragmentContext();
    $this->actingAs($admin, 'user');

    $response = $this->get(route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
    $response->assertSee('data-message-id="'.$first->id.'"', false);
    $response->assertSee('data-message-id="'.$second->id.'"', false);
    $response->assertSee('topweb-chat-date-separator', false);
    $response->assertSee('topweb-chat-poll-meta', false);
    $response->assertSee('topweb-chat-anchor', false);
});

it('keeps fragment and JSON in agreement', function () {
    ['admin' => $admin, 'conversation' => $conversation, 'first' => $first, 'second' => $second] = fragmentContext();
    $this->actingAs($admin, 'user');

    $html = $this->get(route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline')->getContent();
    $json = $this->getJson(route('admin.topweb_chat.messages.index', $conversation))->json('messages');

    foreach ([$first->id, $second->id] as $id) {
        expect($html)->toContain('data-message-id="'.$id.'"');
    }
    expect(collect($json)->pluck('id')->all())->toEqual([$first->id, $second->id]);
});

it('renders the initial timeline from the same partial', function () {
    $show = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($show)->toContain('conversations.partials.timeline-messages');
});

it('explains ambiguous sends without offering retry', function () {
    ['admin' => $admin, 'conversation' => $conversation] = fragmentContext();
    \Webkul\TopwebChat\Models\Message::query()->create([
        'conversation_id' => $conversation->id, 'direction' => 'outgoing',
        'type' => 'text', 'content' => 'duvida', 'status' => 'unknown',
        'source' => 'topweb_chat', 'sent_at' => now(),
    ]);
    $this->actingAs($admin, 'user');

    $this->get(route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline')
        ->assertOk()
        ->assertSee('topweb-chat-status-unknown', false);
});

it('handles expired sessions on submit', function () {
    $view = file_get_contents(
        base_path('packages/Webkul/TopwebChat/src/Resources/views/conversations/show.blade.php')
    );

    expect($view)->toContain('message_queue_failed:${response.status}')
        ->and($view)->toContain('messages.session_expired')
        ->and($view)->toContain('window.location.reload()');
});

it('shows internal notes inline only to users with the notes permission', function () {
    ['admin' => $admin, 'conversation' => $conversation] = fragmentContext();
    \Webkul\TopwebChat\Models\InternalNote::query()->create([
        'conversation_id' => $conversation->id, 'user_id' => $admin->id,
        'content' => 'cliente quer visitar sabado',
    ]);

    $this->actingAs($admin, 'user');
    $fragment = $this->get(route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline');
    $fragment->assertOk();
    $fragment->assertSee('cliente quer visitar sabado', false);
    $fragment->assertSee('topweb-chat-internal-note', false);

    $agentRole = \Webkul\User\Models\Role::query()->create([
        'name' => 'Agente', 'permission_type' => 'custom',
        'permissions' => ['topweb_chat.inbox.view'],
    ]);
    $agent = \Webkul\User\Models\User::query()->create([
        'name' => 'Agente', 'email' => 'agente@example.com',
        'role_id' => $agentRole->id, 'status' => true,
    ]);

    $this->actingAs($agent, 'user');
    $this->get(route('admin.topweb_chat.messages.index', $conversation).'?fragment=timeline')
        ->assertOk()
        ->assertDontSee('cliente quer visitar sabado', false);
});
