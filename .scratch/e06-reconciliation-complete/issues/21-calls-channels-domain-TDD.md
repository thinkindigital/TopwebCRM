# TDD Specification — Slice #21: Calls/Channels Domain (calls + status.received)

> **Fase**: RED
> **Dependência**: #20 (groups domain - similar infra)

---

## 1. Comportamentos Criticos

| Comportamento | Prioridade |
|---------------|------------|
| **Calls** (OpenWA events: `call.received`, `call.accepted`, `call.rejected`, `call.missed`) | P0 |
| **Entidade TopwebChatCall**: `id`, `conversation_id`, `call_id` (OpenWA), `from_jid`, `is_video`, `is_group`, `status` (ringing/accepted/rejected/missed), `started_at`, `ended_at`, `duration_seconds` | P0 |
| **Webhook Handlers** | `ProcessCallReceived`, `ProcessCallEnded` (accepted/rejected/missed) | P0 |
| **UI** | Badge "Chamada perdida" na conversa + log de chamadas no detalhe | P0 |
| **Outbound** | `POST /api/sessions/:sessionId/calls/:callId/reject` (rejeitar ativamente) | P0 |
| **Channels/Newsletters** (OpenWA: `status.received` opt-in) | P1 |
| **Entidade TopwebChatChannel**: `id`, `instance_id`, `channel_jid`, `name`, `description`, `followers_count` | P1 |
| **Webhook `status.received`** | `ProcessChannelStatus` -> cria/atualiza Channel + `ChannelStatus` | P1 |
| **UI** | Menu TopwebChat > Canais (read-only, monitoramento) | P1 |
| **ACL** | `topweb_chat.calls.view`, `topweb_chat.channels.view` | P0 |

---

## 2. Test Specification (RED)

### Calls

### Teste 1: Entidade TopwebChatCall

```php
// tests/Feature/Domain/CallsChannelsDomainTest.php

it('creates Call entity with correct schema', function () {
    $this->artisan('migrate --pretend')
        ->expectsOutputToContain('topweb_chat_calls');

    $this->artisan('migrate');

    expect(Schema::hasTable('topweb_chat_calls'))->toBeTrue();
    expect(Schema::hasColumns('topweb_chat_calls', [
        'conversation_id', 'call_id', 'from_jid', 'is_video', 
        'is_group', 'status', 'started_at', 'ended_at', 'duration_seconds'
    ]))->toBeTrue();
});
```

### Teste 2: Webhook call.received -> ProcessCallReceived

```php
it('processes call.received webhook', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-call', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $payload = [
        'event' => 'call.received',
        'sessionId' => 'uuid-call',
        'idempotencyKey' => 'call_uuid-call_12345',
        'deliveryId' => 'dlv_call1',
        'data' => [
            'callId' => '12345',
            'from' => '5511999999999@c.us',
            'isVideo' => true,
            'isGroup' => false,
            'timestamp' => 1724150000,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-call/messages/*/history?limit=50' => Http::response([], 200)]);

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-call', $payload, $headers);

    $response->assertStatus(200);

    $call = TopwebChatCall::where('call_id', '12345')->first();
    expect($call)->not->toBeNull();
    expect($call->conversation_id)->toBe($conversation->id);
    expect($call->from_jid)->toBe('5511999999999@c.us');
    expect($call->is_video)->toBeTrue();
    expect($call->status)->toBe('ringing');
    expect($call->started_at)->not->toBeNull();
});
```

### Teste 3: Webhook call.accepted/rejected/missed -> ProcessCallEnded

```php
it('processes call.accepted webhook', function () {
    $call = TopwebChatCall::factory()->create([
        'status' => 'ringing',
        'started_at' => now()->subMinutes(2),
    ]);

    $payload = [
        'event' => 'call.accepted',
        'sessionId' => 'uuid-call-end',
        'idempotencyKey' => 'call_uuid-call-end_12345_accepted_1724150200',
        'data' => [
            'sessionId' => 'uuid-call-end',
            'callId' => '12345',
            'from' => '5511999999999@c.us',
            'outcome' => 'accepted',
            'isVideo' => true,
            'isGroup' => false,
            'timestamp' => 1724150200,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-call-end/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-call-end', $payload, $headers);

    $call = $call->fresh();
    expect($call->status)->toBe('accepted');
    expect($call->ended_at)->not->toBeNull();
    expect($call->duration_seconds)->toBeGreaterThan(0);
});
```

