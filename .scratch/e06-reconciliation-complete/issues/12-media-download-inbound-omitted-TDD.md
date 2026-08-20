# TDD Specification — Slice #12: Media Download Inbound Omitted (stream >1MiB via GET /media)

> **Fase**: RED
> **Dependência**: #11 (inline media infra)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Trigger** | Webhook `message.received` com `hasMedia=true` + `media.omitted=true` + `sizeBytes > 1MiB` | P0 |
| **Stream Download** | Job `DownloadMedia` → `GET /api/sessions/:sessionId/messages/:chatId/:messageId/media` (stream) | P0 |
| **Chunked Save** | Salva em chunks (memory efficient) em `storage/app/private/topweb_chat/{conversation_id}/{message_id}.{ext}` | P0 |
| **Validation** | Valida `Content-Type` header vs MIME permitidos + `Content-Length` ≤ 50 MiB | P0 |
| **Metadata** | `source="omitted_stream"` + metadados completos | P0 |
| **Timeout/Retry** | Timeout 60s (config), retry 2x com backoff exponencial | P0 |
| **Failure** → log + flag + alerta | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Stream Download via GET /media
```php
// tests/Feature/Media/OmittedMediaDownloadTest.php

it('downloads omitted media via GET /media stream', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-omitted', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'type' => 'video',
        'provider_message_id' => 'true_5511999999999@c.us_OMIT123',
    ]);

    // Mock OpenWA GET /media endpoint returning stream
    $videoContent = str_repeat('VIDEO_DATA_', 100000); // ~1.2MB
    Http::fake([
        'openwa:2785/api/sessions/uuid-omitted/messages/5511999999999@c.us/true_5511999999999@c.us_OMIT123/media' => Http::response($videoContent, 200, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => strlen($videoContent),
            'Content-Disposition' => 'attachment; filename="video.mp4"',
        ]),
    ]);

    $job = new DownloadMedia(
        message: $message,
        mediaData: [
            'mimetype' => 'video/mp4',
            'filename' => 'video.mp4',
            'sizeBytes' => strlen($videoContent),
        ],
        source: 'omitted_stream',
    );

    $job->handle();

    // Assert: file saved via stream
    $expectedPath = "topweb_chat/{$conversation->id}/{$message->id}.mp4";
    expect(Storage::disk('private')->exists($expectedPath))->toBeTrue();
    expect(Storage::disk('private')->get($expectedPath))->toBe($videoContent);
});
```

### Teste 2: Chunked Save (Memory Efficient)
```php
it('saves large files in chunks to avoid memory exhaustion', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-chunk', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'chunk-test']);

    // Large file: 10MB
    $largeContent = str_repeat('CHUNK_', 1024 * 1024 * 10 / 6);
    
    Http::fake([
        'openwa:2785/api/sessions/uuid-chunk/messages/*/media' => Http::response(
            fn() => $largeContent, // generator for streaming
            200,
            ['Content-Type' => 'video/mp4', 'Content-Length' => strlen($largeContent)]
        ),
    ]);

    // Monitor memory usage
    $memoryBefore = memory_get_peak_usage(true);
    
    $job = new DownloadMedia($message, [
        'mimetype' => 'video/mp4',
        'filename' => 'large.mp4',
        'sizeBytes' => strlen($largeContent),
    ], 'omitted_stream');

    $job->handle();

    $memoryAfter = memory_get_peak_usage(true);
    $memoryUsed = $memoryAfter - $memoryBefore;
    
    // Should not load entire file in memory at once (chunked)
    expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // < 50MB peak
    
    // File saved correctly
    expect(Storage::disk('private')->get("topweb_chat/{$conversation->id}/{$message->id}.mp4"))->toBe($largeContent);
});
```

### Teste 3: Valida Content-Type Header vs Permitidos
```php
it('validates Content-Type header against allowed MIME types', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-ct', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'ct-test']);

    // OpenWA returns executable but declares video/mp4
    Http::fake([
        'openwa:2785/api/sessions/uuid-ct/messages/*/media' => Http::response(
            "<?php echo 'hack'; ?>",
            200,
            ['Content-Type' => 'application/x-php', 'Content-Length' => 25]
        ),
    ]);

    $job = new DownloadMedia($message, [
        'mimetype' => 'video/mp4', // declared
        'filename' => 'fake.mp4',
        'sizeBytes' => 25,
    ], 'omitted_stream');

    $job->handle();

    $meta = $message->fresh()->metadata;
    expect($meta['media_download_failed'])->toBeTrue();
    expect($meta['media_download_error'])->toContain('MIME mismatch');
});
```

