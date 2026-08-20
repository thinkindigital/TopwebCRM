# TDD Specification — Slice #03: Unknown Messages Reconciliation

> **Fase**: RED (especificação de testes antes da implementação)
> **Dependência**: #02 Message Status Reconciliation (usa mesma infra de history)

---

## 1. Comportamentos Críticos (Scope)

| Comportamento | Descrição | Prioridade |
|---------------|-----------|------------|
| **Unknown Command** | `php artisan topweb-chat:reconcile --unknown` (ou flag `--history --include-unknown`) | P0 |
| **Migration** | Coluna `reconcile_attempts` (integer, default 0) em `topweb_chat_messages` | P0 |
| **Attempt Limit** | Processa apenas msgs `status='unknown'` + `reconcile_attempts < 5` | P0 |
| **History Lookup** | `GET /api/sessions/:sessionId/messages/:chatId/history?limit=100` por `provider_message_id` | P0 |
| **Remote Found → Update** | `delivered`/`read` → update status (monotônico); `failed` → failed + last_error; `sent` → mantém unknown | P0 |
| **Remote Not Found** | `reconcile_attempts++`, mantém `unknown` | P0 |
| **Max Attempts** | `reconcile_attempts >= 5` → alerta + mantém unknown (NÃO auto-converte failed) | P0 |
| **Audit Log** | Log `reconciliation.unknown_message` com `attempt`, `remote_status`, `action` | P0 |

---

## 2. Test Specification (RED Phase)

### Teste 1: Migration Adiciona Coluna reconcile_attempts
```php
// tests/Feature/Reconciliation/UnknownMessagesReconciliationTest.php

it('migration adds reconcile_attempts column to topweb_chat_messages', function () {
    $this->artisan('migrate --pretend')
        ->expectsOutputToContain('reconcile_attempts');
    
    // After migration
    $this->artisan('migrate');
    
    expect(Schema::hasColumn('topweb_chat_messages', 'reconcile_attempts'))->toBeTrue();
    expect(Schema::getColumnType('topweb_chat_messages', 'reconcile_attempts'))->toBe('integer');
    expect(DB::table('topweb_chat_messages')->value('reconcile_attempts'))->toBe(0); // default
});
```

### Teste 2: Command --unknown Executa
```php
it('executes reconcile --unknown for unknown messages', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unknown',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 0,
        'provider_message_id' => 'true_5511999999999@c.us_UNKNOWN123',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unknown/messages/*/history?limit=100' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_UNKNOWN123',
                'status' => 'delivered',
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown')
        ->assertExitCode(0);

    expect($msgUnknown->fresh()->status)->toBe('delivered');
    expect($msgUnknown->fresh()->reconcile_attempts)->toBe(1);
});
```

### Teste 3: Remote Delivered/Read → Update Status (Monotônico)
```php
it('updates unknown to delivered/read when remote confirms', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-delivered',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 0,
        'provider_message_id' => 'true_5511999999999@c.us_UNK2DEL',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-delivered/messages/*/history?limit=100' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_UNK2DEL',
                'status' => 'delivered',
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown');

    expect($msgUnknown->fresh()->status)->toBe('delivered');
    expect($msgUnknown->fresh()->reconcile_attempts)->toBe(1);
});
```

### Teste 4: Remote Failed → Failed + Last Error
```php
it('sets failed with last_error when remote reports failed', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-failed',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 0,
        'provider_message_id' => 'true_5511999999999@c.us_UNK2FAIL',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-failed/messages/*/history?limit=100' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_UNK2FAIL',
                'status' => 'failed',
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown');

    $msg = $msgUnknown->fresh();
    expect($msg->status)->toBe('failed');
    expect($msg->last_error)->not->toBeNull();
    expect($msg->last_error)->toContain('failed');
});
```

### Teste 5: Remote Sent → Mantém Unknown (Ainda Sem Ack Final)
```php
it('keeps unknown when remote still shows sent (no final ack yet)', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-still-sent',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 2,
        'provider_message_id' => 'true_5511999999999@c.us_UNKSTILLSENT',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-still-sent/messages/*/history?limit=100' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_UNKSTILLSENT',
                'status' => 'sent',  // still no final ack
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown');

    $msg = $msgUnknown->fresh();
    expect($msg->status)->toBe('unknown'); // unchanged
    expect($msg->reconcile_attempts)->toBe(3); // incremented
});
```

