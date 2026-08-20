# TDD Specification — Slice #22: PIX Domain (auditoria financeira)

> **Fase**: RED
> **Dependência**: #11-21 (media + interactive + domains completos)

---

## 1. Comportamentos Criticos

| Comportamento | Prioridade |
|---------------|------------|
| **Entidades** | `TopwebChatPixTransaction`: `id`, `conversation_id`, `message_id`, `pix_key`, `amount`, `currency`, `status` (pending/paid/failed/expired/refunded), `payload`, `response`, `expires_at`, `paid_at` | P0 |
| **Webhook** | `message.received` com `type='pix'` -> detecta payload PIX -> Job `ProcessPixMessage` cria `PixTransaction` + vincula `Message` | P0 |
| **Outbound** | Enviar cobranca PIX: UI botao "Solicitar PIX" no composer -> modal: chave PIX, valor, descricao -> `POST /api/sessions/:sessionId/messages/send-pix` (se OpenWA suportar) OU envia mensagem texto com payload PIX copiavel | P0 |
| **Auditoria** | Log estruturado `pix.transaction` com todos campos (LGPD/PCI compliance) | P0 |
| **ACL** | `topweb_chat.pix.send`, `topweb_chat.pix.view`, `topweb_chat.pix.audit` (apenas admins/financeiro) | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Entidade TopwebChatPixTransaction

```php
// tests/Feature/Domain/PixDomainTest.php

it('creates PixTransaction entity with correct schema', function () {
    $this->artisan('migrate --pretend')
        ->expectsOutputToContain('topweb_chat_pix_transactions');

    $this->artisan('migrate');

    expect(Schema::hasTable('topweb_chat_pix_transactions'))->toBeTrue();
    expect(Schema::hasColumns('topweb_chat_pix_transactions', [
        'conversation_id', 'message_id', 'pix_key', 'amount', 'currency',
        'status', 'payload', 'response', 'expires_at', 'paid_at'
    ]))->toBeTrue();
});
```

### Teste 2: Inbound - Detecta Mensagem PIX -> Cria PixTransaction

```php
it('detects PIX message inbound and creates transaction', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-pix', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-pix',
        'idempotencyKey' => 'msg_uuid-pix_true_5511999999999@c.us_PIX123',
        'deliveryId' => 'dlv_pix1',
        'data' => [
            'id' => 'true_5511999999999@c.us_PIX123',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'body' => 'PIX payment received',
            'type' => 'pix',
            'timestamp' => 1724150000,
            'isGroup' => false,
            'kind' => 'individual',
            'hasMedia' => false,
            'pix' => [
                'key' => 'chave.pix@exemplo.com',
                'amount' => 150.50,
                'currency' => 'BRL',
                'payload' => '00020126360014br.gov.bcb.pix0114chave.pix@exemplo.com520400005303986540510.005802BR5913Joao Silva6009Sao Paulo62070503***6304ABCD',
            ],
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-pix/messages/*/history?limit=50' => Http::response([], 200)]);

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-pix', $payload, $headers);

    $response->assertStatus(200);

    $pixTx = TopwebChatPixTransaction::where('pix_key', 'chave.pix@exemplo.com')->first();
    expect($pixTx)->not->toBeNull();
    expect($pixTx->conversation_id)->toBe($conversation->id);
    expect($pixTx->pix_key)->toBe('chave.pix@exemplo.com');
    expect($pixTx->amount)->toBe(150.50);
    expect($pixTx->currency)->toBe('BRL');
    expect($pixTx->status)->toBe('pending');
    expect($pixTx->payload)->toBeArray();
});
```

### Teste 3: Inbound - PIX com Status Paid

```php
it('updates PIX transaction status to paid when payment confirmed', function () {
    $pixTx = TopwebChatPixTransaction::factory()->create([
        'status' => 'pending',
        'amount' => 100.00,
        'pix_key' => 'chave.pix@exemplo.com',
    ]);

    $payload = [
        'event' => 'pix.payment_confirmed',
        'sessionId' => 'uuid-pix-confirmed',
        'idempotencyKey' => 'pix_confirmed_uuid-pix_12345',
        'data' => [
            'pix_key' => 'chave.pix@exemplo.com',
            'amount' => 100.00,
            'status' => 'paid',
            'paid_at' => 1724150500,
            'transaction_id' => 'tx_12345',
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-pix-confirmed/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-pix-confirmed', $payload, $headers);

    $tx = $pixTx->fresh();
    expect($tx->status)->toBe('paid');
    expect($tx->paid_at)->not->toBeNull();
    expect($tx->response)->toBeArray();
});
```

