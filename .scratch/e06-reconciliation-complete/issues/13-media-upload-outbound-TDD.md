# TDD Specification — Slice #13: Media Upload Outbound (media_token JWT, base64 ou URL assinada)

> **Fase**: RED
> **Dependência**: #11-12 (media download inbound)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Frontend Upload** | Drag & drop / file input → validação client-side (MIME/tamanho) → POST `/api/topweb-chat/media/upload` | P0 |
| **Backend Upload** | Recebe multipart/form-data → valida MIME/tamanho (≤ 50 MiB) → salva temp em `storage/app/private/temp/{operator_id}/{conversation_id}/{uuid}.{ext}` | P0 |
| **Media Token** | Retorna `media_token` (JWT assinado, expiração 1h) + `expires_at` | P0 |
| **Envio Outbound** | Se `media_token` presente → `OpenWaProvider::sendMedia()` usa `base64` (≤ 10 MiB) OU `url` (rota assinada MinIO/S3 futuro) | P0 |
| **Cleanup** | Job `CleanupTempMedia` remove temp após envio bem-sucedido OU expiração token (1h) | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Frontend → Backend Upload → Media Token
```php
// tests/Feature/Media/OutboundMediaUploadTest.php

it('uploads media via API and returns media_token (JWT)', function () {
    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.send');

    $conversation = Conversation::factory()->create();

    $imageContent = file_get_contents(base_path('tests/fixtures/test-image.jpg')); // ~500KB

    $response = $this->actingAs($operator)->postMultipart('/api/topweb-chat/media/upload', [
        'file' => new \Illuminate\Http\UploadedFile(
            path: sys_get_temp_dir() . '/test-image.jpg',
            originalName: 'photo.jpg',
            mimeType: 'image/jpeg',
            size: strlen($imageContent),
        ),
        'conversation_id' => $conversation->id,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'media_token',
            'expires_at',
            'mimetype',
            'filename',
            'size',
        ]);

    $tokenData = json_decode(base64_decode(explode('.', $response->json('media_token'))[1]), true);
    expect($tokenData)->toContainKeys(['message_id', 'conversation_id', 'operator_id', 'exp', 'path']);
});
```

### Teste 2: Backend Valida MIME/Tamanho + Salva Temp
```php
it('validates MIME/size server-side and saves to temp storage', function () {
    $operator = User::factory()->create();
    $conversation = Conversation::factory()->create();

    // Valid image
    $validImage = str_repeat('JPEG', 100000); // ~400KB
    $response = $this->actingAs($operator)->postMultipart('/api/topweb-chat/media/upload', [
        'file' => UploadedFile::fake()->create('photo.jpg', 400, 'image/jpeg'),
        'conversation_id' => $conversation->id,
    ]);
    $response->assertStatus(200);

    // Invalid MIME
    $response = $this->actingAs($operator)->postMultipart('/api/topweb-chat/media/upload', [
        'file' => UploadedFile::fake()->create('shell.php', 100, 'application/x-php'),
        'conversation_id' => $conversation->id,
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');

    // Oversize (> 50MB)
    $response = $this->actingAs($operator)->postMultipart('/api/topweb-chat/media/upload', [
        'file' => UploadedFile::fake()->create('huge.mp4', 60000, 'video/mp4'), // 60MB
        'conversation_id' => $conversation->id,
    ]);
    $response->assertStatus(422);
});
```

### Teste 3: Temp Path Pattern
```php
it('saves temp file with correct path pattern', function () {
    $operator = User::factory()->create();
    $conversation = Conversation::factory()->create();

    $response = $this->actingAs($operator)->postMultipart('/api/topweb-chat/media/upload', [
        'file' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        'conversation_id' => $conversation->id,
    ]);

    $token = $response->json('media_token');
    $payload = json_decode(base64_decode(explode('.', $token)[1]), true);

    // Path: temp/{operator_id}/{conversation_id}/{uuid}.{ext}
    expect($payload['path'])->toMatch('/^temp\/' . $operator->id . '\/' . $conversation->id . '\/[a-f0-9-]+\.(jpg|jpeg|png|webp)$/');
    expect(Storage::disk('private')->exists($payload['path']))->toBeTrue();
});
```

