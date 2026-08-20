# TDD Specification — Slice #11: Media Download Inbound Inline (base64 ≤ 1MiB)

> **Fase**: RED (especificação de testes antes da implementação)
> **Dependência**: #01-05 (reconciliação base + webhook handler disparando job)

---

## 1. Comportamentos Críticos (Scope)

| Comportamento | Descrição | Prioridade |
|---------------|-----------|------------|
| **Job Trigger** | Webhook `message.received` com `hasMedia=true` + `media.data` (base64) dispara `DownloadMedia` job na queue `topweb_chat_media` | P0 |
| **Decode & Save** | Decodifica base64 → valida MIME/tamanho (≤ 1MiB inline budget) → salva em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}` | P0 |
| **MIME Validation** | Valida MIME contra lista permitida + magic bytes (finfo) | P0 |
| **Size Limit** | Rejeita se decoded size > 1MiB (inline budget) | P0 |
| **Metadata Persistence** | Atualiza `Message.metadata` (criptografado) com: mimetype, filename, sizeBytes, sha256, storage_path, downloaded_at, source="inline_base64" | P0 |
| **Failure Handling** | MIME inválido, >1MiB, decode error → log error + `Message.metadata.media_download_failed=true` + alerta | P0 |
| **Queue** | Job na queue `topweb_chat_media` (assíncrono, não bloqueia webhook) | P0 |

---

## 2. Test Specification (RED Phase)

### Teste 1: Job Disparado pelo Webhook Handler
```php
// tests/Feature/Media/InlineMediaDownloadTest.php

