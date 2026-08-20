# TDD Specification — Slice #17: Media MinIO/S3 Integration (bucket próprio, configuração no Krayin, signed URLs)

> **Fase**: RED
> **Dependência**: #15-16 (retention + MIME validation)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Config Disk** | `config/topweb-chat.php`: `media.disk` (private|minio|s3) | P0 |
| **MinIO Disk** | Laravel filesystem: `minio` disk (league/flysystem-aws-s3-v3) | P0 |
| **Bucket Policy** | Versioning habilitado, lifecycle rules (TTL), CORS para signed URLs | P0 |
| **Path Pattern** | `{conversation_id}/{message_id}.{ext}` (mesmo pattern local) | P0 |
| **Migração** | Command `topweb-chat:media-migrate --to=minio` copia arquivos locais → MinIO, atualiza `storage_path` | P1 |
| **Signed URLs** | Para outbound `url` + download operador → `Storage::temporaryUrl()` | P1 |

---

## 2. Test Specification (RED)

### Teste 1: Config Disk Switch (private|minio|s3)
```php
// tests/Feature/Media/MinioIntegrationTest.php

it('switches storage disk via config', function () {
    config(['topweb_chat.media.disk' => 'minio']);
    expect(config('topweb_chat.media.disk'))->toBe('minio');

    config(['topweb_chat.media.disk' => 's3']);
    expect(config('topweb_chat.media.disk'))->toBe('s3');

    config(['topweb_chat.media.disk' => 'private']);
    expect(config('topweb_chat.media.disk'))->toBe('private');
});
```

### Teste 2: MinIO Disk Configurado no Laravel
```php
it('configures minio disk in Laravel filesystem', function () {
    config([
        'topweb_chat.media.disk' => 'minio',
        'filesystems.disks.minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY'),
            'secret' => env('MINIO_SECRET_KEY'),
            'endpoint' => env('MINIO_ENDPOINT'),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET', 'topweb-chat'),
            'use_path_style_endpoint' => true,
        ],
    ]);

    expect(Storage::disk('minio'))->toBeInstanceOf(\League\Flysystem\Filesystem::class);
});
```

### Teste 3: Bucket Policy (Versioning + Lifecycle + CORS)
```php
it('creates bucket with versioning, lifecycle, and CORS', function () {
    // This test would run against actual MinIO or mocked S3 client
    // For TDD, we test the command that sets up the bucket
    
    $this->artisan('topweb-chat:media-setup-minio')
        ->assertExitCode(0)
        ->expectsOutputToContain('Bucket created')
        ->expectsOutputToContain('Versioning enabled')
        ->expectsOutputToContain('Lifecycle rules applied')
        ->expectsOutputToContain('CORS configured');
});
```

### Teste 4: Path Pattern Mantido
```php
it('maintains path pattern {conversation_id}/{message_id}.{ext}', function () {
    config(['topweb_chat.media.disk' => 'minio']);
    
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-minio', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$message->id}.jpg",
            'mimetype' => 'image/jpeg',
        ],
    ]);

    // Upload to MinIO
    Storage::disk('minio')->put("topweb_chat/{$conversation->id}/{$message->id}.jpg", 'MINIO_DATA');

    // Verify path pattern
    expect(Storage::disk('minio')->exists("topweb_chat/{$conversation->id}/{$message->id}.jpg"))->toBeTrue();
});
```

### Teste 5: Migração Local → MinIO
```php
it('migrates local files to MinIO and updates metadata', function () {
    config(['topweb_chat.media.disk' => 'minio']);
    
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-mig', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Create local files
    $msg1 = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/msg1.jpg",
            'mimetype' => 'image/jpeg',
        ],
    ]);
    $msg2 = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/msg2.pdf",
            'mimetype' => 'application/pdf',
        ],
    ]);

    Storage::disk('private')->put("topweb_chat/{$conversation->id}/msg1.jpg", 'LOCAL_JPG');
    Storage::disk('private')->put("topweb_chat/{$conversation->id}/msg2.pdf", 'LOCAL_PDF');

    // Run migration
    $this->artisan('topweb-chat:media-migrate --to=minio')
        ->assertExitCode(0)
        ->expectsOutputToContain('Migrated 2 files to minio');

    // Verify files in MinIO
    expect(Storage::disk('minio')->exists("topweb_chat/{$conversation->id}/{$msg1->id}.jpg"))->toBeTrue();
    expect(Storage::disk('minio')->exists("topweb_chat/{$conversation->id}/{$msg2->id}.pdf"))->toBeTrue();

    // Verify metadata updated
    expect($msg1->fresh()->metadata['storage_path'])->toBe("topweb_chat/{$conversation->id}/{$msg1->id}.jpg");
    expect($msg2->fresh()->metadata['storage_path'])->toBe("topweb_chat/{$conversation->id}/{$msg2->id}.pdf");

    // Local files still exist (migration doesn't delete source by default)
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/msg1.jpg"))->toBeTrue();
});
```

