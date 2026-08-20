# TDD Specification — Slices #06-10: Quarentena Identidade

> **Fase**: RED (especificação de testes antes da implementação)
> **Dependência**: #01-05 (reconciliação base + webhook handler)

---

## Visão Geral dos 5 Slices

| Slice | Arquivo | Foco |
|-------|---------|------|
| #06 | `06-identity-quarantine-zero-matches-TDD.md` | Zero matches → quarentena + botão "Checar WhatsApp" |
| #07 | `07-identity-quarantine-ambiguous-TDD.md` | Múltiplos matches → seleção manual |
| #08 | `08-identity-quarantine-lid-unresolved-TDD.md` | @lid sem phone → quarentena + resolução on-demand |
| #09 | `09-quarantine-resolution-ui-TDD.md` | UI fila "Quarentena Identidade" + resolução |
| #10 | `10-whatsapp-check-button-TDD.md` | Botão "Checar WhatsApp" no Lead/Pessoa |

---

## Entidades e Migrações Comuns

```php
// Migration: add quarantine fields to conversations
Schema::table('topweb_chat_conversations', function (Blueprint $table) {
    $table->string('status')->default('active')->change(); // active|quarantined
    $table->string('quarantine_reason')->nullable()->after('status'); // no_match|ambiguous|lid_unresolved
    $table->json('candidate_person_ids')->nullable()->after('quarantine_reason');
    $table->boolean('needs_lid_resolution')->default(false)->after('candidate_person_ids');
    $table->timestamp('quarantined_at')->nullable()->after('needs_lid_resolution');
    $table->timestamp('resolved_at')->nullable()->after('quarantined_at');
    $table->foreignId('resolved_by')->nullable()->constrained('users')->after('resolved_at');
});
```

### Permissões ACL
```php
// permissions
'topweb_chat.quarantine_view'      // ver fila quarentena
'topweb_chat.quarantine_resolve'   // resolver quarentena
'topweb_chat.whatsapp_check'       // botão "Checar WhatsApp"
```

---

## #06: Zero Matches — TDD Spec

### Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| Webhook `message.received` → `contacts/check` retorna `exists=false` OU número não no CRM | P0 |
| Cria `Conversation` com `status='quarantined'`, `quarantine_reason='no_match'` | P0 |
| Mensagens inbound persistem mas **ocultas** do operador comum | P0 |
| Botão "Enviar WhatsApp" **indisponível** no Lead/Pessoa (disabled + tooltip) | P0 |
| Botão "Checar WhatsApp" **habilitado** → executa `contacts/check` on-demand | P0 |
| Re-check bem-sucedido → transição `quarantined` → `active` + vinculação automática | P0 |

### Testes RED

```php
// tests/Feature/Quarantine/ZeroMatchesQuarantineTest.php

it('creates quarantined conversation when contacts/check returns exists=false', function () {
    $instance = Instance::factory()->enabled()->create([
        'session_uuid' => 'uuid-zero',
        'status' => 'ready',
        'engine_loaded' => true,
    ]);

    // No matching Person/Lead for this phone
    Http::fake([
        'openwa:2785/api/sessions/uuid-zero/contacts/check/5511999999999' => Http::response([
            'exists' => false,
        ], 200),
    ]);

    // Simulate webhook message.received
    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-zero',
        'idempotencyKey' => 'msg_uuid-zero_true_5511999999999@c.us_ABC123',
        'deliveryId' => 'dlv_123',
        'data' => [
            'id' => 'true_5511999999999@c.us_ABC123',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'body' => 'Olá!',
            'type' => 'text',
            'timestamp' => 1724150000,
            'isGroup' => false,
            'kind' => 'individual',
            'hasMedia' => false,
        ],
    ];

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-zero', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
        'X-OpenWA-Event' => 'message.received',
        'X-OpenWA-Idempotency-Key' => 'msg_uuid-zero_true_5511999999999@c.us_ABC123',
        'X-OpenWA-Delivery-Id' => 'dlv_123',
    ]);

    $response->assertStatus(200);

    // Assert: Conversation created with quarantined status
    $conversation = Conversation::where('remote_jid_key', hash('sha256', '5511999999999@c.us'))->first();
    expect($conversation)->not->toBeNull();
    expect($conversation->status)->toBe('quarantined');
    expect($conversation->quarantine_reason)->toBe('no_match');
    expect($conversation->person_id)->toBeNull();
    expect($conversation->lead_id)->toBeNull();

    // Assert: Message persisted
    $message = Message::where('provider_message_id', 'true_5511999999999@c.us_ABC123')->first();
    expect($message)->not->toBeNull();
    expect($message->direction)->toBe('in');
});
```

