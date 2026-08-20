# TDD Specification — Slice #01: Instance Status Reconciliation

> **Fase**: RED (especificação de testes antes da implementação)
> **Objetivo**: Definir comportamentos testáveis via API pública (Artisan command + Scheduler + OpenWaProvider)

---

## 1. Comportamentos Críticos (Scope)

| Comportamento | Descrição | Prioridade |
|---------------|-----------|------------|
| **Reconcile Command** | `php artisan topweb-chat:reconcile --state` executa para todas instâncias `enabled=true` | P0 |
| **OpenWA Sync** | Para cada instância: `GET /api/sessions/:sessionId` → compara campos | P0 |
| **Status Update** | Atualiza `Instance` local se `status`, `engineLoaded`, `restriction`, `lastActive`, `phone`, `pushName` divergirem | P0 |
| **Failed Handling** | `status='failed'` → log warning + notificação admin + **NÃO** auto-recovery (INV-7) | P0 |
| **Disconnected Auto-Start** | `status='disconnected'` + `engineLoaded=false` + config `auto_start=true` → `POST /start` | P1 |
| **Scheduler Registration** | Job registrado em `routes/console.php` com `everyMinute()` | P0 |
| **Audit Log** | Log `reconciliation.instance_status` com `before`/`after` por instância | P0 |

---

## 2. Test Specification (RED Phase)

### Teste 1: Command Executa Sem Erros
```php
// tests/Feature/Reconciliation/InstanceStatusReconciliationTest.php

it('executes reconcile command without errors for enabled instances', function () {
    // Arrange
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-123',
        'provider' => 'openwa',
    ]);
    
    Http::fake([
        'openwa:2785/api/sessions/uuid-123' => Http::response([
            'id' => 'uuid-123',
            'name' => 'crm-topweb',
            'status' => 'ready',
            'engineLoaded' => true,
            'phone' => '5511999999999',
            'pushName' => 'CRM Bot',
            'lastActive' => '2026-08-20T10:00:00.000Z',
            'restriction' => null,
        ], 200),
    ]);

    // Act
    $this->artisan('topweb-chat:reconcile --state')
        ->assertExitCode(0);

    // Assert
    expect($instance->fresh()->status)->toBe('ready');
    expect($instance->fresh()->engine_loaded)->toBeTrue();
});
```

### Teste 2: Atualiza Campos Divergentes
```php
it('updates Instance fields when OpenWA returns different values', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-456',
        'status' => 'disconnected',      // divergente
        'engine_loaded' => false,        // divergente
        'phone' => '5511888888888',      // divergente
        'push_name' => 'Old Name',       // divergente
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-456' => Http::response([
            'id' => 'uuid-456',
            'status' => 'ready',
            'engineLoaded' => true,
            'phone' => '5511999999999',
            'pushName' => 'New Name',
            'lastActive' => '2026-08-20T10:00:00.000Z',
            'restriction' => null,
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --state');

    $instance->refresh();
    expect($instance->status)->toBe('ready');
    expect($instance->engine_loaded)->toBeTrue();
    expect($instance->phone)->toBe('5511999999999');
    expect($instance->push_name)->toBe('New Name');
});
```

### Teste 3: Status Failed → Warning + Sem Auto-Recovery (INV-7)
```php
it('logs warning and does NOT auto-recover when status=failed (INV-7)', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-789',
        'status' => 'disconnected',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-789' => Http::response([
            'id' => 'uuid-789',
            'status' => 'failed',           // TERMINAL per INV-7
            'engineLoaded' => false,
            'lastError' => 'WhatsApp Web refused connection: TOS_BLOCK',
        ], 200),
    ]);

    // Capture logs
    $logs = [];
    Log::shouldReceive('warning')->once()->andReturnUsing(function ($message, $context) use (&$logs) {
        $logs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --state');

    // Assert: status updated to failed
    expect($instance->fresh()->status)->toBe('failed');

    // Assert: warning logged with context
    expect($logs)->toHaveCount(1);
    expect($logs[0]['context'])->toContainKeys(['instance_id', 'session_uuid', 'old_status', 'new_status', 'reason']);
    expect($logs[0]['context']['new_status'])->toBe('failed');

    // Assert: NO auto-recovery (no POST /start called)
    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), '/start');
    });
});
```