### Teste 6: Remote Not Found → Increment Attempts
```php
it('increments reconcile_attempts when message not found in remote history', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-notfound',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 1,
        'provider_message_id' => 'true_5511999999999@c.us_NOTFOUND',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-notfound/messages/*/history?limit=100' => Http::response([
            // Empty history - message not found
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown');

    $msg = $msgUnknown->fresh();
    expect($msg->status)->toBe('unknown');
    expect($msg->reconcile_attempts)->toBe(2);
});
```

### Teste 7: Max Attempts (5) → Alerta + Mantém Unknown (NÃO Auto-Failed)
```php
it('alerts and keeps unknown at max attempts (5), never auto-converts to failed', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-max-attempts',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Already at 4 attempts
    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 4, // 4th attempt
        'provider_message_id' => 'true_5511999999999@c.us_MAXATT',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-max-attempts/messages/*/history?limit=100' => Http::response([
            // Not found
        ], 200),
    ]);

    $alertLogs = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$alertLogs) {
        $alertLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --unknown');

    $msg = $msgUnknown->fresh();
    
    // Assert: still unknown (not auto-converted to failed)
    expect($msg->status)->toBe('unknown');
    expect($msg->reconcile_attempts)->toBe(5); // incremented to 5
    
    // Assert: warning logged
    expect($alertLogs)->toHaveCount(1);
    expect($alertLogs[0]['context'])->toContainKeys([
        'message_id', 'provider_message_id', 'attempts', 'action', 'max_reached'
    ]);
    expect($alertLogs[0]['context']['action'])->toBe('max_attempts_reached');
    expect($alertLogs[0]['context']['max_reached'])->toBeTrue();
});
```

### Teste 8: Max Attempts Não Auto-Converte Para Failed
```php
it('NEVER auto-converts unknown to failed even at max attempts', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-max-no-fail',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 4,
        'provider_message_id' => 'true_5511999999999@c.us_NEVERFAIL',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-max-no-fail/messages/*/history?limit=100' => Http::response([], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown');

    // Even at attempt 5, status remains UNKNOWN (never auto-failed)
    expect($msgUnknown->fresh()->status)->toBe('unknown');
    expect($msgUnknown->fresh()->reconcile_attempts)->toBe(5);
    
    // Verify no failed status was ever set
    $msg = Message::find($msgUnknown->id);
    expect($msg->status)->not->toBe('failed');
});
```

### Teste 8: Audit Log Unknown Message Reconciliation
```php
it('logs audit entry for unknown message reconciliation', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-audit',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 0,
        'provider_message_id' => 'true_5511999999999@c.us_UNK_AUDIT',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-audit/messages/*/history?limit=100' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_UNK_AUDIT',
                'status' => 'delivered',
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $auditLogs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$auditLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.unknown_message') {
            $auditLogs[] = $context;
        }
    });

    $this->artisan('topweb-chat:reconcile --unknown');

    expect($auditLogs)->toHaveCount(1);
    $log = $auditLogs[0];
    expect($log['event'])->toBe('reconciliation.unknown_message');
    expect($log['message_id'])->toBe($msgUnknown->id);
    expect($log['provider_message_id'])->toBe('true_5511999999999@c.us_UNK_AUDIT');
    expect($log['attempt'])->toBe(1);
    expect($log['remote_status'])->toBe('delivered');
    expect($log['action'])->toBe('updated_to_delivered');
    expect($log['previous_attempts'])->toBe(0);
    expect($log['new_attempts'])->toBe(1);
});
```

### Teste 9: Max Attempts Alert Log
```php
it('logs specific alert when max attempts reached', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-unk-alert',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgUnknown = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'unknown',
        'reconcile_attempts' => 4,
        'provider_message_id' => 'true_5511999999999@c.us_ALERT',
        'updated_at' => now()->subMinutes(30),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-unk-alert/messages/*/history?limit=100' => Http::response([], 200),
    ]);

    $alertLogs = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$alertLogs) {
        $alertLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --unknown');

    expect($alertLogs)->toHaveCount(1);
    $alert = $alertLogs[0];
    expect($alert['context']['event'])->toBe('reconciliation.unknown_message');
    expect($alert['context']['action'])->toBe('max_attempts_reached');
    expect($alert['context']['max_reached'])->toBeTrue();
    expect($alert['context']['attempts'])->toBe(5);
    expect($alert['context']['provider_message_id'])->toBe('true_5511999999999@c.us_ALERT');
});
```

