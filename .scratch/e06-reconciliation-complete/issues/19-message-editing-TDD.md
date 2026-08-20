# TDD Specification — Slice #19: Message Editing (message.edited webhook + POST /edit)

> **Fase**: RED
> **Dependência**: #18 (reactions infra similar)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Inbound** | Webhook `message.edited` -> Job `ProcessMessageEdit` -> atualiza `Message.content` = novo `body`, `metadata.edited=true`, `metadata.edit_history.push({old_body, new_body, edited_at, edited_by})` | P0 |
| **Outbound** | Operador edita mensagem propria -> modal com texto atual -> `POST /api/sessions/:sessionId/messages/edit` -> `OpenWaProvider::editMessage()` | P0 |
| **Regra** | Apenas mensagens `fromMe=true` (proprias) editaveis | P0 |
| **Persistencia** | `Message.content` = versao atual; `metadata.edit_history` = array de edicoes | P0 |
| **Midia** | Se `hasMedia=true` + `caption` alterado -> atualiza `metadata.caption` | P1 |

---

## 2. Test Specification (RED)

### Teste 1: Inbound Webhook message.edited
```php
// tests/Feature/Interactive/MessageEditingTest.php

it('processes inbound message.edited webhook', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-edit', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $originalMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'content' => 'Texto original',
        'provider_message_id' => 'true_5511999999999@c.us_EDIT123',
        'metadata' => ['edited' => false, 'edit_history' => []],
    ]);

    $payload = [
        'event' => 'message.edited',
        'sessionId' => 'uuid-edit',
        'idempotencyKey' => 'edit_uuid-edit_true_5511999999999@c.us_EDIT123_1724150200',
        'deliveryId' => 'dlv_edit1',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_EDIT123',
            'chatId' => '5511999999999@c.us',
            'body' => 'Texto corrigido',
            'senderId' => '5511888888888@c.us',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'fromMe' => true,
            'isGroup' => false,
            'type' => 'text',
            'hasMedia' => false,
            'timestamp' => 1724150200,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-edit/messages/*/history?limit=50' => Http::response([], 200)]);

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-edit', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
        'X-OpenWA-Event' => 'message.edited',
        'X-OpenWA-Idempotency-Key' => $payload['idempotencyKey'],
    ]);

    $response->assertStatus(200);

    $msg = $originalMsg->fresh();
    expect($msg->content)->toBe('Texto corrigido');
    expect($msg->metadata['edited'])->toBeTrue();
    expect($msg->metadata['edit_history'])->toHaveCount(1);
    expect($msg->metadata['edit_history'][0])->toContainKeys([
        'old_body' => 'Texto original',
        'new_body' => 'Texto corrigido',
        'edited_at' => 1724150200,
        'edited_by' => '5511888888888@c.us',
    ]);
});
```

### Teste 2: Edicao de Caption de Midia
```php
it('updates caption when media message edited', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-edit-caption', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $mediaMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'type' => 'image',
        'content' => 'Legenda original',
        'provider_message_id' => 'true_5511999999999@c.us_EDITCAP123',
        'metadata' => [
            'caption' => 'Legenda original',
            'edited' => false,
            'edit_history' => [],
        ],
    ]);

    $payload = [
        'event' => 'message.edited',
        'sessionId' => 'uuid-edit-caption',
        'idempotencyKey' => 'edit_uuid-cap_true_..._1724150300',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_EDITCAP123',
            'chatId' => '5511999999999@c.us',
            'body' => 'Nova legenda da imagem',
            'senderId' => '5511888888888@c.us',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'fromMe' => true,
            'isGroup' => false,
            'type' => 'image',
            'hasMedia' => true,
            'timestamp' => 1724150300,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-edit-caption/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-edit-caption', $payload, $headers);

    $msg = $mediaMsg->fresh();
    expect($msg->content)->toBe('Nova legenda da imagem');
    expect($msg->metadata['caption'])->toBe('Nova legenda da imagem');
    expect($msg->metadata['edit_history'][0]['old_body'])->toBe('Legenda original');
    expect($msg->metadata['edit_history'][0]['new_body'])->toBe('Nova legenda da imagem');
});
```

### Teste 3: Apenas Mensagens Proprias (fromMe=true) Editaveis
```php
it('only allows editing own messages (fromMe=true)', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-edit-own', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $inboundMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'provider_message_id' => 'true_5511999999999@c.us_INBOUND123',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.send');

    Http::fake([
        'openwa:2785/api/sessions/uuid-edit-own/messages/edit' => Http::response([
            'success' => false,
            'error' => 'Cannot edit messages from other users',
        ], 400),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/edit-message", [
        'message_id' => 'true_5511999999999@c.us_INBOUND123',
        'body' => 'Tentativa de editar msg alheia',
    ]);

    $response->assertStatus(400);
});
```

### Teste 4: Outbound Edicao (Operador Edita)
```php
it('edits own message via POST /edit', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.send');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-out-edit', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $myMsg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'content' => 'Mensagem original',
        'provider_message_id' => 'true_5511999999999@c.us_MYMSG123',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-out-edit/messages/edit' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/edit-message", [
        'message_id' => 'true_5511999999999@c.us_MYMSG123',
        'body' => 'Mensagem editada pelo operador',
    ]);

    $response->assertStatus(200);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://openwa:2785/api/sessions/uuid-out-edit/messages/edit'
            && $request->method() === 'POST'
            && json_decode($request->body(), true)['body'] === 'Mensagem editada pelo operador';
    });
});
```