```php
it('hides quarantined messages from regular operators', function () {
    $user = User::factory()->create(); // regular operator
    $user->givePermissionTo('topweb_chat.access');

    $conversation = Conversation::factory()->create([
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    Message::factory()->count(3)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
    ]);

    $response = $this->actingAs($user)->getJson("/api/topweb-chat/conversations/{$conversation->id}/timeline");
    
    // Regular operator should NOT see quarantined conversations
    $response->assertStatus(403); // or empty results
});
```

```php
it('shows "Checar WhatsApp" button and hides "Enviar WhatsApp" for quarantined lead', function () {
    $lead = Lead::factory()->create(['phone' => '5511999999999']);
    
    // Simulate: conversation exists but quarantined no_match
    Conversation::factory()->create([
        'lead_id' => $lead->id,
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.access');

    $response = $this->actingAs($user)->get("/admin/leads/{$lead->id}");
    
    $response->assertSee('Checar WhatsApp'); // button visible
    $response->assertDontSee('Enviar WhatsApp'); // or button disabled
});
```

```php
it('re-check via "Checar WhatsApp" transitions to active when contacts/check returns true', function () {
    $lead = Lead::factory()->create(['phone' => '5511999999999']);
    
    $conversation = Conversation::factory()->create([
        'lead_id' => $lead->id,
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-check/contacts/check/5511999999999' => Http::response([
            'exists' => true,
            'whatsappId' => '5511999999999@c.us',
        ], 200),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.whatsapp_check');

    $response = $this->actingAs($user)->postJson("/api/topweb-chat/leads/{$lead->id}/check-whatsapp", [
        'session_id' => 'uuid-check',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true, 'whatsapp_exists' => true]);

    $conv = $conversation->fresh();
    expect($conv->status)->toBe('active');
    expect($conv->quarantine_reason)->toBeNull();
    expect($conv->person_id)->not->toBeNull(); // auto-linked if Person exists
});
```

---

## #07: Ambiguous (Múltiplos Matches) — TDD Spec

### Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| `remote_jid_key` encontra 2+ Pessoas → `quarantine_reason='ambiguous'` + `candidate_person_ids` JSON | P0 |
| Fila "Quarentena" mostra candidatos (nome, telefone, email, lead) | P0 |
| Operador seleciona **um** → vincula + transição `active` | P0 |
| Log `quarantine.resolved` com `selected_person_id`, `resolved_by` | P0 |

### Testes RED