it('dispatches DownloadMedia job when webhook has inline media', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-inline-media',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Mock webhook payload with inline media
    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-inline-media',
        'idempotencyKey' => 'msg_uuid-inline_true_5511999999999@c.us_MEDIA123',
        'deliveryId' => 'dlv_media1',
        'data' => [
            'id' => 'true_5511999999999@c.us_MEDIA123',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'body' => '',
            'type' => 'image',
            'timestamp' => 1724150000,
            'isGroup' => false,
            'kind' => 'individual',
            'hasMedia' => true,
            'media' => [
                'mimetype' => 'image/jpeg',
                'filename' => 'photo.jpg',
                'data' => base64_encode(str_repeat('x', 50000)), // ~50KB base64
                'sizeBytes' => 50000,
            ],
        ],
    ];

    // Mock job dispatch
    Queue::fake([DownloadMedia::class]);

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-inline-media', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
        'X-OpenWA-Event' => 'message.received',
        'X-OpenWA-Idempotency-Key' => 'msg_uuid-inline_true_5511999999999@c.us_MEDIA123',
        'X-OpenWA-Delivery-Id' => 'dlv_media1',
    ]);

    $response->assertStatus(200);

    // Assert: job dispatched to correct queue
    Queue::assertPushed(DownloadMedia::class, function ($job) {
        return $job->message->provider_message_id === 'true_5511999999999@c.us_MEDIA123'
            && $job->queue === 'topweb_chat_media';
    });
});
```

### Teste 2: Decodifica Base64 e Salva Arquivo
```php
it('decodes base64 and saves file to private storage', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-save', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'type' => 'image',
        'provider_message_id' => 'true_5511999999999@c.us_SAVE123',
        'status' => 'received',
    ]);

    // Create job with inline media data
    $base64Data = base64_encode(str_repeat('JPEG_DATA_', 5000)); // ~50KB
    $job = new DownloadMedia(
        message: $message,
        mediaData: [
            'mimetype' => 'image/jpeg',
            'filename' => 'photo.jpg',
            'data' => $base64Data,
            'sizeBytes' => 50000,
        ],
        source: 'inline_base64',
    );

    $job->handle();

    // Assert: file saved in private storage
    $expectedPath = "topweb_chat/{$conversation->id}/{$message->id}.jpg";
    expect(Storage::disk('private')->exists($expectedPath))->toBeTrue();

    // Assert: file content matches
    $savedContent = Storage::disk('private')->get($expectedPath);
    expect($savedContent)->toBe(str_repeat('JPEG_DATA_', 5000));
});
```

### Teste 3: Valida MIME Type (Lista Permitida + Magic Bytes)
```php
it('validates MIME type against allowed list and magic bytes', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-mime', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'mime-test']);

    // Test allowed MIME: image/jpeg
    $job = new DownloadMedia($message, [
        'mimetype' => 'image/jpeg',
        'filename' => 'photo.jpg',
        'data' => base64_encode("\xFF\xD8\xFF\xE0"), // JPEG magic bytes
        'sizeBytes' => 4,
    ], 'inline_base64');

    $job->handle();
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/{$message->id}.jpg"))->toBeTrue();

    // Test rejected MIME: application/x-php (even with .jpg extension)
    $message2 = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'mime-test2']);
    $job2 = new DownloadMedia($message2, [
        'mimetype' => 'image/jpeg',
        'filename' => 'shell.jpg',
        'data' => base64_encode("<?php echo 'hacked'; ?>"), // PHP magic bytes
        'sizeBytes' => 25,
    ], 'inline_base64');

    $job2->handle();

    // Should fail: magic bytes don't match declared MIME
    $meta = $message2->fresh()->metadata;
    expect($meta['media_download_failed'])->toBeTrue();
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/{$message2->id}.jpg"))->toBeFalse();
});
```

### Teste 4: Rejeita Tamanho > 1MiB (Inline Budget)
```php
it('rejects media larger than 1MiB inline budget', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-size', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'size-test']);

    // 2MB base64 (exceeds 1MiB inline budget)
    $largeBase64 = base64_encode(str_repeat('x', 2 * 1024 * 1024));

    $job = new DownloadMedia($message, [
        'mimetype' => 'video/mp4',
        'filename' => 'large.mp4',
        'data' => $largeBase64,
        'sizeBytes' => 2 * 1024 * 1024,
    ], 'inline_base64');

    $job->handle();

    $meta = $message->fresh()->metadata;
    expect($meta['media_download_failed'])->toBeTrue();
    expect($meta['media_download_error'])->toContain('exceeds inline budget');
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/{$message->id}.mp4"))->toBeFalse();
});
```

### Teste 4: Metadados Completos Persistidos (Criptografados)
```php
it('persists complete metadata encrypted in Message.metadata', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-meta', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'provider_message_id' => 'true_5511999999999@c.us_META123',
    ]);

    $base64Data = base64_encode("fake image data");
    $job = new DownloadMedia($message, [
        'mimetype' => 'image/png',
        'filename' => 'screenshot.png',
        'data' => $base64Data,
        'sizeBytes' => strlen($base64Data),
    ], 'inline_base64');

    $job->handle();

    $meta = $message->fresh()->metadata;
    
    // Assert all required fields present
    expect($meta)->toContainKeys([
        'mimetype', 'filename', 'sizeBytes', 'sha256', 
        'storage_path', 'downloaded_at', 'source'
    ]);
    
    expect($meta['mimetype'])->toBe('image/png');
    expect($meta['filename'])->toBe('screenshot.png');
    expect($meta['sizeBytes'])->toBe(strlen($base64Data));
    expect($meta['sha256'])->toBe(hash('sha256', $base64Data));
    expect($meta['storage_path'])->toBe("topweb_chat/{$conversation->id}/{$message->id}.png");
    expect($meta['source'])->toBe('inline_base64');
    expect($meta['downloaded_at'])->not->toBeNull();
    expect($meta['media_download_failed'])->toBeFalse();
});
```

### Teste 5: Falha → Log Error + Flag + Alerta
```php
it('logs error, sets flag, and alerts on failure', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-fail', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'fail-test']);

    // Invalid base64 (decode error)
    $job = new DownloadMedia($message, [
        'mimetype' => 'image/jpeg',
        'filename' => 'bad.jpg',
        'data' => '!!!not-base64!!!',
        'sizeBytes' => 100,
    ], 'inline_base64');

    $errorLogs = [];
    Log::shouldReceive('error')->andReturnUsing(function ($m, $c) use (&$errorLogs) {
        $errorLogs[] = compact('message' => $m, 'context' => $c);
    });

    $alertLogs = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($m, $c) use (&$alertLogs) {
        if (isset($c['event']) && $c['event'] === 'media.download_failed') $alertLogs[] = $c;
    });

    $job = new DownloadMedia($message, [
        'mimetype' => 'image/jpeg',
        'filename' => 'bad.jpg',
        'data' => '!!!not-base64!!!',
        'sizeBytes' => 100,
    ], 'inline_base64');

    $job->handle();

    $meta = $message->fresh()->metadata;
    expect($meta['media_download_failed'])->toBeTrue();
    expect($meta['media_download_error'])->not->toBeNull();

    expect($errorLogs)->toHaveCount(1);
    expect($alertLogs)->toHaveCount(1);
    expect($alertLogs[0]['context']['event'])->toBe('media.download_failed');
    expect($alertLogs[0]['context']['message_id'])->toBe($message->id);
    expect($alertLogs[0]['context']['source'])->toBe('inline_base64');
});
```

### Teste 6: Job na Queue Correta
```php
it('runs on topweb_chat_media queue', function () {
    $job = new DownloadMedia(
        message: Message::factory()->make(),
        mediaData: ['mimetype' => 'image/jpeg', 'data' => base64_encode('x'), 'sizeBytes' => 1],
        source: 'inline_base64',
    );

    expect($job->queue)->toBe('topweb_chat_media');
});
```

### Teste 7: Sticker Detection (image/webp < 100KB)
```php
it('detects stickers automatically (image/webp < 100KB)', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-sticker', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'sticker-test']);

    // Small webp = sticker
    $stickerData = base64_encode(str_repeat('WEBP', 5000)); // ~20KB
    $job = new DownloadMedia($message, [
        'mimetype' => 'image/webp',
        'filename' => 'sticker.webp',
        'data' => $stickerData,
        'sizeBytes' => strlen($stickerData),
    ], 'inline_base64');

    $job->handle();

    $meta = $message->fresh()->metadata;
    expect($meta['is_sticker'])->toBeTrue();
    expect($meta['mimetype'])->toBe('image/webp');
});
```

---

## 3. Interface Contracts

### Job Class
```php
// app/Jobs/Media/DownloadMedia.php