### Teste 5: Historico de Edicoes Persistido
```php
it('persists edit history with old/new body, timestamp, editor', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-history', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $msg = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'content' => 'Versao 1',
        'provider_message_id' => 'true_5511999999999@c.us_HIST123',
        'metadata' => ['edit_history' => []],
    ]);

    $payload1 = [
        'event' => 'message.edited',
        'sessionId' => 'uuid-history',
        'idempotencyKey' => 'edit_uuid-hist_true_..._1724150000',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_HIST123',
            'chatId' => '5511999999999@c.us',
            'body' => 'Versao 2',
            'senderId' => '5511888888888@c.us',
            'fromMe' => true,
            'timestamp' => 1724150000,
        ],
    ];

    $payload2 = [
        'event' => 'message.edited',
        'sessionId' => 'uuid-history',
        'idempotencyKey' => 'edit_uuid-hist_true_..._1724150100',
        'data' => [
            'messageId' => 'true_5511999999999@c.us_HIST123',
            'chatId' => '5511999999999@c.us',
            'body' => 'Versao 3 final',
            'senderId' => '5511888888888@c.us',
            'fromMe' => true,
            'timestamp' => 1724150100,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-history/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-history', $payload1, $headers);
    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-history', $payload2, $headers);

    $msg = $msg->fresh();
    expect($msg->content)->toBe('Versao 3 final');
    expect($msg->metadata['edit_history'])->toHaveCount(2);
    expect($msg->metadata['edit_history'][0])->toContain([
        'old_body' => 'Versao 1',
        'new_body' => 'Versao 2',
        'edited_at' => 1724150000,
    ]);
    expect($msg->metadata['edit_history'][1])->toContain([
        'old_body' => 'Versao 2',
        'new_body' => 'Versao 3 final',
        'edited_at' => 1724150100,
    ]);
});
```

### Teste 6: UI Mostra Historico de Edicoes
```php
it('UI shows edit history in message tooltip/expansion', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.access');

    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'content' => 'Texto final',
        'metadata' => [
            'edited' => true,
            'edit_history' => [
                ['old_body' => 'Original', 'new_body' => 'Editado 1', 'edited_at' => 1724150000, 'edited_by' => '5511888888888@c.us'],
                ['old_body' => 'Editado 1', 'new_body' => 'Final', 'edited_at' => 1724150100, 'edited_by' => '5511888888888@c.us'],
            ],
        ],
    ]);

    $response = $this->actingAs($operator)->getJson("/api/topweb-chat/conversations/{$conversation->id}/timeline");

    $timeline = $response->json('messages');
    $msg = collect($timeline)->firstWhere('id', $message->id);

    expect($msg['metadata']['edited'])->toBeTrue();
    expect($msg['metadata']['edit_history'])->toHaveCount(2);
    expect($msg['metadata']['edit_history'][0]['old_body'])->toBe('Original');
    expect($msg['metadata']['edit_history'][1]['new_body'])->toBe('Final');
});
```

---

## 3. Interface Contracts

### OpenWaProvider::editMessage()
```php
// app/Services/Messaging/OpenWaProvider.php

public function editMessage(string $sessionUuid, string $chatId, string $messageId, string $body): array {
    return $this->post("sessions/{$sessionUuid}/messages/edit", [
        'chatId' => $chatId,
        'messageId' => $messageId,
        'body' => $body,
    ]);
}
```

### Edit Service
```php
// app/Services/TopwebChat/MessageEditService.php

class MessageEditService {
    public function processInbound(string $sessionUuid, array $data): void {
        // 1. Find message by provider_message_id
        // 2. Verify fromMe=true (only own messages editable)
        // 3. Store old_body = current content
        // 4. Update content = new body
        // 5. Push to edit_history: {old_body, new_body, edited_at, edited_by}
        // 6. Set metadata.edited = true
    }

    public function sendEdit(string $sessionUuid, string $chatId, string $messageId, string $body): void {
        app(OpenWaProvider::class)->editMessage($sessionUuid, $chatId, $messageId, $body);
    }
}
```

### Metadata Structure
```json
{
  "edited": true,
  "edit_history": [
    {
      "old_body": "Texto original",
      "new_body": "Texto corrigido",
      "edited_at": 1724150200,
      "edited_by": "5511888888888@c.us"
    },
    {
      "old_body": "Texto corrigido",
      "new_body": "Texto final",
      "edited_at": 1724150300,
      "edited_by": "5511888888888@c.us"
    }
  }
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Acao |
|---------|------|
| `app/Services/Messaging/OpenWaProvider.php` | Estender `editMessage()` |
| `app/Services/TopwebChat/MessageEditService.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/MessageEditController.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/WebhookController.php` | Estender (processa message.edited) |
| `tests/Feature/Interactive/MessageEditingTest.php` | **Criar** (6 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::fake([
        'openwa:2785/api/sessions/*/messages/edit' => Http::response(['success' => true], 200),
        'openwa:2785/api/sessions/*/messages/*/history?limit=50' => Http::response([], 200),
    ]);
});
```

---

## 6. Evidencias GREEN

```bash
php artisan test tests/Feature/Interactive/MessageEditingTest.php  # 6 pass
./vendor/bin/pint
```

---

## Proximo Slice

Apos GREEN -> **#20 Groups Domain** (ACL + auditoria propria).