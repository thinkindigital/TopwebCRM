# TDD Specification — Slice #20: Groups Domain (ACL + auditoria propria)

> **Fase**: RED
> **Dependência**: #11-19 (media + interactive base)

---

## 1. Comportamentos Criticos

| Comportamento | Prioridade |
|---------------|------------|
| **Entidades** | `TopwebChatGroup`: `id`, `instance_id`, `group_jid` (criptografado), `subject`, `description`, `owner_jid`, `created_at`, `settings` (JSON: announce, locked, etc.) | P0 |
| **Participantes** | `TopwebChatGroupParticipant`: `group_id`, `participant_jid`, `is_admin`, `joined_at` | P0 |
| **Webhooks** | `group.join` / `group.leave` / `group.update` / `group.join_request` -> Jobs de processamento | P0 |
| **API OpenWA** | `GET /api/sessions/:sessionId/groups`, `GET /api/sessions/:sessionId/groups/:groupId`, `POST /api/sessions/:sessionId/groups`, `POST /api/sessions/:sessionId/groups/:groupId/participants`, `GET/POST /api/sessions/:sessionId/groups/:groupId/membership-requests` | P0 |
| **UI Admin** | Menu TopwebChat > Grupos (apenas admins/operadores com `topweb_chat.groups`) | P0 |
| **ACL** | `topweb_chat.groups.view`, `topweb_chat.groups.manage`, `topweb_chat.groups.moderate` | P0 |

---

## 2. Test Specification (RED)

### Teste 1: Entidades Group + Participant (Migrations + Models + Repositories)

```php
// tests/Feature/Domain/GroupsDomainTest.php

it('creates Group and Participant entities with correct schema', function () {
    $this->artisan('migrate --pretend')
        ->expectsOutputToContain('topweb_chat_groups')
        ->expectsOutputToContain('topweb_chat_group_participants');

    $this->artisan('migrate');

    expect(Schema::hasTable('topweb_chat_groups'))->toBeTrue();
    expect(Schema::hasTable('topweb_chat_group_participants'))->toBeTrue();
    expect(Schema::hasColumns('topweb_chat_groups', ['group_jid', 'subject', 'description', 'owner_jid', 'settings']))->toBeTrue();
    expect(Schema::hasColumns('topweb_chat_group_participants', ['group_id', 'participant_jid', 'is_admin', 'joined_at']))->toBeTrue();
});
```

### Teste 2: Group Model + Repository

```php
it('creates Group with encrypted group_jid and hash key', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-group', 'status' => 'ready']);
    
    $group = TopwebChatGroup::create([
        'instance_id' => $instance->id,
        'group_jid' => '120363021234567890@g.us',
        'subject' => 'Equipe Projetos',
        'description' => 'Grupo para coordenacao de projetos',
        'owner_jid' => '5511999999999@c.us',
        'settings' => json_encode(['announce' => false, 'locked' => false]),
    ]);

    expect($group->group_jid_key)->toBe(hash('sha256', '120363021234567890@g.us'));
    expect($group->settings)->toBeArray();
    expect($group->settings['announce'])->toBeFalse();
});
```

### Teste 3: Webhooks group.* Processados -> Sincronizam Estado Local

```php
it('processes group.join webhook', function () {
    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-gjoin', 'status' => 'ready']);
    $group = TopwebChatGroup::factory()->create([
        'instance_id' => $instance->id,
        'group_jid' => '120363021234567890@g.us',
    ]);

    $payload = [
        'event' => 'group.join',
        'sessionId' => 'uuid-gjoin',
        'idempotencyKey' => 'grp_120363021234567890@g.us_join_1724150000',
        'deliveryId' => 'dlv_gjoin1',
        'data' => [
            'groupId' => '120363021234567890@g.us',
            'actorId' => '5511999999999@c.us',
            'participantIds' => ['5511888888888@c.us', '5511777777777@c.us'],
            'timestamp' => 1724150000,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-gjoin/messages/*/history?limit=50' => Http::response([], 200)]);

    $response = $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-gjoin', $payload, $headers);

    $response->assertStatus(200);

    $participants = TopwebChatGroupParticipant::where('group_id', $group->id)->get();
    expect($participants)->toHaveCount(2);
    expect($participants->first()->participant_jid)->toBe('5511888888888@c.us');
    expect($participants->first()->is_admin)->toBeFalse();
    expect($participants->first()->joined_at)->toBe('2026-08-20 10:00:00');
});
```