```php
// tests/Feature/Quarantine/AmbiguousQuarantineTest.php

it('creates ambiguous quarantine when multiple persons share same phone', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-ambig', 'status' => 'ready']);
    
    // Create 2 Persons with same phone
    $person1 = Person::factory()->create(['phone' => [['number' => '5511999999999', 'type' => 'mobile']]]);
    $person2 = Person::factory()->create(['phone' => [['number' => '5511999999999', 'type' => 'mobile']]]);
    
    $lead1 = Lead::factory()->create(['person_id' => $person1->id]);
    $lead2 = Lead::factory()->create(['person_id' => $person2->id]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-ambig/contacts/check/5511999999999' => Http::response([
            'exists' => true,
            'whatsappId' => '5511999999999@c.us',
        ], 200),
    ]);

    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-ambig',
        'idempotencyKey' => 'msg_uuid-ambig_true_5511999999999@c.us_AMB123',
        'deliveryId' => 'dlv_amb',
        'data' => [
            'id' => 'true_5511999999999@c.us_AMB123',
            'from' => '5511999999999@c.us',
            'to' => '5511888888888@c.us',
            'body' => 'Teste ambiguo',
            'type' => 'text',
            'timestamp' => 1724150000,
            'isGroup' => false,
            'kind' => 'individual',
        ],
    ];

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-ambig', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
    ])->assertStatus(200);

    $conversation = Conversation::where('remote_jid_key', hash('sha256', '5511999999999@c.us'))->first();
    expect($conversation->status)->toBe('quarantined');
    expect($conversation->quarantine_reason)->toBe('ambiguous');
    expect($conversation->candidate_person_ids)->toHaveCount(2);
    expect($conversation->candidate_person_ids)->toContain($person1->id, $person2->id);
});
```

```php
it('resolves ambiguous quarantine when operator selects one person', function () {
    $person1 = Person::factory()->create(['phone' => [['number' => '5511999999999']]]);
    $person2 = Person::factory()->create(['phone' => [['number' => '5511999999999']]]);
    
    $conversation = Conversation::factory()->create([
        'status' => 'quarantined',
        'quarantine_reason' => 'ambiguous',
        'candidate_person_ids' => [$person1->id, $person2->id],
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.quarantine_resolve');

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/resolve-quarantine", [
        'selected_person_id' => $person1->id,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true]);

    $conv = $conversation->fresh();
    expect($conv->status)->toBe('active');
    expect($conv->quarantine_reason)->toBeNull();
    expect($conv->candidate_person_ids)->toBeNull();
    expect($conv->person_id)->toBe($person1->id);

    // Log audit
    $logs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($m, $c) use (&$logs) {
        if (isset($c['event']) && $c['event'] === 'quarantine.resolved') $logs[] = $c;
    });
    
    expect($logs)->toHaveCount(1);
    expect($logs[0]['selected_person_id'])->toBe($person1->id);
    expect($logs[0]['resolved_by'])->toBe($operator->id);
});
```

---

## #08: LID Unresolved — TDD Spec

### Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| Webhook `from` = `@lid` (ex: `12345678901234@lid`) | P0 |
| Tenta `GET /contacts/:contactId/phone` on-demand | P0 |
| Se retorna `null` → `quarantine_reason='lid_unresolved'`, `needs_lid_resolution=true` | P0 |
| Se `RESOLVE_LID_TO_PHONE=true` no OpenWA → `senderPhone` vem no webhook → evita quarentena | P0 |
| Badge "LID não resolvido" na fila + opção vinculação manual | P0 |

### Testes RED

```php
// tests/Feature/Quarantine/LidUnresolvedQuarantineTest.php

it('quarantines lid when GET /contacts/:contactId/phone returns null', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-lid', 'status' => 'ready']);

    Http::fake([
        // contacts/check succeeds (LID exists on WhatsApp)
        'openwa:2785/api/sessions/uuid-lid/contacts/check/12345678901234' => Http::response([
            'exists' => true,
            'whatsappId' => '12345678901234@lid',
        ], 200),
        // But phone resolution fails
        'openwa:2785/api/sessions/uuid-lid/contacts/12345678901234@lid/phone' => Http::response([
            'phone' => null,
        ], 200),
    ]);

    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-lid',
        'idempotencyKey' => 'msg_uuid-lid_true_12345678901234@lid_LID123',
        'deliveryId' => 'dlv_lid',
        'data' => [
            'id' => 'true_12345678901234@lid_LID123',
            'from' => '12345678901234@lid',
            'to' => '5511888888888@c.us',
            'body' => 'Msg de LID',
            'type' => 'text',
            'timestamp' => 1724150000,
            'isGroup' => false,
            'kind' => 'individual',
        ],
    ];

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-lid', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
    ])->assertStatus(200);

    $conversation = Conversation::where('remote_jid_key', hash('sha256', '12345678901234@lid'))->first();
    expect($conversation->status)->toBe('quarantined');
    expect($conversation->quarantine_reason)->toBe('lid_unresolved');
    expect($conversation->needs_lid_resolution)->toBeTrue();
});
```