### Teste 4: Disconnected + Auto-Start Config
```php
it('auto-starts disconnected session when auto_start config enabled', function () {
    config(['topweb_chat.auto_start' => true]);

    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-auto',
        'status' => 'disconnected',
        'engine_loaded' => false,
    ]);

    Http::fake([
        // GET /sessions/:id
        'openwa:2785/api/sessions/uuid-auto' => Http::response([
            'id' => 'uuid-auto',
            'status' => 'disconnected',
            'engineLoaded' => false,
        ], 200),
        // POST /sessions/:id/start
        'openwa:2785/api/sessions/uuid-auto/start' => Http::response([
            'id' => 'uuid-auto',
            'status' => 'initializing',
            'engineLoaded' => true,
        ], 200),
    ], ['*']); // allow multiple calls

    $this->artisan('topweb-chat:reconcile --state');

    // Assert: POST /start was called
    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/start') && $request->method() === 'POST';
    });
});
```

### Teste 5: Disconnected + Auto-Start Disabled (Default)
```php
it('does NOT auto-start when auto_start config is disabled', function () {
    config(['topweb_chat.auto_start' => false]);

    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-no-auto',
        'status' => 'disconnected',
        'engine_loaded' => false,
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-no-auto' => Http::response([
            'id' => 'uuid-no-auto',
            'status' => 'disconnected',
            'engineLoaded' => false,
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --state');

    // Assert: NO POST /start called
    Http::assertNotSent(function ($request) {
        return str_ends_with($request->url(), '/start');
    });
});
```

### Teste 6: Restriction Field Sync
```php
it('syncs restriction field from OpenWA (reachout_timelock, tos_block, proxy_block)', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-restrict',
        'restriction' => null,
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-restrict' => Http::response([
            'id' => 'uuid-restrict',
            'status' => 'ready',
            'engineLoaded' => true,
            'restriction' => [
                'kind' => 'reachout_timelock',
                'code' => 'REACHOUT_TIMELOCK',
                'expiresAt' => '2026-08-25T10:00:00.000Z',
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --state');

    $restriction = $instance->fresh()->restriction;
    expect($restriction)->not->toBeNull();
    expect($restriction['kind'])->toBe('reachout_timelock');
    expect($restriction['code'])->toBe('REACHOUT_TIMELOCK');
    expect($restriction['expires_at'])->toBe('2026-08-25T10:00:00.000Z');
});
```

### Teste 7: Auditorial Log com Before/After
```php
it('logs audit entry with before/after state for each instance', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-audit',
        'status' => 'disconnected',
        'engine_loaded' => false,
        'phone' => '5511888888888',
        'push_name' => 'Old',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-audit' => Http::response([
            'id' => 'uuid-audit',
            'status' => 'ready',
            'engineLoaded' => true,
            'phone' => '5511999999999',
            'pushName' => 'New',
            'lastActive' => '2026-08-20T10:00:00.000Z',
            'restriction' => null,
        ], 200),
    ]);

    $auditLogs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$auditLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.instance_status') {
            $auditLogs[] = $context;
        }
    });

    $this->artisan('topweb-chat:reconcile --state');

    expect($auditLogs)->toHaveCount(1);
    $log = $auditLogs[0];
    expect($log['event'])->toBe('reconciliation.instance_status');
    expect($log['instance_id'])->toBe($instance->id);
    expect($log['session_uuid'])->toBe('uuid-audit');
    expect($log['before']['status'])->toBe('disconnected');
    expect($log['after']['status'])->toBe('ready');
    expect($log['before']['engine_loaded'])->toBeFalse();
    expect($log['after']['engine_loaded'])->toBeTrue();
    expect($log['changed_fields'])->toContain('status', 'engine_loaded', 'phone', 'push_name');
});
```

### Teste 8: Scheduler Registration
```php
// tests/Feature/Scheduler/ReconciliationSchedulerTest.php

it('registers reconcile --state job everyMinute', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    
    // Simulate kernel schedule registration
    $this->artisan('schedule:run')->assertExitCode(0);
    
    // Verify schedule definition exists
    $commands = $schedule->commands();
    $reconcileState = collect($commands)->firstWhere('command', 'topweb-chat:reconcile --state');
    
    expect($reconcileState)->not->toBeNull();
    expect($reconcileState->getExpression())->toBe('* * * * *'); // everyMinute
});
```

### Teste 9: Ignora Instâncias Desabilitadas
```php
it('skips instances with enabled=false', function () {
    $enabled = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-enabled']);
    $disabled = Instance::factory()->enabled(false)->create(['session_uuid' => 'uuid-disabled']);

    Http::fake([
        'openwa:2785/api/sessions/uuid-enabled' => Http::response([
            'id' => 'uuid-enabled',
            'status' => 'ready',
            'engineLoaded' => true,
        ], 200),
        // Should NOT call for disabled instance
        'openwa:2785/api/sessions/uuid-disabled' => Http::response([], 404)->once(),
    ]);

    $this->artisan('topweb-chat:reconcile --state');

    // Only enabled instance called
    Http::assertSentCount(1);
});
```

