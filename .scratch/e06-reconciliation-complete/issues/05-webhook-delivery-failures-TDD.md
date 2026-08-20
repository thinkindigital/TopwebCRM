# TDD Specification — Slice #05: Webhook Delivery Failures Monitoring

> **Fase**: RED (especificação de testes antes da implementação)
> **Dependência**: #01 Instance Status Reconciliation (precisa instâncias ativas)

---

## 1. Comportamentos Críticos (Scope)

| Comportamento | Descrição | Prioridade |
|---------------|-----------|------------|
| **Failures Job** | Job `topweb-chat:reconcile --webhook-failures` (daily) consulta `GET /api/webhooks/delivery-failures` | P0 |
| **OpenWA Integration** | Para cada instância ativa: `GET /api/webhooks/delivery-failures?sessionId={uuid}` | P0 |
| **Alert Logging** | Cada delivery falho esgotado → log `reconciliation.webhook_failure` com `action=alerted` | P0 |
| **Admin UI** | Página `/admin/topweb-chat/webhook-failures` (DataGrid) com colunas, filtros, export, botão "Reprocessar" | P0 |
| **Reprocess Action** | Botão "Reprocessar" → `POST /api/sessions/:sessionId/webhooks/:id/test` + log `action=reprocessed` | P0 |
| **Filters** | Filtros: session, event, date range, status code | P1 |

---

## 2. Test Specification (RED Phase)

### Teste 1: Command --webhook-failures Executa
```php
// tests/Feature/Reconciliation/WebhookFailuresReconciliationTest.php

it('executes webhook failures reconcile for active instances', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-wf',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    Http::fake([
        'openwa:2785/api/webhooks/delivery-failures?sessionId=uuid-wf' => Http::response([
            [
                'deliveryId' => 'dlv_abc123',
                'event' => 'message.received',
                'sessionId' => 'uuid-wf',
                'url' => 'https://crm.example.com/webhook',
                'lastStatusCode' => 500,
                'attempts' => 3,
                'failedAt' => '2026-08-20T10:00:00.000Z',
                'error' => 'Connection timeout',
            ],
        ], 200),
    ]);

    $this->artisan('topweb-chat:reconcile --webhook-failures')
        ->assertExitCode(0);

    // Assert: API called for active instance
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/webhooks/delivery-failures') 
            && str_contains($request->url(), 'sessionId=uuid-wf');
    });
});
```

### Teste 2: Logs Alert for Each Failed Delivery
```php
it('logs alert for each exhausted delivery failure', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-wf-alert',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    Http::fake([
        'openwa:2785/api/webhooks/delivery-failures?sessionId=uuid-wf-alert' => Http::response([
            [
                'deliveryId' => 'dlv_fail1',
                'event' => 'message.received',
                'sessionId' => 'uuid-wf-alert',
                'url' => 'https://crm.example.com/webhook',
                'lastStatusCode' => 500,
                'attempts' => 3,
                'failedAt' => '2026-08-20T10:00:00.000Z',
                'error' => 'Connection timeout',
            ],
            [
                'deliveryId' => 'dlv_fail2',
                'event' => 'message.sent',
                'sessionId' => 'uuid-wf-alert',
                'url' => 'https://crm.example.com/webhook',
                'lastStatusCode' => null, // network error
                'attempts' => 3,
                'failedAt' => '2026-08-20T10:05:00.000Z',
                'error' => 'Network error',
            ],
        ], 200),
    ]);

    $alertLogs = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$alertLogs) {
        $alertLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --webhook-failures');

    expect($alertLogs)->toHaveCount(2);
    
    foreach ($alertLogs as $log) {
        expect($log['context']['event'])->toBe('reconciliation.webhook_failure');
        expect($log['context']['action'])->toBe('alerted');
        expect($log['context'])->toContainKeys([
            'delivery_id', 'event', 'session_id', 'url', 'last_status_code', 'attempts', 'failed_at', 'error'
        ]);
    }
});
```

### Teste 3: Ignores Instances Not Ready
```php
it('skips instances that are not ready', function () {
    $ready = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-wf-ready', 'status' => 'ready', 'engine_loaded' => true]);
    $disc = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-wf-disc', 'status' => 'disconnected', 'engine_loaded' => false]);
    $failed = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-wf-fail', 'status' => 'failed', 'engine_loaded' => false]);

    Http::fake([
        'openwa:2785/api/webhooks/delivery-failures?sessionId=uuid-wf-ready' => Http::response([], 200),
        // Should NOT call for disconnected/failed
    ]);

    $this->artisan('topweb-chat:reconcile --webhook-failures');

    Http::assertSentCount(1);
});
```

