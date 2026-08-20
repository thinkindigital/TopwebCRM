# TDD Specification — Slice #18: Reactions Support (message.reaction webhook + POST /react)

> **Fase**: RED
> **Dependência**: #11-17 (media pipeline completo)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Inbound** | Webhook `message.reaction` → Job `ProcessReaction` → atualiza `Message.metadata.reactions` (JSON: `{emoji: [{senderId, senderName, timestamp}]}`) | P0 |
| **Outbound** | Operador reage → picker emoji → `POST /api/sessions/:sessionId/messages/react` → `OpenWaProvider::react()` | P0 |
| **Persistência** | `Message.metadata.reactions` = array de objetos `{emoji, senderId, senderName, timestamp}` | P0 |
| **UI** | Mostrar reações agrupadas por emoji + contagem + lista senders (tooltip) | P0 |
| **Remoção** | Emoji vazio → remove reação do sender | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Inbound Webhook message.reaction
```php
// tests/Feature/Interactive/ReactionsTest.php

it('processes inbound message.reaction webhook', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-react', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Original message
    $originalMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'provider_message_id' => 'true_5511999999999@c.us_ORIGINAL123',
        'metadata' => ['reactions' => []],
    ]);

    // Webhook message.reaction
    $payload = [
        'event' => 'message.reaction',
        'sessionId' => 'uuid-react',
        'idempotencyKey' => 'react_uuid-react_true_5511999999999@c.us_ORIGINAL123_5511888888888@c.us_1724150000',
        'deliveryId' => 'dlv_react1',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_ORIGINAL123',
            'chatId' => '5511999999999@c.us',
            'reaction' => '👍',
            'senderId' => '5511888888888@c.us',
            'reactions' => [
                '5511888888888@c.us' => '👍',
            ],
        ],
    ];

    Http::fake([
        'openwa:2785/api/sessions/uuid-react/messages/*/history?limit=50' => Http::response([], 200),
    ]);

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-react', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
        'X-OpenWA-Event' => 'message.reaction',
        'X-OpenWA-Idempotency-Key' => $payload['idempotencyKey'],
    ]);

    $response->assertStatus(200);

    // Assert: reactions metadata updated
    $meta = $originalMsg->fresh()->metadata;
    expect($meta['reactions'])->toBeArray();
    expect($meta['reactions']['👍'])->toContain([
        'senderId' => '5511888888888@c.us',
        'senderName' => null, // will be resolved if contact details available
        'timestamp' => 1724150000,
    ]);
});
```

### Teste 2: Múltiplas Reações no Mesmo Emoji
```php
it('aggregates multiple reactions on same emoji', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-multi-react', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'true_5511999999999@c.us_MULTI123',
        'metadata' => ['reactions' => []],
    ]);

    // First reaction
    $payload1 = [
        'event' => 'message.reaction',
        'sessionId' => 'uuid-multi-react',
        'idempotencyKey' => 'react_uuid-multi-react_true_..._5511888888888@c.us_1724150000',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_MULTI123',
            'chatId' => '5511999999999@c.us',
            'reaction' => '❤️',
            'senderId' => '5511888888888@c.us',
            'reactions' => ['5511888888888@c.us' => '❤️'],
        ],
    ];

    // Second reaction (different sender, same emoji)
    $payload2 = [
        'event' => 'message.reaction',
        'sessionId' => 'uuid-multi-react',
        'idempotencyKey' => 'react_uuid-multi-react_true_..._5511777777777@c.us_1724150100',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_MULTI123',
            'chatId' => '5511999999999@c.us',
            'reaction' => '❤️',
            'senderId' => '5511777777777@c.us',
            'reactions' => [
                '5511888888888@c.us' => '❤️',
                '5511777777777@c.us' => '❤️',
            ],
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-multi-react/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-multi-react', $payload1, $headers);
    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-multi-react', $payload2, $headers);

    $meta = $msg->fresh()->metadata;
    expect($meta['reactions']['❤️'])->toHaveCount(2);
    expect($meta['reactions']['❤️'])->toContain('senderId' => '5511888888888@c.us');
    expect($meta['reactions']['❤️'])->toContain('senderId' => '5511777777777@c.us');
});
```

