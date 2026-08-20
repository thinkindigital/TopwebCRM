# TDD Specification — Slice #02: Message Status Reconciliation

> **Fase**: RED (especificação de testes antes da implementação)
> **Dependência**: #01 Instance Status Reconciliation (precisa `Instance.status='ready'` e `engine_loaded=true`)

---

## 1. Comportamentos Críticos (Scope)

| Comportamento | Descrição | Prioridade |
|---------------|-----------|------------|
| **History Command** | `php artisan topweb-chat:reconcile --history --limit=20` executa para instâncias `ready` | P0 |
| **History Fetch** | Para msgs `status IN ('sent','delivered')` + `updated_at > 10min` sem ack → `GET /api/sessions/:sessionId/messages/:chatId/history?limit=50` | P0 |
| **Status Comparison** | Compara `status` remoto vs local por `provider_message_id` | P0 |
| **Monotonic Update** | Atualiza `Message.status` local: `sent`→`delivered`→`read`; **nunca rebaixa** `delivered`/`read` | P0 |
| **Failed Terminal** | Se remoto=`failed` → local=`failed` + `last_error`; **failed nunca volta** para `sent`/`delivered` | P0 |
| **Conversation Sync** | Atualiza `Conversation.last_message_at` + recalcula `unread_count` (inbound `status != 'read'`) | P0 |
| **Scheduler** | Job registrado `everyFiveMinutes()` com `--limit=20` | P0 |
| **Audit Log** | Log `reconciliation.message_status` com `message_id`, `before_status`, `after_status`, `source=openwa_history` | P0 |

---

## 2. Test Specification (RED Phase)

### Teste 1: Command Executa Para Instâncias Ready
```php
// tests/Feature/Reconciliation/MessageStatusReconciliationTest.php

it('executes history reconcile for ready instances only', function () {
    // Arrange
    $readyInstance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-ready',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);
    
    $disconnectedInstance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-disc',
        'status' => 'disconnected',
        'engine_loaded' => false,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $readyInstance->id,
        'remote_jid_key' => 'hash-123',
    ]);

    // Messages needing reconciliation: sent/delivered > 10min ago
    $msgSent = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'sent',
        'provider_message_id' => 'true_5511999999999@c.us_ABC123',
        'updated_at' => now()->subMinutes(15),
    ]);

    $msgDelivered = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'delivered',
        'provider_message_id' => 'true_5511999999999@c.us_DEF456',
        'updated_at' => now()->subMinutes(20),
    ]);

    // Message NOT needing reconciliation (recent)
    $msgRecent = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'sent',
        'provider_message_id' => 'true_5511999999999@c.us_RECENT',
        'updated_at' => now()->subMinutes(5),
    ]);

    // Mock OpenWA history response
    Http::fake([
        'openwa:2785/api/sessions/uuid-ready/messages/*/history?limit=50' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_ABC123',
                'status' => 'delivered',  // sent -> delivered
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
            [
                'id' => 'true_5511999999999@c.us_DEF456',
                'status' => 'read',       // delivered -> read
                'timestamp' => 1724150100,
                'fromMe' => true,
            ],
            [
                'id' => 'true_5511999999999@c.us_RECENT',
                'status' => 'sent',
                'timestamp' => 1724150200,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    // Act
    $this->artisan('topweb-chat:reconcile --history --limit=20')
        ->assertExitCode(0);

    // Assert: only ready instance processed
    Http::assertSentCount(1);
    
    // Assert: messages updated
    expect($msgSent->fresh()->status)->toBe('delivered');
    expect($msgDelivered->fresh()->status)->toBe('read');
    expect($msgRecent->fresh()->status)->toBe('sent'); // unchanged (too recent)
});
```

### Teste 2: Status Monotônico — Nunca Rebaixa Delivered/Read
```php
it('never downgrades delivered/read status (monotonic)', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-mono',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Local says 'read' (more advanced), remote says 'delivered' (less advanced)
    $msgLocalRead = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'read',
        'provider_message_id' => 'true_5511999999999@c.us_READ123',
        'updated_at' => now()->subMinutes(15),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-mono/messages/*/history?limit=50' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_READ123',
                'status' => 'delivered',  // REMOTE says delivered (less advanced)
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    // Assert: local status UNCHANGED (read > delivered)
    expect($msgLocalRead->fresh()->status)->toBe('read');
});
```

### Teste 3: Failed Terminal — Nunca Volta de Failed
```php
it('never recovers from failed status (terminal)', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-failed-term',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Local already failed
    $msgFailed = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'failed',
        'last_error' => 'Previous error',
        'provider_message_id' => 'true_5511999999999@c.us_FAILED123',
        'updated_at' => now()->subMinutes(15),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-failed-term/messages/*/history?limit=50' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_FAILED123',
                'status' => 'delivered',  // REMOTE says delivered (recovered?)
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    // Assert: status remains FAILED (terminal)
    expect($msgFailed->fresh()->status)->toBe('failed');
    expect($msgFailed->fresh()->last_error)->toBe('Previous error');
});
```

