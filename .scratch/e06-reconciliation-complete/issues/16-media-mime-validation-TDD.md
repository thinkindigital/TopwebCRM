# TDD Specification — Slice #16: Media MIME Validation (permitidos/bloqueados + stickers)

> **Fase**: RED
> **Dependência**: #11-15 (media pipeline + retention)

---

## 1. Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| **Lista Permitida** | Config `topweb_chat.media.allowed_mimes` com MIME types completos | P0 |
| **Validação Dual** | Server-side: `finfo_file` (magic bytes) + extensão → **ambos** devem estar na permitida | P0 |
| **Lista Bloqueada** | Sempre rejeita: `application/x-executable`, `application/x-msdownload`, `application/x-sh`, `application/x-php`, qualquer `application/*` não listada | P0 |
| **Stickers** | Detecta `image/webp` + tamanho típico < 100KB → flag `is_sticker=true` no metadata | P0 |
| **Client-side** | `accept` attribute + JS validation | P1 |

---

## 2. Test Specification (RED)

### Teste 1: Config Permitidos com Lista Completa
```php
// tests/Feature/Media/MimeValidationTest.php

it('has complete allowed MIME types config', function () {
    $allowed = config('topweb_chat.media.allowed_mimes');
    
    expect($allowed)->toContain('image/jpeg', 'image/png', 'image/webp', 'image/gif');
    expect($allowed)->toContain('video/mp4', 'video/3gp', 'video/quicktime');
    expect($allowed)->toContain('audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac');
    expect($allowed)->toContain('application/pdf');
    expect($allowed)->toContain('application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($allowed)->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($allowed)->toContain('application/vnd.openxmlformats-officedocument.presentationml.presentation');
    expect($allowed)->toContain('application/msword');
    expect($allowed)->toContain('application/vnd.ms-excel');
    expect($allowed)->toContain('application/vnd.ms-powerpoint');
});
```

### Teste 2: Validação Dual (Magic Bytes + Extensão)
```php
it('validates both magic bytes AND extension against allowed list', function () {
    $service = app(MediaValidator::class);
    
    // Valid: JPEG magic bytes + .jpg extension
    $result = $service->validate('image/jpeg', 'photo.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF");
    expect($result)->toBeTrue();

    // Invalid: PHP magic bytes with .jpg extension
    $result = $service->validate('image/jpeg', 'shell.jpg', "<?php echo 'hack'; ?>");
    expect($result)->toBeFalse();

    // Invalid: executable magic bytes with .pdf extension
    $result = $service->validate('application/pdf', 'doc.pdf', "\x7FELF");
    expect($result)->toBeFalse();
});
```

### Teste 3: Rejeita application/* Não Listada
```php
it('rejects any application/* not explicitly allowed', function () {
    $service = app(MediaValidator::class);
    
    $blocked = [
        'application/x-executable',
        'application/x-msdownload', 
        'application/x-sh',
        'application/x-php',
        'application/zip',
        'application/octet-stream',
        'application/custom-format',
    ];

    foreach ($blocked as $mime) {
        $result = $service->validate($mime, 'file.bin', 'data');
        expect($result)->toBeFalse();
    }
});
```

### Teste 4: Permite application/* Explícitas
```php
it('allows explicitly allowed application/* types', function () {
    $service = app(MediaValidator::class);
    
    $allowed = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
    ];

    foreach ($allowed as $mime) {
        $content = $this->getValidContentForMime($mime);
        $result = $service->validate($mime, 'file', $content);
        expect($result)->toBeTrue();
    }
});
```

### Teste 5: Sticker Detection (image/webp < 100KB)
```php
it('detects stickers: image/webp < 100KB', function () {
    $service = app(MediaValidator::class);
    
    $smallWebp = str_repeat('WEBP', 5000); // ~20KB
    $result = $service->validate('image/webp', 'sticker.webp', $smallWebp);
    expect($result)->toBeTrue();
    
    $largeWebp = str_repeat('WEBP', 50000); // ~200KB
    $result = $service->validate('image/webp', 'large.webp', $largeWebp);
    expect($result)->toBeTrue();
});
```

### Teste 6: Client-side Accept Attribute
```php
// tests/Feature/Media/MimeValidationClientTest.php

it('renders correct accept attribute in file input', function () {
    $user = User::factory()->create();
    $conversation = Conversation::factory()->create();
    
    $response = $this->actingAs($user)->get("/admin/topweb-chat/conversations/{$conversation->id}/composer");
    
    $response->assertStatus(200);
    $acceptAttr = config('topweb_chat.media.accept_attribute');
    expect($acceptAttr)->toContain('image/jpeg,image/png,image/webp,image/gif');
    expect($acceptAttr)->toContain('video/mp4,video/3gp,video/quicktime');
    expect($acceptAttr)->toContain('audio/ogg,audio/mpeg,audio/mp4,audio/aac');
    expect($acceptAttr)->toContain('application/pdf');
    expect($acceptAttr)->toContain('.doc,.docx,.xls,.xlsx,.ppt,.pptx');
});
```

---

## 3. Interface Contracts

### Config
```php
// config/topweb-chat.php
'media' => [
    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/3gp', 'video/quicktime',
        'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac',
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/msword',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
    ],
    'blocked_mimes' => [
        'application/x-executable',
        'application/x-msdownload',
        'application/x-sh',
        'application/x-php',
    ],
    'sticker_max_kb' => 100,
    'accept_attribute' => 'image/jpeg,image/png,image/webp,image/gif,video/mp4,video/3gp,video/quicktime,audio/ogg,audio/mpeg,audio/mp4,audio/aac,application/pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx',
],
```

### Validator Service
```php
// app/Services/Media/MediaValidator.php

class MediaValidator {
    public function __construct(
        protected array $allowedMimes = [],
        protected array $blockedMimes = [],
        protected int $stickerMaxKb = 100
    ) {}

    public function validate(string $declaredMime, string $filename, string $content): bool {
        if (in_array($declaredMime, $this->blockedMimes)) return false;
        if (!in_array($declaredMime, $this->allowedMimes)) return false;
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = finfo_buffer($finfo, $content);
        finfo_close($finfo);
        
        if ($detected !== $declaredMime) return false;
        if (!$this->extensionMatchesMime($ext, $declaredMime)) return false;
        
        return true;
    }

    protected function extensionMatchesMime(string $ext, string $mime): bool {
        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
            'mp4' => 'video/mp4', '3gp' => 'video/3gp', 'mov' => 'video/quicktime',
            'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
        return isset($map[$ext]) && $map[$ext] === $mime;
    }
}
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Ação |
|---------|------|
| `config/topweb-chat.php` | Estender config media |
| `app/Services/Media/MediaValidator.php` | **Criar** |
| `resources/views/components/media/upload-field.blade.php` | **Criar** |
| `tests/Feature/Media/MimeValidationTest.php` | **Criar** (5 testes) |
| `tests/Feature/Media/MimeValidationClientTest.php` | **Criar** (1 teste) |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    $this->validator = app(MediaValidator::class);
});
```

---

## 6. Evidências GREEN

```bash
php artisan test tests/Feature/Media/MimeValidationTest.php  # 5 pass
php artisan test tests/Feature/Media/MimeValidationClientTest.php  # 1 pass
./vendor/bin/pint
```

---

## 7. Próximo Slice

Após GREEN → **#17 Media MinIO Integration** (bucket próprio, configuração no Krayin, signed URLs).