```php
it('processes call.rejected and call.missed webhooks', function () {
    foreach (['rejected', 'missed'] as $outcome) {
        $call = TopwebChatCall::factory()->create(['status' => 'ringing', 'started_at' => now()->subMinutes(1)]);

        $payload = [
            'event' => 'call.' . $outcome,
            'sessionId' => 'uuid-call-' . $outcome,
            'idempotencyKey' => "call_uuid-call-{$outcome}_12345_{$outcome}_1724150200",
            'data' => [
                'sessionId' => 'uuid-call-' . $outcome,
                'callId' => '12345',
                'from' => '5511999999999@c.us',
                'outcome' => $outcome,
                'isVideo' => true,
                'isGroup' => false,
                'timestamp' => 1724150200,
            ],
        ];

        Http::fake(['openwa:2785/api/sessions/uuid-call-' . $outcome . '/messages/*/history?limit=50' => Http::response([], 200)]);

        $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-call-' . $outcome, $payload, $headers);

        $call = $call->fresh();
        expect($call->status)->toBe($outcome);
        expect($call->duration_seconds)->toBe(0);
    }
});
```

### Teste 4: UI - Badge "Chamada perdida" + Log de Chamadas

```php
it('shows missed call badge in conversation', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.access');

    $conversation = Conversation::factory()->create();
    TopwebChatCall::factory()->create([
        'conversation_id' => $conversation->id,
        'status' => 'missed',
        'is_video' => false,
        'started_at' => now()->subMinutes(10),
    ]);

    $response = $this->actingAs($operator)->getJson("/api/topweb-chat/conversations/{$conversation->id}/timeline");

    $timeline = $response->json('messages');
    $callEntry = collect($timeline)->firstWhere('type', 'call');
    expect($callEntry)->not->toBeNull();
    expect($callEntry['call_status'])->toBe('missed');
});
```

### Teste 5: Outbound - Rejeitar Chamada Ativa

```php
it('rejects active call via POST /reject', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.send');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-reject', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-reject/calls/12345/reject' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/reject-call", [
        'call_id' => '12345',
    ]);

    $response->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://openwa:2785/api/sessions/uuid-reject/calls/12345/reject'
            && $request->method() === 'POST';
    });
});
```

---

### Channels/Newsletters

### Teste 6: Entidade TopwebChatChannel

```php
it('creates Channel entity with correct schema', function () {
    $this->artisan('migrate --pretend')
        ->expectsOutputToContain('topweb_chat_channels')
        ->expectsOutputToContain('topweb_chat_channel_status');

    $this->artisan('migrate');

    expect(Schema::hasTable('topweb_chat_channels'))->toBeTrue();
    expect(Schema::hasTable('topweb_chat_channel_status'))->toBeTrue();
    expect(Schema::hasColumns('topweb_chat_channels', ['channel_jid', 'name', 'description', 'followers_count']))->toBeTrue();
});
```

### Teste 7: Webhook status.received -> ProcessChannelStatus

```php
it('processes status.received webhook (opt-in)', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-channel', 'status' => 'ready']);
    
    $channel = TopwebChatChannel::factory()->create([
        'instance_id' => $instance->id,
        'channel_jid' => '1234567890@newsletter',
        'name' => 'Newsletter Tech',
    ]);

    $payload = [
        'event' => 'status.received',
        'sessionId' => 'uuid-channel',
        'idempotencyKey' => 'status_uuid-channel_status123',
        'data' => [
            'sessionId' => 'uuid-channel',
            'statusId' => 'status123',
            'contact' => ['id' => '1234567890@newsletter', 'name' => 'Newsletter Tech', 'pushName' => 'Tech News'],
            'type' => 'image',
            'caption' => 'Nova versao lancada!',
            'hasMedia' => true,
            'mediaOmitted' => false,
            'postedAt' => 1724150000000,
            'expiresAt' => 1724236400000,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-channel/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-channel', $payload, $headers);

    $status = TopwebChatChannelStatus::where('channel_id', $channel->id)->first();
    expect($status)->not->toBeNull();
    expect($status->status_id)->toBe('status123');
    expect($status->type)->toBe('image');
    expect($status->caption)->toBe('Nova versao lancada!');
    expect($status->hasMedia)->toBeTrue();
    expect($status->posted_at)->toBe('2026-08-20 10:00:00');
});
```

### Teste 7: UI - Menu TopwebChat > Canais (Read-only)

```php
it('shows channels list for users with topweb_chat.channels.view permission', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.channels.view');

    $channels = TopwebChatChannel::factory()->count(2)->create();

    $response = $this->actingAs($admin)->get('/admin/topweb-chat/channels');
    
    $response->assertStatus(200);
    $response->assertSee('Canais/Newsletters');
    foreach ($channels as $c) {
        $response->assertSee($c->name);
    }
});
```

### Teste 8: ACL Separada para Calls/Channels

