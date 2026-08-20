# TDD Specification — Slice #15: Media Retention Policy (TTL configurável, cleanup agendado)

> **Fase**: RED
> **Dependência**: #11-14 (media pipeline completo)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Config** | `config/topweb-chat.php`: `media.retention_ttl_days` (default 365, 0 = nunca expira), `media.cleanup_schedule` (daily|weekly|monthly) | P0 |
| **Command** | `topweb-chat:media-cleanup` deleta arquivos físicos + atualiza metadata `media_expired=true`, `media_expired_at` | P0 |
| **Scheduler** | Executa conforme `cleanup_schedule` (daily default) | P0 |
| **UI Admin** | Configurações TopwebChat > Mídia > Retenção (TTL dias, agendamento) | P1 |
| **Log** | `media.retention_cleanup` com `count`, `freed_bytes` | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Config TTL Dias (Default 365, 0 = Nunca)
```php
// tests/Feature/Media/MediaRetentionPolicyTest.php

it('uses default retention TTL of 365 days', function () {
    expect(config('topweb_chat.media.retention_ttl_days'))->toBe(365);
});

it('allows disabling retention with 0', function () {
    config(['topweb_chat.media.retention_ttl_days' => 0]);
    expect(config('topweb_chat.media.retention_ttl_days'))->toBe(0);
});
```

### Teste 2: Command Deleta Arquivos Antigos + Atualiza Metadata
```php
it('deletes old media files and updates metadata', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-ret', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // Old media (downloaded 400 days ago)
    $oldMessage = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$oldMessage->id}.jpg",
            'mimetype' => 'image/jpeg',
            'filename' => 'old.jpg',
            'sizeBytes' => 102400,
            'downloaded_at' => now()->subDays(400)->toISOString(),
        ],
    ]);
    Storage::disk('private')->put("topweb_chat/{$conversation->id}/{$oldMessage->id}.jpg", 'OLD_DATA');

    // Recent media (downloaded 10 days ago)
    $recentMessage = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$recentMessage->id}.jpg",
            'downloaded_at' => now()->subDays(10)->toISOString(),
        ],
    ]);
    Storage::disk('private')->put("topweb_chat/{$conversation->id}/{$recentMessage->id}.jpg", 'RECENT_DATA');

    // Default TTL 365 days
    config(['topweb_chat.media.retention_ttl_days' => 365]);

    $this->artisan('topweb-chat:media-cleanup');

    // Old file deleted
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/{$oldMessage->id}.jpg"))->toBeFalse();
    $oldMeta = $oldMessage->fresh()->metadata;
    expect($oldMeta['media_expired'])->toBeTrue();
    expect($oldMeta['media_expired_at'])->not->toBeNull();

    // Recent file kept
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/{$recentMessage->id}.jpg"))->toBeTrue();
    expect($recentMessage->fresh()->metadata['media_expired'])->toBeFalse();
});
```

### Teste 3: TTL = 0 Nunca Expira
```php
it('never expires media when TTL = 0', function () {
    config(['topweb_chat.media.retention_ttl_days' => 0]);

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-never', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    $oldMessage = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'metadata' => [
            'storage_path' => "topweb_chat/{$conversation->id}/{$oldMessage->id}.jpg",
            'downloaded_at' => now()->subDays(1000)->toISOString(),
        ],
    ]);
    Storage::disk('private')->put("topweb_chat/{$conversation->id}/{$oldMessage->id}.jpg", 'DATA');

    $this->artisan('topweb-chat:media-cleanup');

    // File should NOT be deleted
    expect(Storage::disk('private')->exists("topweb_chat/{$conversation->id}/{$oldMessage->id}.jpg"))->toBeTrue();
    expect($oldMessage->fresh()->metadata['media_expired'])->toBeFalse();
});
```