```php
it('avoids quarantine when RESOLVE_LID_TO_PHONE=true provides senderPhone', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-lid-resolved', 'status' => 'ready']);

    // Mock OpenWA with RESOLVE_LID_TO_PHONE=true behavior
    Http::fake([
        'openwa:2785/api/sessions/uuid-lid-resolved/contacts/check/12345678901234' => Http::response([
            'exists' => true,
            'whatsappId' => '12345678901234@lid',
        ], 200),
    ]);

    $payload = [
        'event' => 'message.received',
        'sessionId' => 'uuid-lid-resolved',
        'idempotencyKey' => 'msg_uuid-lid-resolved_true_12345678901234@lid_LID456',
        'deliveryId' => 'dlv_lid2',
        'data' => [
            'id' => 'true_12345678901234@lid_LID456',
            'from' => '12345678901234@lid',
            'to' => '5511888888888@c.us',
            'body' => 'Msg com phone resolvido',
            'type' => 'text',
            'timestamp' => 1724150000,
            'isGroup' => false,
            'kind' => 'individual',
            'senderPhone' => '5511999999999', // RESOLVE_LID_TO_PHONE=true
        ],
    ];

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-lid-resolved', $payload, [
        'X-OpenWA-Signature' => 'sha256=' . hash_hmac('sha256', json_encode($payload), 'secret'),
    ])->assertStatus(200);

    $conversation = Conversation::where('remote_jid_key', hash('sha256', '12345678901234@lid'))->first();
    expect($conversation->status)->toBe('active'); // NOT quarantined
    expect($conversation->person_id)->not->toBeNull(); // linked via phone
});
```

---

## #09: Quarantine Resolution UI — TDD Spec

### Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| Menu TopwebChat > Quarentena (DataGrid) acessível com `topweb_chat.quarantine_view` | P0 |
| Lista: `remote_jid` (mascarado), `quarantine_reason`, `candidate_person_ids`, `created_at`, `messages_count` | P0 |
| Filtros: `quarantine_reason`, data, instance | P0 |
| Detalhe: mostra mensagens inbound (readonly) + candidatos (se ambiguous) | P0 |
| Ações: "Checar WhatsApp" (no_match), "Vincular manualmente" (lid_unresolved), "Confirmar" (ambiguous) | P0 |
| Resolução → `status='active'`, mensagens tornam-se visíveis | P0 |

### Testes RED

```php
// tests/Feature/Admin/QuarantineResolutionUITest.php

it('shows quarantine list for users with permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.quarantine_view');

    $convs = Conversation::factory()->count(3)->create([
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    $response = $this->actingAs($user)->get('/admin/topweb-chat/quarantine');
    
    $response->assertStatus(200);
    $response->assertSee('Quarentena Identidade');
    foreach ($convs as $c) {
        $response->assertSee($c->quarantine_reason);
    }
});
```

```php
it('shows candidates for ambiguous quarantine in detail view', function () {
    $person1 = Person::factory()->create(['name' => 'João Silva', 'phone' => [['number' => '5511999999999']]]);
    $person2 = Person::factory()->create(['name' => 'Maria Santos', 'phone' => [['number' => '5511999999999']]]);

    $conversation = Conversation::factory()->create([
        'status' => 'quarantined',
        'quarantine_reason' => 'ambiguous',
        'candidate_person_ids' => [$person1->id, $person2->id],
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.quarantine_resolve');

    $response = $this->actingAs($user)->get("/admin/topweb-chat/quarantine/{$conversation->id}");
    
    $response->assertStatus(200);
    $response->assertSee('João Silva');
    $response->assertSee('Maria Santos');
    $response->assertSee('Confirmar vinculação');
});
```