### Teste 4: Outbound - Enviar Cobranca PIX (UI "Solicitar PIX")

```php
it('sends PIX request via OpenWA if supported, otherwise sends text with PIX payload', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.pix.send');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-pix-send', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-pix-send/messages/send-pix' => Http::response([
            'messageId' => 'true_5511999999999@c.us_PIXSENT123',
            'timestamp' => 1724150000,
        ], 201),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/send-pix", [
        'pix_key' => 'chave.pix@exemplo.com',
        'amount' => 250.75,
        'description' => 'Pagamento servicos',
    ]);

    if ($response->status() === 201) {
        Http::assertSent(function ($request) {
            return $request->url() === 'http://openwa:2785/api/sessions/uuid-pix-send/messages/send-pix'
                && json_decode($request->body(), true)['pix_key'] === 'chave.pix@exemplo.com';
        });
    } else {
        Http::assertSent(function ($request) {
            return $request->url() === 'http://openwa:2785/api/sessions/uuid-pix-send/messages/send-text'
                && str_contains($request->body(), 'PIX');
        });
    }

    $response->assertStatus(200);
});
```

### Teste 5: Fallback - Envia Texto com Payload PIX Copiavel

```php
it('falls back to text message with PIX payload when OpenWA does not support send-pix', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.pix.send');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-pix-fallback', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-pix-fallback/messages/send-pix' => Http::response([
            'message' => 'send-pix not supported',
        ], 501),
        'openwa:2785/api/sessions/uuid-pix-fallback/messages/send-text' => Http::response([
            'messageId' => 'true_5511999999999@c.us_FALLBACK123',
            'timestamp' => 1724150000,
        ], 201),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/send-pix", [
        'pix_key' => 'chave.pix@exemplo.com',
        'amount' => 100.00,
        'description' => 'Teste fallback',
    ]);

    $response->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://openwa:2785/api/sessions/uuid-pix-fallback/messages/send-text'
            && str_contains(json_decode($request->body(), true)['text'], 'PIX');
    });
});
```

### Teste 6: Auditoria - Log Estruturado `pix.transaction`

```php
it('logs structured pix.transaction with all fields (LGPD/PCI compliance)', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-pix-audit', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-pix-audit',
        'idempotencyKey' => 'msg_uuid-pix-audit_true_5511999999999@c.us_AUDIT123',
        'deliveryId' => 'dlv_audit1',
        'data' => [
            'id' => 'true_5511999999999@c.us_AUDIT123',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'body' => 'PIX payment',
            'type' => 'pix',
            'timestamp' => 1724150000,
            'pix' => [
                'key' => 'chave.pix@exemplo.com',
                'amount' => 500.00,
                'currency' => 'BRL',
            ],
        ],
    ];

    $auditLogs = [];
    Log::shouldReceive('channel')->with('pix')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($m, $c) use (&$auditLogs) {
        if (isset($c['event']) && $c['event'] === 'pix.transaction') $auditLogs[] = $c;
    });

    Http::fake(['openwa:2785/api/sessions/uuid-pix-audit/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-pix-audit', $payload, $headers);

    expect($auditLogs)->toHaveCount(1);
    $log = $auditLogs[0];
    expect($log['event'])->toBe('pix.transaction');
    expect($log['conversation_id'])->toBe($conversation->id);
    expect($log['pix_key'])->toBe('chave.pix@exemplo.com');
    expect($log['amount'])->toBe(500.00);
    expect($log['currency'])->toBe('BRL');
    expect($log['status'])->toBe('pending');
    expect($log['pix_key'])->toBe('chave.pix@exemplo.com');
});
```

### Teste 6: ACL - `topweb_chat.pix.send`, `topweb_chat.pix.view`, `topweb_chat.pix.audit`

```php
it('enforces PIX ACL permissions', function () {
    $userSend = User::factory()->create();
    $userSend->givePermissionTo('topweb_chat.pix.send');

    $userView = User::factory()->create();
    $userView->givePermissionTo('topweb_chat.pix.view');

    $userAudit = User::factory()->create();
    $userAudit->givePermissionTo('topweb_chat.pix.audit');

    $userNone = User::factory()->create();

    $response = $this->actingAs($userSend)->postJson('/api/topweb-chat/conversations/1/send-pix', []);
    $response->assertStatus(200);

    $response = $this->actingAs($userView)->get('/admin/topweb-chat/pix-transactions');
    $response->assertStatus(200);

    $response = $this->actingAs($userAudit)->get('/admin/topweb-chat/pix-audit');
    $response->assertStatus(200);

    $response = $this->actingAs($userNone)->postJson('/api/topweb-chat/conversations/1/send-pix', []);
    $response->assertStatus(403);
});
```