### Teste 3: Remoção de Reação (Emoji Vazio)
```php
it('removes reaction when emoji is empty', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-remove', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'true_5511999999999@c.us_REMOVE123',
        'metadata' => [
            'reactions' => [
                '👍' => [['senderId' => '5511888888888@c.us', 'senderName' => 'João', 'timestamp' => 1724150000]],
            ],
        ],
    ]);

    // Reaction removal webhook
    $payload = [
        'event' => 'message.reaction',
        'sessionId' => 'uuid-remove',
        'idempotencyKey' => 'react_uuid-remove_true_..._5511888888888@c.us_1724150200',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_REMOVE123',
            'chatId' => '5511999999999@c.us',
            'reaction' => '', // empty = removal
            'senderId' => '5511888888888@c.us',
            'reactions' => [], // empty after removal
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-remove/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-remove', $payload, $headers);

    $meta = $msg->fresh()->metadata;
    expect($meta['reactions'])->toBeEmpty();
});
```

### Teste 4: Outbound Reação (Operador Reage)
```php
it('sends reaction via POST /react', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.send');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-out-react', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'true_5511999999999@c.us_OUTREACT',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-out-react/messages/react' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/react", [
        'message_id' => $message->provider_message_id,
        'emoji' => '😂',
    ]);

    $response->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://openwa:2785/api/sessions/uuid-out-react/messages/react'
            && $request->method() === 'POST'
            && json_decode($request->body(), true)['emoji'] === '😂';
    });
});
```

### Teste 5: UI Mostra Reações Agrupadas
```php
it('UI displays reactions grouped by emoji with count and senders', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.access');

    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'reactions' => [
                '👍' => [
                    ['senderId' => '5511888888888@c.us', 'senderName' => 'João', 'timestamp' => 1724150000],
                    ['senderId' => '5511777777777@c.us', 'senderName' => 'Maria', 'timestamp' => 1724150010],
                ],
                '❤️' => [
                    ['senderId' => '5511666666666@c.us', 'senderName' => 'Pedro', 'timestamp' => 1724150020],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($operator)->getJson("/api/topweb-chat/conversations/{$conversation->id}/timeline");

    $response->assertStatus(200);
    $timeline = $response->json('messages');
    $msg = collect($timeline)->firstWhere('id', $message->id);

    expect($msg['metadata']['reactions'])->toBe([
        '👍' => [
            ['emoji' => '👍', 'count' => 2, 'senders' => ['João', 'Maria']],
            'emoji' => '👍',
        ],
        '❤️' => [
            ['emoji' => '❤️', 'count' => 1, 'senders' => ['Pedro']],
            'emoji' => '❤️',
        ],
    ]);
});
```

---

## 3. Interface Contracts

### OpenWaProvider::react()
```php
// app/Services/Messaging/OpenWaProvider.php

public function react(string $sessionUuid, string $chatId, string $messageId, string $emoji): array {
    // POST /api/sessions/{sessionId}/messages/react
    // Body: {chatId, messageId, emoji}
    // Empty emoji = remove reaction
    
    return $this->post("sessions/{$sessionUuid}/messages/react", [
        'chatId' => $chatId,
        'messageId' => $messageId,
        'emoji' => $emoji,
    ]);
}
```

### Reaction Service
```php
// app/Services/TopwebChat/ReactionService.php

class ReactionService {
    public function processInbound(string $sessionUuid, array $data): void {
        // 1. Find message by provider_message_id
        // 2. Update metadata.reactions
        //    - If reaction empty: remove sender from emoji array
        //    - If reaction present: add/update sender in emoji array
        //    - If emoji array empty after removal: remove emoji key
    }

    public function sendReaction(string $sessionUuid, string $chatId, string $messageId, string $emoji): void {
        app(OpenWaProvider::class)->react($sessionUuid, $chatId, $messageId, $emoji);
    }
}
```

### Metadata Structure
```json
{
  "reactions": {
    "👍": [
      {"senderId": "5511888888888@c.us", "senderName": "João", "timestamp": 1724150000},
      {"senderId": "5511777777777@c.us", "senderName": "Maria", "timestamp": 1724150010}
    ],
    "❤️": [
      {"senderId": "5511666666666@c.us", "senderName": "Pedro", "timestamp": 1724150020}
    ]
  }
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Services/Messaging/OpenWaProvider.php` | Estender `react()` |
| `app/Services/TopwebChat/ReactionService.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/ReactionController.php` | **Criar** (inbound + outbound) |
| `app/Http/Controllers/Api/TopwebChat/WebhookController.php` | Estender (processa message.reaction) |
| `tests/Feature/Interactive/ReactionsTest.php` | **Criar** (5 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::fake([
        'openwa:2785/api/sessions/*/messages/react' => Http::response(['success' => true], 200),
        'openwa:2785/api/sessions/*/messages/*/history?limit=50' => Http::response([], 200),
    ]);
});
```

---

## 6. Evidências GREEN

```bash
php artisan test tests/Feature/Interactive/ReactionsTest.php  # 5 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#19 Message Editing** (message.edited webhook + POST /edit).