```php
it('resolves quarantine and makes messages visible', function () {
    $person = Person::factory()->create();
    $lead = Lead::factory()->create(['person_id' => $person->id]);

    $conversation = Conversation::factory()->create([
        'status' => 'quarantined',
        'quarantine_reason' => 'ambiguous',
        'candidate_person_ids' => [$person->id],
    ]);

    Message::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'direction' => 'in',
        'status' => 'delivered',
    ]);

    $operator = User::factory()->create();
    $operator->givePermissionTo('topweb_chat.quarantine_resolve');

    $response = $this->actingAs($operator)->postJson("/api/topweb-chat/conversations/{$conversation->id}/resolve-quarantine", [
        'selected_person_id' => $person->id,
    ]);

    $response->assertStatus(200);
    
    $conv = $conversation->fresh();
    expect($conv->status)->toBe('active');
    expect($conv->person_id)->toBe($person->id);
    expect($conv->lead_id)->toBe($lead->id);

    // Messages now visible
    $messages = Message::where('conversation_id', $conversation->id)->get();
    expect($messages->count())->toBe(2);
});
```

---

## #10: Botão "Checar WhatsApp" — TDD Spec

### Comportamentos Críticos

| Comportamento | Prioridade |
|---------------|------------|
| Botão aparece no Lead/Pessoa quando `Conversation` em quarentena `no_match` | P0 |
| Executa `GET /api/sessions/:sessionId/contacts/check/:number` | P0 |
| Loading state + feedback visual (success/warning/error) | P0 |
| Sucesso → habilita "Enviar WhatsApp" + vinculação automática se match | P0 |
| Falha → mantém quarentena + mensagem clara | P0 |
| Log `whatsapp.check` com `lead_id`/`person_id`, `number`, `result` | P0 |

### Testes RED

```php
// tests/Feature/Quarantine/WhatsAppCheckButtonTest.php

it('shows check button for quarantined no_match lead', function () {
    $lead = Lead::factory()->create(['phone' => '5511999999999']);
    
    Conversation::factory()->create([
        'lead_id' => $lead->id,
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.whatsapp_check');

    $response = $this->actingAs($user)->get("/admin/leads/{$lead->id}");
    
    $response->assertSee('Checar WhatsApp');
    $response->assertDontSee('Enviar WhatsApp'); // disabled
});
```

```php
it('executes contacts/check and enables send button on success', function () {
    $lead = Lead::factory()->create(['phone' => '5511999999999']);
    
    $conversation = Conversation::factory()->create([
        'lead_id' => $lead->id,
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-check/contacts/check/5511999999999' => Http::response([
            'exists' => true,
            'whatsappId' => '5511999999999@c.us',
        ], 200),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.whatsapp_check');

    $response = $this->actingAs($user)->postJson("/api/topweb-chat/leads/{$lead->id}/check-whatsapp", [
        'session_id' => 'uuid-check',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => true, 'whatsapp_exists' => true]);

    $conv = $conversation->fresh();
    expect($conv->status)->toBe('active');
    expect($conv->quarantine_reason)->toBeNull();
});
```

```php
it('shows warning and keeps quarantine when contacts/check returns false', function () {
    $lead = Lead::factory()->create(['phone' => '5511999999999']);
    Conversation::factory()->create([
        'lead_id' => $lead->id,
        'status' => 'quarantined',
        'quarantine_reason' => 'no_match',
    ]);

    Http::fake([
        'openwa:2785/api/sessions/uuid-check/contacts/check/5511999999999' => Http::response([
            'exists' => false,
        ], 200),
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.whatsapp_check');

    $response = $this->actingAs($user)->postJson("/api/topweb-chat/leads/{$lead->id}/check-whatsapp", [
        'session_id' => 'uuid-check',
    ]);

    $response->assertStatus(200);
    $response->assertJson(['success' => false, 'whatsapp_exists' => false]);

    // Still quarantined
    $conv = $lead->conversation->fresh();
    expect($conv->status)->toBe('quarantined');
});
```

