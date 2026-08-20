# TDD Specification — Slice #04: Conversation Drift Reconciliation

> **Fase**: RED (especificação de testes antes da implementação)
> **Dependência**: #02 Message Status Reconciliation (precisa status atualizados para count correto)

---

## 1. Comportamentos Críticos (Scope)

| Comportamento | Descrição | Prioridade |
|---------------|-----------|------------|
| **Unread Count Recalc** | `unread_count` = count `Message` onde `conversation_id`, `direction='in'`, `status != 'read'` | P0 |
| **Last Message At** | `last_message_at` = max(`Message.created_at`) onde `direction='in'` | P0 |
| **Auto Execution** | Executado no job `--history` (everyFiveMinutes) + opcional `--state` | P0 |
| **Audit Log** | Log `reconciliation.conversation_drift` com `conversation_id`, `before_count`, `after_count`, `before_last_message_at`, `after_last_message_at` | P0 |

---

## 2. Test Specification (RED Phase)

### Teste 1: Unread Count = Inbound Status != Read
```php
// tests/Feature/Reconciliation/ConversationDriftReconciliationTest.php

it('recalculates unread_count as inbound messages with status != read', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-conv-drift',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'unread_count' => 99, // stale value
    ]);

    // Create inbound messages with various statuses
    Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered', // unread
    ]);

    Message::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'received', // unread
    ]);

    Message::factory()->count(4)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'read', // NOT unread
    ]);

    // Outbound messages (should NOT count)
    Message::factory()->count(5)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'delivered',
    ]);

    // Execute reconciliation (part of --history job)
    $this->artisan('topweb-chat:reconcile --history --limit=20');

    // Assert: unread_count = inbound where status != 'read' (3 + 2 = 5)
    expect($conversation->fresh()->unread_count)->toBe(5);
});
```

### Teste 2: Last Message At = Max Inbound Created At
```php
it('sets last_message_at to max created_at of inbound messages', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-last-msg',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'last_message_at' => now()->subDays(5), // stale
    ]);

    // Inbound messages at different times
    $oldMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
        'created_at' => now()->subHours(3),
    ]);

    $newMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
        'created_at' => now()->subMinutes(10),
    ]);

    $midMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'read', // read but still counts for last_message_at
        'created_at' => now()->subHours(1),
    ]);

    // Outbound (should NOT affect last_message_at)
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'delivered',
        'created_at' => now()->subMinutes(5), // newer than inbound but outbound
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    $conv = $conversation->fresh();
    
    // Assert: last_message_at = max inbound created_at (newMsg at -10min)
    expect($conv->last_message_at)->toBeCloseTo($newMsg->created_at, 1);
    expect($conv->last_message_at)->not->toBeCloseTo(now()->subMinutes(5), 1); // not outbound
});
```

### Teste 3: Only Inbound Messages Count for Last Message At
```php
it('only considers inbound messages for last_message_at', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-inbound-only',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Only outbound messages
    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'delivered',
        'created_at' => now()->subMinutes(5),
    ]);

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'status' => 'sent',
        'created_at' => now()->subMinutes(10),
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    $conv = $conversation->fresh();
    
    // No inbound messages → last_message_at should be null or unchanged
    expect($conv->last_message_at)->toBeNull();
});
```

### Teste 4: Executed Automatically in --history Job
```php
it('runs automatically as part of --history reconciliation job', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-auto-drift',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'unread_count' => 0,
        'last_message_at' => now()->subDays(1),
    ]);

    Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
    ]);

    // Mock OpenWA history (needed for --history job)
    Http::fake([
        'openwa:2785/api/sessions/uuid-auto-drift/messages/*/history?limit=50' => Http::response([], 200),
    ]);

    // Execute --history (includes conversation drift)
    $this->artisan('topweb-chat:reconcile --history --limit=20');

    expect($conversation->fresh()->unread_count)->toBe(3);
    expect($conversation->fresh()->last_message_at)->not->toBeNull();
});
```