---

## 3. Interface Contracts

### Entity

```php
// app/Models/TopwebChatPixTransaction.php

class TopwebChatPixTransaction extends Model {
    protected $fillable = [
        'conversation_id', 'message_id', 'pix_key', 'amount', 'currency',
        'status', 'payload', 'response', 'expires_at', 'paid_at',
    ];
    protected $casts = [
        'payload' => 'array',
        'response' => 'array',
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function conversation(): BelongsTo {
        return $this->belongsTo(Conversation::class);
    }
    public function message(): BelongsTo {
        return $this->belongsTo(Message::class);
    }
}
```

### OpenWaProvider (se suportar send-pix)

```php
// app/Services/Messaging/OpenWaProvider.php

public function sendPix(string $sessionUuid, string $chatId, string $pixKey, float $amount, string $description): array {
    return $this->post("sessions/{$sessionUuid}/messages/send-pix", [
        'chatId' => $chatId,
        'pixKey' => $pixKey,
        'amount' => $amount,
        'currency' => 'BRL',
        'description' => $description,
    ]);
}
```

### Service

```php
// app/Services/TopwebChat/PixService.php

class PixService {
    public function processInboundPix(string $sessionUuid, array $pixData, Message $message): TopwebChatPixTransaction {
        // 1. Extract PIX data from message
        // 2. Create PixTransaction with status=pending
        // 3. Log audit
        // 4. Return transaction
    }

    public function sendPixRequest(string $sessionUuid, string $chatId, string $pixKey, float $amount, string $description): array {
        // Try OpenWA send-pix first
        // If 501, fallback to text with PIX payload
    }

    public function updateStatus(TopwebChatPixTransaction $tx, string $status, array $response = []): void {
        $tx->status = $status;
        if ($status === 'paid') $tx->paid_at = now();
        $tx->response = array_merge($tx->response ?? [], $response);
        $tx->save();
        
        Log::channel('pix')->info('PIX status updated', [
            'event' => 'pix.status_updated',
            'transaction_id' => $tx->id,
            'old_status' => $tx->getOriginal('status'),
            'new_status' => $status,
        ]);
    }
}
```

### Audit Log Channel

```php
// config/logging.php

'channels' => [
    'pix' => [
        'driver' => 'daily',
        'path' => storage_path('logs/pix.log'),
        'level' => 'info',
        'days' => 365,
    ],
],
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Acao |
|---------|------|
| `database/migrations/xxxx_create_topweb_chat_pix_transactions_table.php` | **Criar** |
| `app/Models/TopwebChatPixTransaction.php` | **Criar** |
| `app/Services/Messaging/OpenWaProvider.php` | Estender `sendPix()` |
| `app/Services/TopwebChat/PixService.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/PixController.php` | **Criar** |
| `app/Http/Controllers/Admin/TopwebChat/PixController.php` | **Criar** |
| `packages/Webkul/TopwebChat/src/Resources/views/admin/pix/` | **Criar** |
| `routes/api.php` | Registrar rotas pix |
| `routes/web.php` | Registrar rotas admin pix |
| `config/topweb-chat.php` | Adicionar permissoes pix |
| `config/logging.php` | Adicionar canal pix |
| `tests/Feature/Domain/PixDomainTest.php` | **Criar** |
| `tests/Feature/Admin/PixAdminTest.php` | **Criar** |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::fake([
        'openwa:2785/api/sessions/*/messages/send-pix' => Http::response([], 404),
        'openwa:2785/api/sessions/*/messages/send-text' => Http::response([], 404),
    ]);
});
```

---

## 6. Evidencias GREEN

```bash
php artisan migrate
php artisan test tests/Feature/Domain/PixDomainTest.php
php artisan test tests/Feature/Admin/PixAdminTest.php
./vendor/bin/pint
```

---

## Conclusao

Com este slice, completamos todos os 22 slices do Epic E06. O sistema agora possui:

1. **Reconciliação Automática** (5 slices)
2. **Quarentena Identidade** (5 slices)  
3. **Mídia Privada** (7 slices)
4. **Interativos** (2 slices: reações, edição)
5. **Domínios Separados** (4 slices: Grupos, Chamadas/Canais, PIX)

Todos com TDD specs, interface contracts, e arquivos mapeados para implementacao.