class DownloadMedia implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'topweb_chat_media';
    public $tries = 3;
    public $timeout = 60;

    public function __construct(
        public Message $message,
        public array $mediaData, // ['mimetype', 'filename', 'data' (base64), 'sizeBytes']
        public string $source // 'inline_base64' | 'omitted_stream'
    ) {}

    public function handle(MediaDownloadService $service): void {
        $service->downloadInline($this->message, $this->mediaData);
    }
}
```

### Service
```php
// app/Services/Media/MediaDownloadService.php

class MediaDownloadService {
    public function __construct(
        protected MediaValidator $validator,
        protected MediaStorage $storage,
        protected MediaMetadataService $metadata,
        protected ReconciliationLogger $logger
    ) {}

    public function downloadInline(Message $message, array $mediaData): void {
        // 1. Validate MIME + size
        // 2. Decode base64
        // 3. Save to private storage
        // 4. Compute SHA256
        // 5. Persist encrypted metadata
    }
}
```

### Validator
```php
// app/Services/Media/MediaValidator.php

class MediaValidator {
    const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/3gp', 'video/quicktime',
        'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.*',
        'application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint',
    ];

    public function validateInline(string $mimetype, string $base64Data): void {
        // 1. Check allowed MIME
        // 2. Decode base64 → check size ≤ 1MiB
        // 3. Verify magic bytes match MIME
    }
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Jobs/Media/DownloadMedia.php` | **Criar** |
| `app/Services/Media/MediaDownloadService.php` | **Criar** |
| `app/Services/Media/MediaValidator.php` | **Criar** |
| `app/Services/Media/MediaStorage.php` | **Criar** |
| `app/Services/Media/MediaMetadataService.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/WebhookController.php` | Estender (dispara job) |
| `config/topweb-chat.php` | Adicionar `media.allowed_mimes`, `media.inline_budget_bytes` |
| `tests/Feature/Media/InlineMediaDownloadTest.php` | **Criar** (7 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Queue::fake([DownloadMedia::class]);
    Storage::fake('private');
    Http::preventStrayRequests();
});
```

---

## 6. Evidências GREEN

```bash
php artisan queue:work topweb_chat_media --once  # processa job
php artisan test tests/Feature/Media/InlineMediaDownloadTest.php  # 7 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#12 Media Download Inbound Omitted** (stream download >1MiB via GET /media).