### Teste 4: Scheduler Conforme Configuração
```php
// tests/Feature/Scheduler/MediaCleanupSchedulerTest.php

it('registers media cleanup job per configured schedule', function () {
    config(['topweb_chat.media.cleanup_schedule' => 'daily']);
    
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $this->artisan('schedule:run')->assertExitCode(0);
    
    $commands = $schedule->commands();
    $cleanup = collect($commands)->firstWhere('command', 'topweb-chat:media-cleanup');
    
    expect($cleanup)->not->toBeNull();
    expect($cleanup->getExpression())->toBe('0 0 * * *'); // daily at midnight
});

it('supports weekly schedule', function () {
    config(['topweb_chat.media.cleanup_schedule' => 'weekly']);
    
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $this->artisan('schedule:run')->assertExitCode(0);
    
    $cleanup = collect($schedule->commands())->firstWhere('command', 'topweb-chat:media-cleanup');
    expect($cleanup->getExpression())->toBe('0 0 * * 0'); // weekly Sunday
});

it('supports monthly schedule', function () {
    config(['topweb_chat.media.cleanup_schedule' => 'monthly']);
    
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $this->artisan('schedule:run')->assertExitCode(0);
    
    $cleanup = collect($schedule->commands())->firstWhere('command', 'topweb-chat:media-cleanup');
    expect($cleanup->getExpression())->toBe('0 0 1 * *'); // monthly 1st
});
```

### Teste 5: Log Retention Cleanup com Contagem + Bytes Liberados
```php
it('logs retention cleanup with count and freed bytes', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-log', 'status' => 'ready']);
    $conversation = Conversation::factory()->create(['instance_id' => $instance->id]);

    // 3 old files: 100KB, 200KB, 300KB = 600KB total
    for ($i = 0; $i < 3; $i++) {
        $msg = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'metadata' => [
                'storage_path' => "topweb_chat/{$conversation->id}/msg_{$i}.jpg",
                'sizeBytes' => ($i + 1) * 100 * 1024,
                'downloaded_at' => now()->subDays(400)->toISOString(),
            ],
        ]);
        Storage::disk('private')->put("topweb_chat/{$conversation->id}/msg_{$i}.jpg", str_repeat('X', ($i + 1) * 1024 * 1024));
    }

    config(['topweb_chat.media.retention_ttl_days' => 365]);

    $cleanupLogs = [];
    Log::shouldReceive('channel')->with('media')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($m, $c) use (&$cleanupLogs) {
        if (isset($c['event']) && $c['event'] === 'media.retention_cleanup') $cleanupLogs[] = $c;
    });

    $this->artisan('topweb-chat:media-cleanup');

    expect($cleanupLogs)->toHaveCount(1);
    expect($cleanupLogs[0]['context'])->toContainKeys([
        'event' => 'media.retention_cleanup',
        'deleted_count' => 3,
        'freed_bytes' => 600 * 1024, // 600KB
    ]);
});
```

### Teste 6: UI Admin para Configurar TTL + Agendamento
```php
// tests/Feature/Admin/MediaSettingsTest.php

it('shows media retention settings in admin', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.settings');

    $response = $this->actingAs($admin)->get('/admin/topweb-chat/settings/media');
    
    $response->assertStatus(200);
    $response->assertSee('Retenção de Mídia');
    $response->assertSee('TTL (dias)');
    $response->assertSee('Agendamento');
    $response->assertSee('365'); // default
});

it('updates retention settings via admin', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.settings');

    $response = $this->actingAs($admin)->put('/admin/topweb-chat/settings/media', [
        'retention_ttl_days' => 180,
        'cleanup_schedule' => 'weekly',
    ]);

    $response->assertStatus(200);
    expect(config('topweb_chat.media.retention_ttl_days'))->toBe(180);
    expect(config('topweb_chat.media.cleanup_schedule'))->toBe('weekly');
});
```

---

## 3. Interface Contracts