### Teste 10: Error Handling (Network/5xx)
```php
it('logs error and continues when OpenWA returns 5xx or network error', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-error']);

    Http::fake([
        'openwa:2785/api/sessions/uuid-error' => Http::response([
            'message' => 'Internal Server Error',
        ], 500),
    ]);

    $errorLogs = [];
    Log::shouldReceive('error')->andReturnUsing(function ($message, $context) use (&$errorLogs) {
        $errorLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --state')->assertExitCode(0); // Command doesn't fail

    expect($errorLogs)->toHaveCount(1);
    expect($errorLogs[0]['context'])->toContainKeys(['instance_id', 'session_uuid', 'error']);
});
```

---

## 3. Interface Contracts (Para Implementação)

### Command Signature
```php
// app/Console/Commands/TopwebChatReconcile.php
protected $signature = 'topweb-chat:reconcile 
    {--state : Reconcile instance status} 
    {--history : Reconcile message history} 
    {--limit=20 : Limit conversations for history}';
```

### OpenWaProvider Interface (Novos Métodos)
```php
// app/Services/Messaging/OpenWaProvider.php

/**
 * Get session status from OpenWA
 * @return array{id, name, status, engineLoaded, phone?, pushName?, lastActive?, restriction?, lastError?}
 */
public function getSessionStatus(string $sessionUuid): array;

/**
 * Start a session
 * @return array{id, name, status, engineLoaded}
 */
public function startSession(string $sessionUuid): array;
```

### Service: InstanceReconciliationService
```php
// app/Services/TopwebChat/InstanceReconciliationService.php

class InstanceReconciliationService {
    public function __construct(
        protected OpenWaProvider $provider,
        protected InstanceRepository $instances,
        protected ReconciliationLogger $logger
    ) {}

    /** @return array{updated: int, failed: int, skipped: int} */
    public function reconcileStatus(): array;
}
```

---

## 4. Arquivos a Criar/Modificar (Implementation Plan)

| Arquivo | Ação | Descrição |
|---------|------|-----------|
| `app/Console/Commands/TopwebChatReconcile.php` | **Criar** | Command com `--state`, `--history`, `--limit` |
| `app/Services/Messaging/OpenWaProvider.php` | **Estender** | Adicionar `getSessionStatus()`, `startSession()` |
| `app/Services/TopwebChat/InstanceReconciliationService.php` | **Criar** | Lógica core de reconciliação |
| `app/Services/TopwebChat/ReconciliationLogger.php` | **Criar** | Logs estruturados + audit |
| `routes/console.php` | **Modificar** | Registrar `everyMinute()` para `--state` |
| `config/topweb-chat.php` | **Modificar** | Adicionar `auto_start` config |
| `database/migrations/xxxx_add_reconciliation_fields_to_instances.php` | **Criar** | Colunas: `engine_loaded`, `restriction` (JSON), `last_active_at` |
| `tests/Feature/Reconciliation/InstanceStatusReconciliationTest.php` | **Criar** | Testes acima (10 testes) |
| `tests/Feature/Scheduler/ReconciliationSchedulerTest.php` | **Criar** | Teste scheduler |

---

## 5. Mocking Strategy (Pest + Http::fake)

```php
// Base test setup
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'openwa:2785/api/sessions/*' => Http::response([], 404), // default deny
    ]);
});

afterEach(function () {
    Http::assertSentCount(0); // verify all expected calls made
});
```

---

## 6. Evidências de GREEN (Checklist Final)

Quando implementado, deve passar:

```bash
# 1. Command executa
php artisan topweb-chat:reconcile --state
# Exit code 0, logs reconciliation.instance_status

# 2. Scheduler registrado
php artisan schedule:run --dry-run
# Mostra topweb-chat:reconcile --state every minute

# 3. Testes passam
php artisan test tests/Feature/Reconciliation/InstanceStatusReconciliationTest.php
# 10 testes, 10 passando

# 4. Lint + style
./vendor/bin/pint
# 0 erros

# 5. Verificação manual
# - Instance status sincronizado com OpenWA mock
# - Failed não auto-recupera
# - Auto-start só quando config true
# - Audit log com before/after
```

---

## 7. Próximo Slice (Dependência)

Após GREEN deste slice → **#02 Message Status Reconciliation** (precisa do instance status funcionando para consultar history).

---

> **Nota**: Este documento é a especificação RED. A implementação deve seguir o rito: 1 teste por vez → GREEN → próximo teste. Proibido implementar múltiplos comportamentos de uma vez.