### Teste 4: Remote Failed → Local Failed + Last Error
```php
it('sets local failed with last_error when remote reports failed', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-remote-failed',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msgSent = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'sent',
        'provider_message_id' => 'true_5511999999999@c.us_REMOTEFAIL',
        'updated_at' => now()->subMinutes(15),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-remote-failed/messages/*/history?limit=50' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_REMOTEFAIL',
                'status' => 'failed',
                'timestamp' => 1724150000,
                'fromMe' => true,
                // OpenWA may include error info in message or we fetch separately
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    $msg = $msgSent->fresh();
    expect($msg->status)->toBe('failed');
    expect($msg->last_error)->not->toBeNull();
    expect($msg->last_error)->toContain('failed');
});
```

### Teste 5: Conversation Sync — Last Message At + Unread Count
```php
it('updates Conversation.last_message_at and recalculates unread_count', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-conv-sync',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'unread_count' => 0, // stale
        'last_message_at' => now()->subDays(1), // stale
    ]);

    // Inbound messages not read
    $inboundUnread1 = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered', // not read
        'created_at' => now()->subMinutes(30),
    ]);

    $inboundUnread2 = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
        'created_at' => now()->subMinutes(10),
    ]);

    // Inbound read (should not count)
    $inboundRead = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'read',
        'created_at' => now()->subMinutes(5),
    ]);

    // Outbound (should not count for unread)
    $outbound = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'delivered',
        'created_at' => now()->subMinutes(20),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-conv-sync/messages/*/history?limit=50' => Http::response([
            // No status changes needed, just testing conversation sync
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    $conv = $conversation->fresh();
    
    // Assert: unread_count = inbound messages where status != 'read'
    expect($conv->unread_count)->toBe(2);
    
    // Assert: last_message_at = max created_at of all messages
    expect($conv->last_message_at)->toBeCloseTo(now(), 1); // within 1 second
});
```

### Teste 6: Scheduler Registration EveryFiveMinutes
```php
// tests/Feature/Scheduler/ReconciliationHistorySchedulerTest.php

it('registers reconcile --history job everyFiveMinutes with limit 20', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    
    $this->artisan('schedule:run')->assertExitCode(0);
    
    $commands = $schedule->commands();
    $reconcileHistory = collect($commands)->firstWhere('command', 'topweb-chat:reconcile --history --limit=20');
    
    expect($reconcileHistory)->not->toBeNull();
    expect($reconcileHistory->getExpression())->toBe('*/5 * * * *'); // everyFiveMinutes
});
```

### Teste 7: Audit Log Message Status Changes
```php
it('logs audit entry for each message status change', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-audit-msg',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'sent',
        'provider_message_id' => 'true_5511999999999@c.us_AUDIT123',
        'updated_at' => now()->subMinutes(15),
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-audit-msg/messages/*/history?limit=50' => Http::response([
            [
                'id' => 'true_5511999999999@c.us_AUDIT123',
                'status' => 'delivered',
                'timestamp' => 1724150000,
                'fromMe' => true,
            ],
        ], 200),
    ]);

    $auditLogs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$auditLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.message_status') {
            $auditLogs[] = $context;
        }
    });

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    expect($auditLogs)->toHaveCount(1);
    $log = $auditLogs[0];
    expect($log['event'])->toBe('reconciliation.message_status');
    expect($log['message_id'])->toBe($msg->id);
    expect($log['provider_message_id'])->toBe('true_5511999999999@c.us_AUDIT123');
    expect($log['before_status'])->toBe('sent');
    expect($log['after_status'])->toBe('delivered');
    expect($log['source'])->toBe('openwa_history');
    expect($log['conversation_id'])->toBe($msg->conversation_id);
});
```

### Teste 8: Ignora Instâncias Não-Ready
```php
it('skips instances that are not ready', function () {
    $ready = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-ready-2',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);
    
    $disconnected = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-disc-2',
        'status' => 'disconnected',
        'engine_loaded' => false,
    ]);
    
    $failed = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-failed-2',
        'status' => 'failed',
        'engine_loaded' => false,
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-ready-2/messages/*/history?limit=50' => Http::response([], 200),
        // Should NOT call for disconnected/failed
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    // Only ready instance called
    Http::assertSentCount(1);
});
```