```php
it('processes group.leave webhook', function () {
    $group = TopwebChatGroup::factory()->create();
    TopwebChatGroupParticipant::factory()->create([
        'group_id' => $group->id,
        'participant_jid' => '5511888888888@c.us',
    ]);

    $payload = [
        'event' => 'group.leave',
        'sessionId' => 'uuid-gleave',
        'idempotencyKey' => 'grp_120363021234567890@g.us_leave_1724150100',
        'data' => [
            'groupId' => '120363021234567890@g.us',
            'actorId' => '5511999999999@c.us',
            'participantIds' => ['5511888888888@c.us'],
            'timestamp' => 1724150100,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-gleave/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-gleave', $payload, $headers);

    expect(TopwebChatGroupParticipant::where('group_id', $group->id)
        ->where('participant_jid', '5511888888888@c.us')
        ->exists())->toBeFalse();
});
```

```php
it('processes group.update webhook', function () {
    $group = TopwebChatGroup::factory()->create([
        'subject' => 'Old Subject',
        'description' => 'Old desc',
        'settings' => json_encode(['announce' => false, 'locked' => false]),
    ]);

    $payload = [
        'event' => 'group.update',
        'sessionId' => 'uuid-gupdate',
        'idempotencyKey' => 'grp_120363021234567890@g.us_update_1724150200',
        'data' => [
            'groupId' => '120363021234567890@g.us',
            'actorId' => '5511999999999@c.us',
            'participantIds' => [],
            'changes' => [
                'subject' => 'New Subject',
                'announce' => true,
            ],
            'timestamp' => 1724150200,
        ],
    ];

    Http::fake(['openwa:2785/api/sessions/uuid-gupdate/messages/*/history?limit=50' => Http::response([], 200)]);

    $this->postJson('/api/topweb-chat/webhooks/openwa/uuid-gupdate', $payload, $headers);

    $group = $group->fresh();
    expect($group->subject)->toBe('New Subject');
    expect($group->settings['announce'])->toBeTrue();
    expect($group->settings['locked'])->toBeFalse();
});
```

### Teste 4: API OpenWA Wrapper no OpenWaProvider

```php
it('wraps OpenWA groups API in OpenWaProvider', function () {
    $provider = app(OpenWaProvider::class);
    $sessionUuid = 'uuid-api-group';

    Http::fake([
        'openwa:2785/api/sessions/uuid-api-group/groups' => Http::response([
            ['id' => '120363021234567890@g.us', 'name' => 'Test Group', 'linkedParentJID' => null],
        ], 200),
        'openwa:2785/api/sessions/uuid-api-group/groups/120363021234567890@g.us' => Http::response([
            'id' => '120363021234567890@g.us',
            'name' => 'Test Group',
            'participants' => [['id' => '5511888888888@c.us', 'isAdmin' => false]],
        ], 200),
    ]);

    $groups = $provider->getGroups($sessionUuid);
    expect($groups)->toHaveCount(1);
    expect($groups[0]['id'])->toBe('120363021234567890@g.us');

    $groupDetails = $provider->getGroup($sessionUuid, '120363021234567890@g.us');
    expect($groupDetails['participants'])->toHaveCount(1);
});
```

### Teste 5: UI Admin - Menu TopwebChat > Grupos

```php
// tests/Feature/Admin/GroupsAdminTest.php

it('shows groups list for users with topweb_chat.groups.view permission', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.groups.view');

    $instance = Instance::factory()->enabled()->create(['session_uuid' => 'uuid-admin-groups']);
    $groups = TopwebChatGroup::factory()->count(3)->create(['instance_id' => $instance->id]);

    $response = $this->actingAs($admin)->get('/admin/topweb-chat/groups');
    
    $response->assertStatus(200);
    $response->assertSee('Grupos WhatsApp');
    foreach ($groups as $g) {
        $response->assertSee($g->subject);
    }
});
```

```php
it('shows group details with participants for manage permission', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo('topweb_chat.groups.manage');

    $group = TopwebChatGroup::factory()->create([
        'subject' => 'Equipe Vendas',
        'description' => 'Grupo da equipe de vendas',
    ]);
    TopwebChatGroupParticipant::factory()->count(2)->create(['group_id' => $group->id, 'is_admin' => false]);
    TopwebChatGroupParticipant::factory()->create(['group_id' => $group->id, 'is_admin' => true]);

    $response = $this->actingAs($admin)->get("/admin/topweb-chat/groups/{$group->id}");
    
    $response->assertStatus(200);
    $response->assertSee('Equipe Vendas');
    $response->assertSee('Participantes');
    $response->assertSee('Adicionar participante');
    $response->assertSee('Remover participante');
    $response->assertSee('Promover a admin');
});
```

### Teste 6: ACL Granular