### Teste 4: Envio Usa base64 (≤10MB) OU URL Assinada (MinIO Futuro)
```php
it('sends via base64 for files ≤ 10MB, signed URL for larger', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-send', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $operator = User::factory()->create();

    // Upload 5MB image → should use base64
    $mediaToken = $this->uploadMedia($operator, $conversation, 5 * 1024 * 1024); // 5MB
    $tokenPayload = json_decode(base64_decode(explode('.', $mediaToken)[1]), true);

    Http::fake([
        'openwa:2785/api/sessions/uuid-send/messages/send-image' => Http::response([
            'messageId' => 'true_5511999999999@c.us_SENT123',
            'timestamp' => 1724150000,
        ], 201),
    ]);

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/send-media", [
        'media_token' => $mediaToken,
        'caption' => 'Test',
    ]);

    $response->assertStatus(200);

    // Assert: OpenWA received base64 (file ≤ 10MB)
    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return isset($body['base64']) && strlen($body['base64']) > 1000000; // ~5MB base64
    });

    // Cleanup: temp file removed after successful send
    // (handled by CleanupTempMedia job)
});
```

### Teste 5: Cleanup Job Remove Temp Após Envio/Expiração
```php
it('cleanup job removes temp file after successful send or token expiration', function () {
    $operator = User::factory()->create();
    $conversation = Conversation::factory()->create();

    $mediaToken = $this->uploadMedia($operator, $conversation, 100000);
    $tokenPayload = json_decode(base64_decode(explode('.', $mediaToken)[1]), true);
    $tempPath = $tokenPayload['path'];

    expect(Storage::disk('private')->exists($tempPath))->toBeTrue();

    // Simulate successful send → cleanup job runs
    $cleanupJob = new CleanupTempMedia($mediaToken);
    $cleanupJob->handle();

    expect(Storage::disk('private')->exists($tempPath))->toBeFalse();

    // Test expiration cleanup (token expired)
    $expiredToken = $this->createExpiredToken($operator, $conversation);
    $expiredPayload = json_decode(base64_decode(explode('.', $expiredToken)[1]), true);
    
    $cleanupJob2 = new CleanupTempMedia($expiredToken);
    $cleanupJob2->handle();

    expect(Storage::disk('private')->exists($expiredPayload['path']))->toBeFalse();
});
```

---

## 3. Interface Contracts

### Controller
```php
// app/Http/Controllers/Api/TopwebChat/MediaController.php

class MediaController extends Controller {
    public function upload(Request $request): JsonResponse {
        // 1. Validate request (file, conversation_id)
        // 2. Save to temp storage
        // 3. Generate media_token (JWT)
        // 4. Return token + metadata
    }

    public function download(string $messageId, Request $request): BinaryFileResponse {
        // 1. Validate media_token query param
        // 2. Check ConversationAccessService
        // 3. Serve file from private storage
    }
}
```

### Media Token (JWT)
```php
// Payload:
{
    "message_id": "uuid",
    "conversation_id": "uuid",
    "operator_id": 123,
    "path": "temp/123/conv-uuid/abc123.jpg",
    "mimetype": "image/jpeg",
    "filename": "photo.jpg",
    "size": 500000,
    "exp": 1692567600, // 1 hour
    "iat": 1692564000
}
```

### Cleanup Job
```php
// app/Jobs/Media/CleanupTempMedia.php

class CleanupTempMedia implements ShouldQueue {
    public $queue = 'topweb_chat_media';
    public $tries = 3;

    public function __construct(public string $media_token) {}

    public function handle(MediaCleanupService $service): void {
        $service->cleanup($this->media_token);
    }
}
```

### Config
```php
// config/topweb-chat.php
'media' => [
    'temp_ttl_hours' => 1,
    'base64_max_mb' => 10, // threshold for base64 vs signed URL
    'cleanup_schedule' => 'hourly',
],
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Http/Controllers/Api/TopwebChat/MediaController.php` | **Criar** |
| `app/Jobs/Media/CleanupTempMedia.php` | **Criar** |
| `app/Services/Media/MediaUploadService.php` | **Criar** |
| `app/Services/Media/MediaTokenService.php` | **Criar** (JWT) |
| `app/Services/Media/MediaCleanupService.php` | **Criar** |
| `routes/api.php` | Registrar rotas media |
| `config/topweb-chat.php` | Estender config media |
| `tests/Feature/Media/OutboundMediaUploadTest.php` | **Criar** (5 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Storage::fake('private');
    Queue::fake([CleanupTempMedia::class]);
    Http::fake([
        'openwa:2785/api/sessions/*/messages/send-*' => Http::response([], 404),
    ]);
});
```

---

## 6. Evidências GREEN

```bash
php artisan test tests/Feature/Media/OutboundMediaUploadTest.php  # 5 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#14 Media Download Operator** (rota autenticada com media_token).