### Teste 4: Handles Empty Response
```php
it('handles empty delivery failures response gracefully', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-wf-empty',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    Http::fake([
        'openwa:2785/api/webhooks/delivery-failures?sessionId=uuid-wf-empty' => Http::response([], 200),
    ]);

    $alertLogs = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($message, $context) use (&$alertLogs) {
        $alertLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --webhook-failures');

    expect($alertLogs)->toHaveCount(0); // no failures = no alerts
});
```

### Teste 5: Error Handling (5xx/Network)
```php
it('logs error and continues when OpenWA returns 5xx', function () {
    $ready = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-wf-err1', 'status' => 'ready', 'engine_loaded' => true]);
    $ready2 = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-wf-err2', 'status' => 'ready', 'engine_loaded' => true]);

    Http::fake([
        'openwa:2785/api/webhooks/delivery-failures?sessionId=uuid-wf-err1' => Http::response(['message' => 'Internal Server Error'], 500),
        'openwa:2785/api/webhooks/delivery-failures?sessionId=uuid-wf-err2' => Http::response([], 200),
    ]);

    $errorLogs = [];
    Log::shouldReceive('error')->andReturnUsing(function ($message, $context) use (&$errorLogs) {
        $errorLogs[] = compact('message', 'context');
    });

    $this->artisan('topweb-chat:reconcile --webhook-failures')->assertExitCode(0);

    expect($errorLogs)->toHaveCount(1);
    expect($errorLogs[0]['context'])->toContainKeys(['instance_id', 'session_uuid', 'error']);
});
```

---

## 3. Admin UI Tests (DataGrid)

### Teste 6: Admin Page Accessible with Permission
```php
// tests/Feature/Admin/WebhookFailuresPageTest.php

it('shows webhook failures page for users with topweb_chat.webhook_failures permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.webhook_failures');

    $response = $this->actingAs($user)->get('/admin/topweb-chat/webhook-failures');
    
    $response->assertStatus(200);
    $response->assertSee('Webhook Failures');
});
```

### Teste 7: DataGrid Shows Failure Columns
```php
it('displays all required columns in DataGrid', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.webhook_failures');

    // Mock some failures in DB (if we store them) or via session
    // For now test the view renders correctly
    $response = $this->actingAs($user)->get('/admin/topweb-chat/webhook-failures');
    
    $response->assertSee('delivery_id');
    $response->assertSee('event');
    $response->assertSee('session_id');
    $response->assertSee('url');
    $response->assertSee('last_status_code');
    $response->assertSee('attempts');
    $response->assertSee('failed_at');
    $response->assertSee('error');
    $response->assertSee('Reprocessar'); // button
});
```

### Teste 7: Filters Work
```php
it('filters by session, event, date range, status', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.webhook_failures');

    $response = $this->actingAs($user)->get('/admin/topweb-chat/webhook-failures', [
        'session_id' => 'uuid-wf',
        'event' => 'message.received',
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-31',
        'status_code' => '500',
    ]);
    
    $response->assertStatus(200);
    // Assert query params passed to DataGrid
});
```

### Teste 8: Export Works
```php
it('exports filtered results', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.webhook_failures');

    $response = $this->actingAs($user)->get('/admin/topweb-chat/webhook-failures/export', [
        'format' => 'csv',
    ]);
    
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv');
});
```

---

## 4. Reprocess Action Tests

### Teste 9: Reprocess Button Calls OpenWA Test Endpoint
```php
it('reprocess action calls OpenWA webhook test endpoint', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.webhook_failures');

    Http::fake([
        'openwa:2785/api/sessions/uuid-wf-reprocess/webhooks/webhook-id/test' => Http::response([
            'success' => true,
            'statusCode' => 200,
        ], 200),
    ]);

    $reprocessLogs = [];
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$reprocessLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.webhook_failure') {
            $reprocessLogs[] = $context;
        }
    });

    $response = $this->actingAs($user)->post('/admin/topweb-chat/webhook-failures/reprocess', [
        'session_id' => 'uuid-wf-reprocess',
        'webhook_id' => 'webhook-id',
    ]);

    $response->assertStatus(200);
    
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/webhooks/webhook-id/test')
            && $request->method() === 'POST';
    });

    expect($reprocessLogs)->toHaveCount(1);
    expect($reprocessLogs[0]['context']['action'])->toBe('reprocessed');
    expect($reprocessLogs[0]['context']['delivery_id'])->toBe('dlv_reprocess');
});
```