### Teste 10: Only Processes Unknown with Attempts < 5
```php
it('only processes unknown messages with reconcile_attempts < 5', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-filter',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Should process (attempts=0,1,2,3,4)
    for ($i = 0; $i < 5; $i++) {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'status' => 'unknown',
            'reconcile_attempts' => $i,
            'provider_message_id' => "true_5511999999999@c.us_PROC{$i}",
        ]);
    }

    // Should NOT process (attempts=5,6)
    for ($i = 5; $i < 7; $i++) {
        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'status' => 'unknown',
            'reconcile_attempts' => $i,
            'provider_message_id' => "true_5511999999999@c.us_SKIP{$i}",
        ]);
    }

    Http::fake([
        'openwa:2785/api/sessions/uuid-filter/messages/*/history?limit=100' => Http::response([
            ['id' => 'true_5511999999999@c.us_PROC0', 'status' => 'delivered', 'fromMe' => true],
            ['id' => 'true_5511999999999@c.us_PROC1', 'status' => 'delivered', 'fromMe' => true],
            ['id' => 'true_5511999999999@c.us_PROC2', 'status' => 'delivered', 'fromMe' => true],
            ['id' => 'true_5511999999999@c.us_PROC3', 'status' => 'delivered', 'fromMe' => true],
            ['id' => 'true_5511999999999@c.us_PROC4', 'status' => 'delivered', 'fromMe' => true],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --unknown');

    // Only 5 processed (attempts 0-4)
    for ($i = 0; $i < 5; $i++) {
        $msg = Message::where('provider_message_id', "true_5511999999999@c.us_PROC{$i}")->first();
        expect($msg->status)->toBe('delivered');
        expect($msg->reconcile_attempts)->toBe($i + 1);
    }

    // 2 skipped (attempts 5,6)
    for ($i = 5; $i < 7; $i++) {
        $msg = Message::where('provider_message_id', "true_5511999999999@c.us_SKIP{$i}")->first();
        expect($msg->status)->toBe('unknown');
        expect($msg->reconcile_attempts)->toBe($i);
    }
});
```

---

## 3. Interface Contracts

### Command Option
```php
// topweb-chat:reconcile --unknown
// OU integrado: topweb-chat:reconcile --history --include-unknown
```

### Migration
```php
// database/migrations/xxxx_add_reconcile_attempts_to_messages.php

Schema::table('topweb_chat_messages', function (Blueprint $table) {
    $table->unsignedInteger('reconcile_attempts')->default(0)->after('last_error');
});
```

### OpenWaProvider — History com Limit Maior
```php
// OpenWaProvider::getChatHistory(string $sessionUuid, string $chatId, int $limit = 100): array
// Para unknown messages usamos limit=100 (busca mais ampla)
```

### Service: UnknownMessageReconciliationService
```php
// app/Services/TopwebChat/UnknownMessageReconciliationService.php

class UnknownMessageReconciliationService {
    public function __construct(
        protected OpenWaProvider $provider,
        protected MessageRepository $messages,
        protected ReconciliationLogger $logger
    ) {}

    /** @return array{updated: int, alerted: int, skipped: int} */
    public function reconcileUnknown(): array;
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `database/migrations/xxxx_add_reconcile_attempts_to_messages.php` | **Criar** |
| `app/Console/Commands/TopwebChatReconcile.php` | Estender (opção `--unknown`) |
| `app/Services/TopwebChat/UnknownMessageReconciliationService.php` | **Criar** |
| `app/Services/TopwebChat/ReconciliationLogger.php` | Estender (log unknown) |
| `tests/Feature/Reconciliation/UnknownMessagesReconciliationTest.php` | **Criar** (10 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'openwa:2785/api/sessions/*/messages/*/history?limit=100' => Http::response([], 404),
    ]);
});
```

---

## 6. Evidências GREEN

```bash
php artisan migrate
php artisan topweb-chat:reconcile --unknown
php artisan test tests/Feature/Reconciliation/UnknownMessagesReconciliationTest.php  # 10 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#04 Conversation Drift Reconciliation** (usa mesma infra + unread_count logic).