### Teste 9: Limite de Conversas (--limit)
```php
it('respects --limit parameter for conversation count', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-limit',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    // Create 30 conversations with messages needing reconciliation
    for ($i = 0; $i < 30; $i++) {
        $conv = Conversation::factory()->create(['instance_id' => $instance->id]);
        Message::factory()->create([
            'conversation_id' => $conv->id,
            'direction' => 'out',
            'status' => 'sent',
            'provider_message_id' => "true_5511999999999@c.us_MSG{$i}",
            'updated_at' => now()->subMinutes(15),
        ]);
    }

    Http::fake([
        'openwa:2785/api/sessions/uuid-limit/messages/*/history?limit=50' => Http::response([], 200),
    ]);

    // Limit 5
    $this->artisan('topweb-chat:reconcile --history --limit=5');

    // Should only process 5 conversations (5 API calls)
    Http::assertSentCount(5);
});
```

### Teste 10: Error Handling — Network/5xx Não Para Outras
```php
it('continues other conversations when one fails with 5xx', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-mixed',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $convOk = Conversation::factory()->create(['instance_id' => $instance->id]);
    $convFail = Conversation::factory()->create(['instance_id' => $instance->id]);

    Message::factory()->create([
        'conversation_id' => $convOk->id,
        'direction' => 'out',
        'status' => 'sent',
        'provider_message_id' => 'true_5511999999999@c.us_OK',
        'updated_at' => now()->subMinutes(15),
    ]);

    Message::factory()->create([
        'conversation_id' => $convFail->id,
        'direction' => 'out',
        'status' => 'sent',
        'provider_message_id' => 'true_5511999999999@c.us_FAIL',
        'updated_at' => now()->subMinutes(15),
    ]);

    Http::fake([
        // First conversation OK
        'openwa:2785/api/sessions/uuid-mixed/messages/' . $convOk->remote_jid_key . '/history?limit=50' => Http::response([
            ['id' => 'true_5511999999999@c.us_OK', 'status' => 'delivered', 'fromMe' => true],
        ], 200),
        // Second conversation fails
        'openwa:2785/api/sessions/uuid-mixed/messages/' . $convFail->remote_jid_key . '/history?limit=50' => Http::response([
            'message' => 'Internal Server Error',
        ], 500),
    ]);

    $errorLogs = [];
    Log::shouldReceive('error')->andReturnUsing(function ($message, $context) use (&$errorLogs) {
        $errorLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --history --limit=20')->assertExitCode(0);

    // OK conversation processed
    expect(Message::where('provider_message_id', 'true_5511999999999@c.us_OK')->first()->status)->toBe('delivered');
    
    // Error logged but command continues
    expect($errorLogs)->toHaveCount(1);
    expect($errorLogs[0]['context'])->toContainKeys(['conversation_id', 'error']);
});
```

---

## 3. Interface Contracts

### Command Option
```php
// topweb-chat:reconcile --history --limit=20
```

### OpenWaProvider — History Method
```php
// app/Services/Messaging/OpenWaProvider.php

/**
 * Get chat history from OpenWA
 * @param string $sessionUuid
 * @param string $chatId
 * @param int $limit
 * @return array<int, array{id, status, timestamp, fromMe, ...}>
 */
public function getChatHistory(string $sessionUuid, string $chatId, int $limit = 50): array;
```

### Service: MessageReconciliationService
```php
// app/Services/TopwebChat/MessageReconciliationService.php

class MessageReconciliationService {
    public function __construct(
        protected OpenWaProvider $provider,
        protected MessageRepository $messages,
        protected ConversationRepository $conversations,
        protected ReconciliationLogger $logger
    ) {}

    /** @return array{updated: int, failed: int, skipped: int} */
    public function reconcileHistory(int $limit = 20): array;
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Console/Commands/TopwebChatReconcile.php` | Estender (opção `--history --limit`) |
| `app/Services/Messaging/OpenWaProvider.php` | Estender (`getChatHistory()`) |
| `app/Services/TopwebChat/MessageReconciliationService.php` | Criar |
| `routes/console.php` | Registrar `everyFiveMinutes()` |
| `database/migrations/xxxx_add_reconcile_fields_to_messages.php` | Criar (coluna `reconcile_attempts`) |
| `tests/Feature/Reconciliation/MessageStatusReconciliationTest.php` | Criar (10 testes) |
| `tests/Feature/Scheduler/ReconciliationHistorySchedulerTest.php` | Criar |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'openwa:2785/api/sessions/*/messages/*/history?limit=50' => Http::response([], 404),
    ]);
});

afterEach(function () {
    Http::assertSentCount(0);
});
```

---

## 6. Evidências GREEN

```bash
php artisan topweb-chat:reconcile --history --limit=20
php artisan schedule:run --dry-run  # mostra everyFiveMinutes
php artisan test tests/Feature/Reconciliation/MessageStatusReconciliationTest.php  # 10 pass
./vendor/bin/pint  # 0 erros
```

---

## 7. Próximo Slice

Após GREEN → **#03 Unknown Messages Reconciliation** (usa mesma infra de history).