```php
it('logs whatsapp.check with result', function () {
    $lead = Lead::factory()->create(['phone' => '5511999999999']);
    Conversation::factory()->create(['lead_id' => $lead->id, 'status' => 'quarantined', 'quarantine_reason' => 'no_match']);

    Http::fake([
        'openwa:2785/api/sessions/uuid-log/contacts/check/5511999999999' => Http::response(['exists' => true], 200),
    ]);

    $auditLogs = [];
    Log::shouldReceive('channel')->with('reconciliation')->andReturnSelf();
    Log::shouldReceive('info')->andReturnUsing(function ($m, $c) use (&$auditLogs) {
        if (isset($c['event']) && $c['event'] === 'whatsapp.check') $auditLogs[] = $c;
    });

    $user = User::factory()->create();
    $user->givePermissionTo('topweb_chat.whatsapp_check');

    $this->actingAs($user)->postJson("/api/topweb-chat/leads/{$lead->id}/check-whatsapp", [
        'session_id' => 'uuid-log',
    ]);

    expect($auditLogs)->toHaveCount(1);
    expect($auditLogs[0]['event'])->toBe('whatsapp.check');
    expect($auditLogs[0]['lead_id'])->toBe($lead->id);
    expect($auditLogs[0]['number'])->toBe('5511999999999');
    expect($auditLogs[0]['result'])->toBe('exists');
});
```

---

## Arquivos Comuns a Criar/Modificar (Todos Slices)

| Arquivo | Ação |
|---------|------|
| `database/migrations/xxxx_add_quarantine_fields_to_conversations.php` | **Criar** |
| `app/Services/TopwebChat/QuarantineService.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/WebhookController.php` | Estender (lógica quarentena) |
| `app/Http/Controllers/Api/TopwebChat/QuarantineController.php` | **Criar** (resolve, check-whatsapp) |
| `app/Http/Controllers/Admin/TopwebChat/QuarantineController.php` | **Criar** (UI admin) |
| `packages/Webkul/TopwebChat/src/Resources/views/admin/quarantine/` | **Criar** (index, show, resolve) |
| `routes/api.php` | Registrar rotas quarentena + check-whatsapp |
| `routes/web.php` | Registrar rotas admin quarentena |
| `config/topweb-chat.php` | Adicionar configurações quarentena |
| `tests/Feature/Quarantine/ZeroMatchesQuarantineTest.php` | **Criar** |
| `tests/Feature/Quarantine/AmbiguousQuarantineTest.php` | **Criar** |
| `tests/Feature/Quarantine/LidUnresolvedQuarantineTest.php` | **Criar** |
| `tests/Feature/Admin/QuarantineResolutionUITest.php` | **Criar** |
| `tests/Feature/Quarantine/WhatsAppCheckButtonTest.php` | **Criar** |

---

## Mocking Strategy

```php
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake([
        'openwa:2785/api/sessions/*/contacts/check/*' => Http::response(['exists' => false], 404),
        'openwa:2785/api/sessions/*/contacts/*/phone' => Http::response(['phone' => null], 404),
    ]);
});
```

---

## Evidências GREEN

```bash
php artisan migrate
php artisan test tests/Feature/Quarantine/  # todos passam
php artisan test tests/Feature/Admin/QuarantineResolutionUITest.php
php artisan test tests/Feature/Quarantine/WhatsAppCheckButtonTest.php
./vendor/bin/pint
```

---

## Próximos Slices

Após GREEN → **#11-17 Mídia Privada** (download inline/omitted, upload outbound, download operador, retenção, validação MIME, MinIO).