### Teste 5: Audit Log Conversation Drift
```php
it('logs audit entry with before/after counts', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-drift-audit',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'unread_count' => 10, // stale high
        'last_message_at' => now()->subDays(2),
    ]);

    Message::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-drift-audit/messages/*/history?limit=50' => Http::response([], 200),
    ]);

    $auditLogs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$auditLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.conversation_drift') {
            $auditLogs[] = $context;
        }
    });

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    expect($auditLogs)->toHaveCount(1);
    $log = $auditLogs[0];
    expect($log['event'])->toBe('reconciliation.conversation_drift');
    expect($log['conversation_id'])->toBe($conversation->id);
    expect($log['before_unread_count'])->toBe(10);
    expect($log['after_unread_count'])->toBe(2);
    expect($log['before_last_message_at'])->not->toBeNull();
    expect($log['after_last_message_at'])->not->toBeNull();
    expect($log['drift_detected'])->toBeTrue();
});
```

### Teste 6: No Drift When Counts Match
```php
it('logs no drift when counts already match', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-no-drift',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'unread_count' => 3, // already correct
    ]);

    Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-no-drift/messages/*/history?limit=50' => Http::response([], 200),
    ]);

    $auditLogs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$auditLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.conversation_drift') {
            $auditLogs[] = $context;
        }
    });

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    expect($auditLogs)->toHaveCount(1);
    expect($auditLogs[0]['drift_detected'])->toBeFalse();
    expect($auditLogs[0]['before_unread_count'])->toBe(3);
    expect($auditLogs[0]['after_unread_count'])->toBe(3);
});
```

### Teste 7: Handles Conversations With No Messages
```php
it('handles conversations with no messages gracefully', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-empty-conv',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create([
        'instance_id' => $instance->id,
        'unread_count' => 5, // stale
        'last_message_at' => now()->subDays(1),
    ]);

    // No messages at all

    Http::fake([
        'openwa:2785/api/sessions/uuid-empty-conv/messages/*/history?limit=50' => Http::response([], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --history --limit=20');

    $conv = $conversation->fresh();
    expect($conv->unread_count)->toBe(0);
    expect($conv->last_message_at)->toBeNull();
});
```

### Teste 8: Scheduler EveryFiveMinutes
```php
// tests/Feature/Scheduler/ReconciliationDriftSchedulerTest.php

it('conversation drift runs everyFiveMinutes as part of history job', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    
    $this->artisan('schedule:run')->assertExitCode(0);
    
    $commands = $schedule->commands();
    $reconcileHistory = collect($commands)->firstWhere('command', 'topweb-chat:reconcile --history --limit=20');
    
    expect($reconcileHistory)->not->toBeNull();
    expect($reconcileHistory->getExpression())->toBe('*/5 * * * *'); // everyFiveMinutes
});
```

---

## 3. Interface Contracts

### Service Method
```php
// app/Services/TopwebChat/ConversationDriftService.php

class ConversationDriftService {
    public function __construct(
        protected ConversationRepository $conversations,
        protected MessageRepository $messages,
        protected ReconciliationLogger $logger
    ) {}

    /** @return array{reconciled: int, drift_detected: int} */
    public function reconcileDrift(): array;
}
```

### Integrated in History Job
```php
// app/Console/Commands/TopwebChatReconcile.php

public function handle() {
    if ($this->option('history')) {
        $this->reconcileHistory($this->option('limit'));
        $this->reconcileConversationDrift(); // NOVO
    }
    // ...
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Services/TopwebChat/ConversationDriftService.php` | **Criar** |
| `app/Console/Commands/TopwebChatReconcile.php` | Estender (chama drift no --history) |
| `app/Services/TopwebChat/ReconciliationLogger.php` | Estender (log drift) |
| `tests/Feature/Reconciliation/ConversationDriftReconciliationTest.php` | **Criar** (8 testes) |
| `tests/Feature/Scheduler/ReconciliationDriftSchedulerTest.php` | **Criar** |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'openwa:2785/api/sessions/*/messages/*/history?limit=50' => Http::response([], 404),
    ]);
});
```

---

## 6. Evidências GREEN

```bash
php artisan topweb-chat:reconcile --history --limit=20
php artisan test tests/Feature/Reconciliation/ConversationDriftReconciliationTest.php  # 8 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#05 Webhook Delivery Failures** (monitoramento + UI admin).