```php
it('enforces separate ACL for calls and channels', function () {
    $userCalls = User::factory()->create();
    $userCalls->givePermissionTo('topweb_chat.calls.view');

    $userChannels = User::factory()->create();
    $userChannels->givePermissionTo('topweb_chat.channels.view');

    $userNone = User::factory()->create();

    $response = $this->actingAs($userCalls)->get('/admin/topweb-chat/calls');
    $response->assertStatus(200);

    $response = $this->actingAs($userCalls)->get('/admin/topweb-chat/channels');
    $response->assertStatus(403);

    $response = $this->actingAs($userChannels)->get('/admin/topweb-chat/channels');
    $response->assertStatus(200);

    $response = $this->actingAs($userNone)->get('/admin/topweb-chat/calls');
    $response->assertStatus(403);
});
```

---

## 3. Interface Contracts

### Entities

```php
// app/Models/TopwebChatCall.php

class TopwebChatCall extends Model {
    protected $fillable = [
        'conversation_id', 'call_id', 'from_jid', 'is_video', 
        'is_group', 'status', 'started_at', 'ended_at', 'duration_seconds',
    ];
    protected $casts = [
        'is_video' => 'boolean',
        'is_group' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}

// app/Models/TopwebChatChannel.php

class TopwebChatChannel extends Model {
    protected $fillable = ['instance_id', 'channel_jid', 'name', 'description', 'followers_count'];
    public function statuses(): HasMany { return $this->hasMany(TopwebChatChannelStatus::class); }
}

// app/Models/TopwebChatChannelStatus.php

class TopwebChatChannelStatus extends Model {
    protected $fillable = ['channel_id', 'status_id', 'contact', 'type', 'caption', 'hasMedia', 'mediaOmitted', 'omitReason', 'postedAt', 'expiresAt'];
    protected $casts = ['contact' => 'array', 'postedAt' => 'datetime', 'expiresAt' => 'datetime'];
}
```

### OpenWaProvider Calls Methods

```php
// app/Services/Messaging/OpenWaProvider.php

public function rejectCall(string $sessionUuid, string $callId): array {
    return $this->post("sessions/{$sessionUuid}/calls/{$callId}/reject", []);
}
```

### Services

```php
// app/Services/TopwebChat/CallService.php
class CallService {
    public function processReceived(string $sessionUuid, array $data): void;
    public function processEnded(string $sessionUuid, array $data): void;
    public function rejectCall(string $sessionUuid, string $callId): void;
}

// app/Services/TopwebChat/ChannelService.php
class ChannelService {
    public function processStatusReceived(string $sessionUuid, array $data): void;
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Acao |
|---------|------|
| `database/migrations/xxxx_create_topweb_chat_calls_table.php` | **Criar** |
| `database/migrations/xxxx_create_topweb_chat_channels_table.php` | **Criar** |
| `database/migrations/xxxx_create_topweb_chat_channel_status_table.php` | **Criar** |
| `app/Models/TopwebChatCall.php` | **Criar** |
| `app/Models/TopwebChatChannel.php` | **Criar** |
| `app/Models/TopwebChatChannelStatus.php` | **Criar** |
| `app/Services/Messaging/OpenWaProvider.php` | Estender (calls methods) |
| `app/Services/TopwebChat/CallService.php` | **Criar** |
| `app/Services/TopwebChat/ChannelService.php` | **Criar** |
| `app/Http/Controllers/Admin/TopwebChat/CallController.php` | **Criar** |
| `app/Http/Controllers/Admin/TopwebChat/ChannelController.php` | **Criar** |
| `packages/Webkul/TopwebChat/src/Resources/views/admin/calls/` | **Criar** |
| `packages/Webkul/TopwebChat/src/Resources/views/admin/channels/` | **Criar** |
| `routes/api.php` | Registrar rotas calls/channels |
| `routes/web.php` | Registrar rotas admin calls/channels |
| `config/topweb-chat.php` | Adicionar permissoes calls/channels |
| `tests/Feature/Domain/CallsChannelsDomainTest.php` | **Criar** |
| `tests/Feature/Admin/CallsChannelsAdminTest.php` | **Criar** |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::fake([
        'openwa:2785/api/sessions/*/calls/*' => Http::response([], 404),
        'openwa:2785/api/sessions/*/messages/*/history?limit=50' => Http::response([], 200),
    ]);
});
```

---

## 6. Evidencias GREEN

```bash
php artisan migrate
php artisan test tests/Feature/Domain/CallsChannelsDomainTest.php
php artisan test tests/Feature/Admin/CallsChannelsAdminTest.php
./vendor/bin/pint
```

---

## Proximo Slice

Apos GREEN -> **#22 PIX Domain** (auditoria financeira).