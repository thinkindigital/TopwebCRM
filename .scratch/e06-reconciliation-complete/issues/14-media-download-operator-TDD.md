# TDD Specification — Slice #14: Media Download Operator (rota autenticada com media_token)

> **Fase**: RED
> **Dependência**: #13 (media upload + token)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Rota** | `GET /api/topweb-chat/media/{messageId}?token={media_token}` | P0 |
| **Middleware** | `MediaTokenValidator` decodifica JWT → valida assinatura, expiração, `message_id` match | P0 |
| **Autorização** | `ConversationAccessService::canView(conversation, user)` | P0 |
| **Response** | `response()->file()` com headers: `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Content-Type` conservador | P0 |
| **Logs** | `media.downloaded` com `message_id`, `operator_id`, `ip` | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Rota com Token Válido Retorna Arquivo
```php
// tests/Feature/Media/OperatorMediaDownloadTest.php

it('downloads media with valid token', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.access');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-download', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'type' => 'image',
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$message->id}.jpg",
            'mimetype' => 'image/jpeg',
            'filename' => 'photo.jpg',
            'sizeBytes' => 102400,
            'sha256' => 'abc123',
        ],
    ]);

    Storage::disk('private')->put("topweb_chat/{$conversation->id}/{$message->id}.jpg", str_repeat('JPG_DATA', 12800));

    $token = JWT::encode([
        'message_id' => $message->id,
        'conversation_id' => $conversation->id,
        'operator_id' => $operator->id,
        'exp' => now()->addHour()->timestamp,
    ], config('app.key'), 'HS256');

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$token}");

    $response->assertStatus(200);
    $response->assertHeader('Content-Disposition', 'attachment; filename="photo.jpg"');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Content-Type', 'image/jpeg');
});
```

### Teste 2: Middleware Valida JWT (Assinatura, Expiração, Message ID)
```php
it('rejects invalid token signature', function () {
    $operator = User::factory()->create();
    $message = Message::factory()->create();

    $invalidToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.invalid.signature';

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$invalidToken}");
    $response->assertStatus(401);
});

it('rejects expired token', function () {
    $operator = User::factory()->create();
    $message = Message::factory()->create();

    $expiredToken = JWT::encode([
        'message_id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'operator_id' => 999,
        'exp' => now()->subHour()->timestamp,
    ], config('app.key'), 'HS256');

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$expiredToken}");
    $response->assertStatus(401);
});

it('rejects token for different message_id', function () {
    $operator = User::factory()->create();
    $message1 = Message::factory()->create();
    $message2 = Message::factory()->create();

    $token = JWT::encode([
        'message_id' => $message1->id,
        'conversation_id' => $message1->conversation_id,
        'operator_id' => $operator->id,
        'exp' => now()->addHour()->timestamp,
    ], config('app.key'), 'HS256');

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message2->id}?token={$token}");
    $response->assertStatus(401);
});
```

### Teste 3: ConversationAccessService Autoriza Acesso
```php
it('authorizes access via ConversationAccessService', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.access');

    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create(['conversation_id' => $conversation->id]);

    $token = JWT::encode([
        'message_id' => $message->id,
        'conversation_id' => $conversation->id,
        'operator_id' => $operator->id,
        'exp' => now()->addHour()->timestamp,
    ], config('app.key'), 'HS256');

    $this->mock(ConversationAccessService::class, function ($mock) {
        $mock->shouldReceive('canView')->andReturn(true);
    });

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$token}");
    $response->assertStatus(200);
});

it('denies access if ConversationAccessService forbids', function () {
    $operator = User::factory()->create();
    $message = Message::factory()->create();

    $token = JWT::encode([/* valid payload */], config('app.key'), 'HS256');

    $this->mock(ConversationAccessService::class, function ($mock) {
        $mock->shouldReceive('canView')->andReturn(false);
    });

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$token}");
    $response->assertStatus(403);
});
```

### Teste 4: Headers de Segurança + Content-Type Conservador
```php
it('returns security headers and conservative Content-Type', function () {
    $operator = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$message->id}.svg",
            'mimetype' => 'image/svg+xml',
            'filename' => 'chart.svg',
        ],
    ]);
    Storage::disk('private')->put("topweb_chat/{$conversation->id}/{$message->id}.svg", '<svg>test</svg>');

    $token = JWT::encode([/* valid */], config('app.key'), 'HS256');

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$token}");

    $response->assertStatus(200);
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Content-Disposition', 'attachment; filename="chart.svg"');
    $response->assertHeader('Content-Type', 'application/octet-stream');
});
```