### Teste 6: Signed URLs para Outbound + Download Operador
```php
it('generates signed URLs for outbound media and operator download', function () {
    config(['topweb_chat.media.disk' => 'minio']);
    
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-signed', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);
    $message = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$message->id}.jpg",
            'mimetype' => 'image/jpeg',
            'filename' => 'photo.jpg',
        ],
    ]);

    Storage::disk('minio')->put("topweb_chat/{$conversation->id}/{$message->id}.jpg", 'SIGNED_TEST');

    // Test signed URL generation (1 hour expiry)
    $url = Storage::disk('minio')->temporaryUrl(
        "topweb_chat/{$conversation->id}/{$message->id}.jpg",
        now()->addHour()
    );

    expect($url)->toContain('X-Amz-Signature');
    expect($url)->toContain('X-Amz-Expires=3600');
    expect($url)->toContain('topweb-chat');

    // Test download via signed URL
    $response = $this->get($url);
    $response->assertStatus(200);
});
```

### Teste 7: UI Admin para Configurar MinIO
```php
// tests/Feature/Admin/MinioSettingsTest.php

it('shows MinIO settings in admin', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.settings');

    $response = $this->actingAs($admin)->get('/admin/topweb-chat/settings/media');
    
    $response->assertStatus(200);
    $response->assertSee('Armazenamento de Mídia');
    $response->assertSee('Disco');
    $response->assertSee('MinIO');
    $response->assertSee('Endpoint');
    $response->assertSee('Bucket');
    $response->assertSee('Access Key');
    $response->assertSee('Secret Key');
    $response->assertSee('Região');
});

it('saves MinIO config and tests connection', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.settings');

    $response = $this->actingAs($admin)->put('/admin/topweb-chat/settings/media', [
        'media_disk' => 'minio',
        'minio_endpoint' => 'http://minio:9000',
        'minio_bucket' => 'topweb-chat',
        'minio_access_key' => 'test_key',
        'minio_secret_key' => 'test_secret',
        'minio_region' => 'us-east-1',
    ]);

    $response->assertStatus(200);
    expect(config('topweb_chat.media.disk'))->toBe('minio');
    expect(config('filesystems.disks.minio.endpoint'))->toBe('http://minio:9000');
});
```

---

## 3. Interface Contracts

### Config
```php
// config/topweb-chat.php
'media' => [
    'disk' => env('TOPWEB_CHAT_MEDIA_DISK', 'private'), // private|minio|s3
    'minio' => [
        'endpoint' => env('MINIO_ENDPOINT'),
        'bucket' => env('MINIO_BUCKET', 'topweb-chat'),
        'region' => env('MINIO_REGION', 'us-east-1'),
        'access_key' => env('MINIO_ACCESS_KEY'),
        'secret_key' => env('MINIO_SECRET_KEY'),
        'use_path_style' => true,
    ],
],
```

### Filesystem (config/filesystems.php)
```php
// config/filesystems.php (auto-configured by setup command)
'disks' => [
    'minio' => [
        'driver' => 's3',
        'key' => env('MINIO_ACCESS_KEY'),
        'secret' => env('MINIO_SECRET_KEY'),
        'endpoint' => env('MINIO_ENDPOINT'),
        'region' => env('MINIO_REGION', 'us-east-1'),
        'bucket' => env('MINIO_BUCKET', 'topweb-chat'),
        'use_path_style_endpoint' => true,
        'throw' => false,
    ],
],
```

### Migration Command
```php
// app/Console/Commands/TopwebChatMediaMigrate.php

class TopwebChatMediaMigrate extends Command {
    protected $signature = 'topweb-chat:media-migrate {--to= : Target disk (minio|s3)} {--delete-source : Delete local files after migration}';
    
    public function handle(MediaMigrationService $service): int {
        $target = $this->option('to');
        $delete = $this->option('delete-source');
        
        $result = $service->migrate($target, $delete);
        
        $this->info("Migrated {$result['migrated']} files to {$target}");
        if ($delete) $this->info("Deleted source files");
        return 0;
    }
}
```

### Setup Command
```php
// app/Console/Commands/TopwebChatMediaSetupMinio.php

class TopwebChatMediaSetupMinio extends Command {
    protected $signature = 'topweb-chat:media-setup-minio';
    
    public function handle(MinioSetupService $service): int {
        $service->createBucket();
        $service->enableVersioning();
        $service->configureLifecycle();
        $service->configureCORS();
        $this->info('MinIO bucket ready');
        return 0;
    }
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `config/topweb-chat.php` | Estender config media.disk + minio |
| `config/filesystems.php` | Adicionar disk minio (ou auto-configurado) |
| `app/Console/Commands/TopwebChatMediaMigrate.php` | **Criar** |
| `app/Console/Commands/TopwebChatMediaSetupMinio.php` | **Criar** |
| `app/Services/Media/MediaMigrationService.php` | **Criar** |
| `app/Services/Media/MinioSetupService.php` | **Criar** |
| `tests/Feature/Media/MinioIntegrationTest.php` | **Criar** (7 testes) |
| `tests/Feature/Admin/MinioSettingsTest.php` | **Criar** (2 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    config(['topweb_chat.media.disk' => 'minio']);
    Storage::fake('minio');
});
```

---

## 6. Evidências GREEN

```bash
php artisan topweb-chat:media-setup-minio
php artisan topweb-chat:media-migrate --to=minio
php artisan test tests/Feature/Media/MinioIntegrationTest.php  # 7 pass
php artisan test tests/Feature/Admin/MinioSettingsTest.php  # 2 pass
./vendor/bin/pint
```

---

## 7. Próximos Slices (Interativos & Domínios)

Após GREEN (media completo) → **#18 Reactions** → **#19 Message Editing** → **#20 Groups Domain** → **#21 Calls/Channels** → **#22 PIX Domain**