### Command
```php
// app/Console/Commands/TopwebChatMediaCleanup.php

class TopwebChatMediaCleanup extends Command {
    protected $signature = 'topweb-chat:media-cleanup';
    protected $description = 'Remove expired media files per retention policy';

    public function handle(MediaRetentionService $service): int {
        $result = $service->cleanup();
        $this->info("Deleted {$result['deleted_count']} files, freed {$result['freed_bytes']} bytes");
        return 0;
    }
}
```

### Service
```php
// app/Services/Media/MediaRetentionService.php

class MediaRetentionService {
    public function __construct(
        protected MessageRepository $messages,
        protected MediaStorage $storage,
        protected ReconciliationLogger $logger
    ) {}

    /** @return array{deleted_count: int, freed_bytes: int} */
    public function cleanup(): array {
        $ttlDays = config('topweb_chat.media.retention_ttl_days', 365);
        if ($ttlDays === 0) return ['deleted_count' => 0, 'freed_bytes' => 0];

        $cutoff = now()->subDays($ttlDays);
        
        $expiredMessages = Message::query()
            ->whereNotNull('metadata->downloaded_at')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.downloaded_at')) < ?", [$cutoff->toISOString()])
            ->where('metadata->media_expired', '!=', true)
            ->get();

        $deleted = 0;
        $freed = 0;

        foreach ($expiredMessages as $message) {
            $meta = $message->metadata;
            if (Storage::disk('private')->exists($meta['storage_path'])) {
                $size = Storage::disk('private')->size($meta['storage_path']);
                Storage::disk('private')->delete($meta['storage_path']);
                $freed += $size;
            }
            
            $message->metadata = array_merge($meta, [
                'media_expired' => true,
                'media_expired_at' => now()->toISOString(),
            ]);
            $message->save();
            $deleted++;
        }

        Log::channel('media')->info('Retention cleanup', [
            'event' => 'media.retention_cleanup',
            'deleted_count' => $deleted,
            'freed_bytes' => $freed,
        ]);

        return ['deleted_count' => $deleted, 'freed_bytes' => $freed];
    }
}
```

### Scheduler
```php
// routes/console.php

$schedule->command('topweb-chat:media-cleanup')
    ->when(fn () => config('topweb_chat.media.retention_ttl_days', 365) > 0)
    ->{$schedule = config('topweb_chat.media.cleanup_schedule', 'daily')};
```

### Config
```php
// config/topweb-chat.php
'media' => [
    'retention_ttl_days' => env('TOPWEB_CHAT_MEDIA_RETENTION_DAYS', 365),
    'cleanup_schedule' => env('TOPWEB_CHAT_MEDIA_CLEANUP_SCHEDULE', 'daily'), // daily|weekly|monthly
],
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `app/Console/Commands/TopwebChatMediaCleanup.php` | **Criar** |
| `app/Services/Media/MediaRetentionService.php` | **Criar** |
| `routes/console.php` | Registrar scheduler dinâmico |
| `config/topweb-chat.php` | Adicionar config retention |
| `tests/Feature/Media/MediaRetentionPolicyTest.php` | **Criar** (3 testes) |
| `tests/Feature/Scheduler/MediaCleanupSchedulerTest.php` | **Criar** (3 testes) |
| `tests/Feature/Admin/MediaSettingsTest.php` | **Criar** (2 testes) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Storage::fake('private');
    Config::set('topweb_chat.media.retention_ttl_days', 365);
    Config::set('topweb_chat.media.cleanup_schedule', 'daily');
});
```

---

## 6. Evidências GREEN

```bash
php artisan topweb-chat:media-cleanup
php artisan test tests/Feature/Media/MediaRetentionPolicyTest.php  # 3 pass
php artisan test tests/Feature/Scheduler/MediaCleanupSchedulerTest.php  # 3 pass
php artisan test tests/Feature/Admin/MediaSettingsTest.php  # 2 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#16 Media MIME Validation** (permitidos/bloqueados + stickers).