### Teste 5: Log de Download
```php
it('logs media.downloaded with operator info', function () {
    $operator = User::factory()->create();
    $conversation = Conversation::factory()->create();
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => ['storage_path' => "topweb_chat/{$conversation->id}/{$message->id}.pdf", 'mimetype' => 'application/pdf', 'filename' => 'doc.pdf'],
    ]);
    Storage::disk('private')->put("topweb_chat/{$conversation->id}/{$message->id}.pdf", '%PDF-test');

    $token = JWT::encode([/* valid */], config('app.key'), 'HS256');

    $downloadLogs = [];
    Log::shouldReceive('channel')->with('media')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($m, $c) use (&$downloadLogs) {
        if (isset($c['event']) && $c['event'] === 'media.downloaded') $downloadLogs[] = $c;
    });

    $response = $this->actingAs($operator)->get("/api/topweb-chat/media/{$message->id}?token={$token}");

    $response->assertStatus(200);
    expect($downloadLogs)->toHaveCount(1);
    expect($downloadLogs[0]['context'])->toContainKeys([
        'event' => 'media.downloaded',
        'message_id' => $message->id,
        'operator_id' => $operator->id,
        'ip' => 'unknown',
    ]);
});
```

---

## 3. Interface Contracts

### Middleware
```php
// app/Http/Middleware/MediaTokenValidator.php

class MediaTokenValidator {
    public function handle(Request $request, Closure $next): Response {
        $token = $request->query('token');
        
        try {
            $payload = JWT::decode($token, new Key(config('app.key'), 'HS256'));
            
            if ($payload->message_id !== $request->route('messageId')) {
                return response()->json(['error' => 'Token mismatch'], 401);
            }
            
            if ($payload->exp < now()->timestamp) {
                return response()->json(['error' => 'Token expired'], 401);
            }
            
            $request->merge(['media_token_payload' => $payload]);
            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }
}
```

### Route
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'media.token'])->group(function () {
    Route::get('/topweb-chat/media/{messageId}', [MediaController::class, 'download'])
        ->name('topweb-chat.media.download');
});
```

### Controller
```php
// app/Http/Controllers/Api/TopwebChat/MediaController.php

public function download(string $messageId, Request $request): BinaryFileResponse {
    $payload = $request->attributes->get('media_token_payload');
    $message = Message::findOrFail($payload->message_id);
    
    if (!app(ConversationAccessService::class)->canView($message->conversation, $request->user())) {
        abort(403);
    }
    
    $meta = $message->metadata;
    
    if (!Storage::disk('private')->exists($meta['storage_path'])) {
        abort(404);
    }
    
    $mimetype = $this->getSafeMimetype($meta['mimetype'], $meta['storage_path']);
    
    return response()->file(
        storage_path("app/private/{$meta['storage_path']}"),
        [
            'Content-Disposition' => "attachment; filename=\"{$meta['filename']}\"",
            'Content-Type' => $mimetype,
            'X-Content-Type-Options' => 'nosniff',
        ]
    );
}

private function getSafeMimetype(string $declared, string $path): string {
    $inertMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
    if (in_array($declared, $inertMimes)) return $declared;
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected = finfo_file($finfo, storage_path("app/private/{$path}"));
    finfo_close($finfo);
    
    return in_array($detected, $inertMimes) ? $detected : 'application/octet-stream';
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Http/Middleware/MediaTokenValidator.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/MediaController.php` | Estender (download) |
| `routes/api.php` | Registrar rota + middleware |
| `config/topweb-chat.php` | Config download |
| `tests/Feature/Media/OperatorMediaDownloadTest.php` | **Criar** (5 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Storage::fake('private');
    JWT::setKey(config('app.key'));
});
```

---

## 5. Evidências GREEN

```bash
php artisan test tests/Feature/Media/OperatorMediaDownloadTest.php  # 5 pass
./vendor/bin/pint
```

---

## 6. Próximo Slice

Após GREEN → **#15 Media Retention Policy** (TTL configurável, cleanup agendado).