```php
it('enforces granular ACL for groups', function () {
    $userView = User::factory()->create();
    $userView->givePermissionTo('topweb_chat.groups.view');

    $userManage = User::factory()->create();
    $userManage->givePermissionTo('topweb_chat.groups.manage');

    $userModerate = User::factory()->create();
    $userModerate->givePermissionTo('topweb_chat.groups.moderate');

    $group = TopwebChatGroup::factory()->create();

    $response = $this->actingAs($userView)->get('/admin/topweb-chat/groups');
    $response->assertStatus(200);

    $response = $this->actingAs($userView)->get("/admin/topweb-chat/groups/{$group->id}");
    $response->assertStatus(403);

    $response = $this->actingAs($userManage)->get("/admin/topweb-chat/groups/{$group->id}");
    $response->assertStatus(200);

    // moderate can approve join requests (future)
});
```

---

## 3. Interface Contracts

### Entities

```php
// app/Models/TopwebChatGroup.php

class TopwebChatGroup extends Model {
    protected $fillable = [
        'instance_id', 'group_jid', 'group_jid_key', 'subject', 
        'description', 'owner_jid', 'settings',
    ];
    protected $casts = ['settings' => 'array'];

    public function participants(): HasMany {
        return $this->hasMany(TopwebChatGroupParticipant::class);
    }
}

// app/Models/TopwebChatGroupParticipant.php

class TopwebChatGroupParticipant extends Model {
    protected $fillable = ['group_id', 'participant_jid', 'is_admin', 'joined_at'];
    protected $casts = ['is_admin' => 'boolean'];
}
```

### Repositories

```php
// app/Repositories/TopwebChatGroupRepository.php

class TopwebChatGroupRepository extends BaseRepository {
    public function findByGroupJidKey(string $key): ?TopwebChatGroup;
    public function getGroupsForInstance(int $instanceId): Collection;
}
```

### OpenWaProvider Methods

```php
// app/Services/Messaging/OpenWaProvider.php

public function getGroups(string $sessionUuid, int $limit = 1000, int $offset = 0): array;
public function getGroup(string $sessionUuid, string $groupId): array;
public function createGroup(string $sessionUuid, string $subject, array $participants = []): array;
public function addParticipants(string $sessionUuid, string $groupId, array $participants): array;
public function removeParticipants(string $sessionUuid, string $groupId, array $participants): array;
public function promoteParticipants(string $sessionUuid, string $groupId, array $participants): array;
public function demoteParticipants(string $sessionUuid, string $groupId, array $participants): array;
public function getMembershipRequests(string $sessionUuid, string $groupId): array;
public function approveMembershipRequest(string $sessionUuid, string $groupId, string $participantId): array;
public function rejectMembershipRequest(string $sessionUuid, string $groupId, string $participantId): array;
```

### Webhook Handlers

```php
// Jobs: ProcessGroupJoin, ProcessGroupLeave, ProcessGroupUpdate, ProcessGroupJoinRequest
```

### ACL Permissions

```php
// permissions
'topweb_chat.groups.view'     // listar grupos
'topweb_chat.groups.manage'   // ver detalhes, adicionar/remover participantes, promover/rebaixar
'topweb_chat.groups.moderate' // aprovar/recusar solicitacoes de entrada
```

---

## 4. Arquivos a Criar/Modificar

| Arquivo | Acao |
|---------|------|
| `database/migrations/xxxx_create_topweb_chat_groups_table.php` | **Criar** |
| `database/migrations/xxxx_create_topweb_chat_group_participants_table.php` | **Criar** |
| `app/Models/TopwebChatGroup.php` | **Criar** |
| `app/Models/TopwebChatGroupParticipant.php` | **Criar** |
| `app/Repositories/TopwebChatGroupRepository.php` | **Criar** |
| `app/Services/Messaging/OpenWaProvider.php` | Estender (groups methods) |
| `app/Services/TopwebChat/GroupService.php` | **Criar** |
| `app/Http/Controllers/Api/TopwebChat/GroupController.php` | **Criar** |
| `app/Http/Controllers/Admin/TopwebChat/GroupController.php` | **Criar** |
| `packages/Webkul/TopwebChat/src/Resources/views/admin/groups/` | **Criar** (index, show, create, edit) |
| `routes/api.php` | Registrar rotas groups |
| `routes/web.php` | Registrar rotas admin groups |
| `config/topweb-chat.php` | Adicionar permissoes groups |
| `tests/Feature/Domain/GroupsDomainTest.php` | **Criar** |
| `tests/Feature/Admin/GroupsAdminTest.php` | **Criar** |

---

## 5. Mocking Strategy

```php
beforeEach(function () {
    Http::fake([
        'openwa:2785/api/sessions/*/groups*' => Http::response([], 404),
        'openwa:2785/api/sessions/*/groups/*/membership-requests*' => Http::response([], 404),
    ]);
});
```

---

## 6. Evidencias GREEN

```bash
php artisan migrate
php artisan test tests/Feature/Domain/GroupsDomainTest.php
php artisan test tests/Feature/Admin/GroupsAdminTest.php
./vendor/bin/pint
```

---

## Proximo Slice

Apos GREEN -> **#21 Calls/Channels Domain** (calls + status.received).