### Teste 4: Valida Content-Length ≤ 50 MiB
```php
it('rejects files larger than 50 MiB (MEDIA_DOWNLOAD_MAX_BYTES)', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-large', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'large-test']);

    // 60MB content
    $hugeContent = str_repeat('X', 60 * 1024 * 1024);
    
    Http::fake([
        'openwa:2785/api/sessions/uuid-large/messages/*/media' => Http::response(
            $hugeContent,
            200,
            ['Content-Type' => 'video/mp4', 'Content-Length' => strlen($hugeContent)]
        ),
    ]);

    $job = new DownloadMedia($message, [
        'mimetype' => 'video/mp4',
        'filename' => 'huge.mp4',
        'sizeBytes' => strlen($hugeContent),
    ], 'omitted_stream');

    $job->handle();

    $meta = $message->fresh()->metadata;
    expect($meta['media_download_failed'])->toBeTrue();
    expect($meta['media_download_error'])->toContain('exceeds 50 MiB limit');
});
```

### Teste 5: Timeout 60s + Retry 2x com Backoff
```php
it('times out after 60s and retries 2x with exponential backoff', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-timeout', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'timeout-test']);

    Http::fake([
        'openwa:2785/api/sessions/uuid-timeout/messages/*/media' => Http::response(
            fn() => sleep(70), // exceeds 60s timeout
            200,
            ['Content-Type' => 'image/jpeg']
        ),
    ]);

    $job = new DownloadMedia($message, [
        'mimetype' => 'image/jpeg',
        'sizeBytes' => 1000,
    ], 'omitted_stream');

    $job->tries = 3; // 1 initial + 2 retries
    $job->backoff = [10, 30]; // exponential backoff seconds

    $start = microtime(true);
    $job->handle();
    $elapsed = microtime(true) - $start;

    // Should have retried 2x with backoff (10s + 30s = ~40s min)
    expect($elapsed)->toBeGreaterThan(35); 
    
    $meta = $message->fresh()->metadata;
    expect($meta['media_download_failed'])->toBeTrue();
    expect($meta['media_download_attempts'])->toBe(3);
});
```

### Teste 6: Metadados Source = omitted_stream
```php
it('sets source=omitted_stream in metadata', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-meta2', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create(['conversation_id' => $conversation->id, 'provider_message_id' => 'meta-omitted']);

    $content = str_repeat('OMITTED_', 10000);
    Http::fake([
        'openwa:2785/api/sessions/uuid-meta2/messages/*/media' => Http::response($content, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => strlen($content),
        ]),
    ]);

    $job = new DownloadMedia($message, [
        'mimetype' => 'image/jpeg',
        'sizeBytes' => strlen($content),
    ], 'omitted_stream');

    $job->handle();

    $meta = $message->fresh()->metadata;
    expect($meta['source'])->toBe('omitted_stream');
    expect($meta['storage_path'])->toContain('topweb_chat/');
    expect($meta['sha256'])->toBe(hash('sha256', $content));
});
```

---

## 3. Interface Contracts (Estende #11)

```php
// DownloadMedia job usa mesmo handler mas source='omitted_stream'
$job = new DownloadMedia(
    message: $message,
    mediaData: [
        'mimetype' => 'video/mp4',
        'filename' => 'video.mp4',
        'sizeBytes' => 15728640,
        // NO 'data' field for omitted - fetched via HTTP
    ],
    source: 'omitted_stream', // DIFERENTE do inline
);

// MediaDownloadService::downloadOmitted(Message $message, array $mediaData): void
// - Faz GET stream para OpenWA /media endpoint
// - Salva em chunks
// - Valida headers Content-Type, Content-Length
```

### Config
```php
// config/topweb-chat.php
'media' => [
    'download_timeout' => 60,
    'download_max_retries' => 2,
    'download_backoff' => [10, 30], // seconds
    'max_size_bytes' => 52428800, // 50 MiB
],
```

---

## 5. Arquivos (Estende #11)

| Arquivo | Ação |
|---------|------|
| `app/Services/Media/MediaDownloadService.php` | Estender (método `downloadOmitted`) |
| `app/Jobs/Media/DownloadMedia.php` | Já criado (usa source) |
| `tests/Feature/Media/OmittedMediaDownloadTest.php` | **Criar** (6 testes) |

---

## 6. Mocking Strategy

```php
beforeEach(function () {
    Http::fake([
        'openwa:2785/api/sessions/*/messages/*/media' => Http::response('', 404),
    ]);
    Storage::fake('private');
});
```

---

## 7. Evidências GREEN

```bash
php artisan test tests/Feature/Media/OmittedMediaDownloadTest.php  # 6 pass
./vendor/bin/pint
```

---

## 8. Próximo Slice

Após GREEN → **#13 Media Upload Outbound** (media_token JWT, base64 ou URL assinada).