### Teste 10: Reprocess Logs Action
```php
it('logs reprocess action with delivery_id', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.webhook_failures');

    Http::fake([
        'openwa:2785/api/sessions/uuid-wf-reprocess2/webhooks/webhook-id2/test' => Http::response([
            'success' => false,
            'statusCode' => 500,
        ], 200),
    ]);

    $reprocessLogs = [];
    Log::shouldReceive('info')->andReturnUsing(function ($message, $context) use (&$reprocessLogs) {
        if (isset($context['event']) && $context['event'] === 'reconciliation.webhook_failure') {
            $reprocessLogs[] = $context;
        }
    });

    $this->actingAs($user)->post('/admin/topweb-chat/webhook-failures/reprocess', [
        'session_id' => 'uuid-wf-reprocess2',
        'webhook_id' => 'webhook-id2',
    ]);

    expect($reprocessLogs)->toHaveCount(1);
    expect($reprocessLogs[0]['context']['action'])->toBe('reprocessed');
    expect($reprocessLogs[0]['context']['result'])->toBe('failed'); // test returned 500
});
```

---

## 3. Interface Contracts

### Command Option
```php
// topweb-chat:reconcile --webhook-failures
```

### OpenWaProvider — Delivery Failures
```php
// app/Services/Messaging/OpenWaProvider.php

/**
 * Get webhook delivery failures for a session
 * @param string $sessionUuid
 * @return array<int, array{deliveryId, event, sessionId, url, lastStatusCode, attempts, failedAt, error}>
 */
public function getWebhookDeliveryFailures(string $sessionUuid): array;

/**
 * Test a webhook endpoint
 * @param string $sessionUuid
 * @param string $webhookId
 * @return array{success: bool, statusCode: int}
 */
public function testWebhook(string $sessionUuid, string $webhookId): array;
```

### Service: WebhookFailureService
```php
// app/Services/TopwebChat/WebhookFailureService.php

class WebhookFailureService {
    public function __construct(
        protected OpenWaProvider $provider,
        protected ReconciliationLogger $logger
    ) {}

    /** @return array{alerted: int, errors: int} */
    public function checkFailures(): array;

    /** @return array{success: bool, statusCode: int, reprocessed: bool} */
    public function reprocessFailure(string $sessionUuid, string $webhookId): array;
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Console/Commands/TopwebChatReconcile.php` | Estender (opção `--webhook-failures`) |
| `app/Services/Messaging/OpenWaProvider.php` | Estender (`getWebhookDeliveryFailures()`, `testWebhook()`) |
| `app/Services/TopwebChat/WebhookFailureService.php` | **Criar** |
| `app/Http/Controllers/Admin/TopwebChat/WebhookFailuresController.php` | **Criar** |
| `packages/Webkul/TopwebChat/src/Resources/views/admin/webhook-failures/` | **Criar** (DataGrid + index) |
| `routes/console.php` | Registrar `daily()` |
| `routes/web.php` | Registrar rotas admin |
| `tests/Feature/Reconciliation/WebhookFailuresReconciliationTest.php` | **Criar** (5 testes) |
| `tests/Feature/Admin/WebhookFailuresPageTest.php` | **Criar** (5 testes UI) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'openwa:2785/api/webhooks/delivery-failures*' => Http::response([], 404),
        'openwa:2785/api/sessions/*/webhooks/*/test' => Http::response([], 404),
    ]);
});
```

---

## 6. Evidências GREEN

```bash
php artisan topweb-chat:reconcile --webhook-failures
php artisan test tests/Feature/Reconciliation/WebhookFailuresReconciliationTest.php  # 5 pass
php artisan test tests/Feature/Admin/WebhookFailuresPageTest.php  # 5 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#06-10 Quarentena Identidade** (zero matches, ambiguous, lid_unresolved, UI, Checar WhatsApp).