<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;
use Webkul\Lead\Models\Stage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\InternalNote;
use Webkul\TopwebChat\Models\Message;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class PrepareE2eFixtures extends Command
{
    protected $signature = 'topweb-chat:e2e-fixtures
        {--install : Create or refresh the isolated DEV fixtures}
        {--cleanup : Remove only the isolated DEV fixtures}
        {--json : Print machine-readable fixture identifiers}';

    protected $description = 'Prepare or remove isolated TopwebChat browser fixtures';

    private const PREFIX = 'TopwebChat E2E ';

    public function handle(): int
    {
        if (in_array(app()->environment(), ['production', 'prod'], true)) {
            $this->error('E2E fixtures are disabled in production.');

            return self::FAILURE;
        }

        if ((bool) $this->option('install') === (bool) $this->option('cleanup')) {
            $this->error('Choose exactly one of --install or --cleanup.');

            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            $this->cleanup();

            if ($this->option('json')) {
                $this->line(json_encode(['cleaned' => true], JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        }

        $password = (string) env('E2E_FIXTURE_PASSWORD', '');
        if ($password === '') {
            $this->error('E2E_FIXTURE_PASSWORD is required for fixture installation.');

            return self::FAILURE;
        }

        $fixture = DB::transaction(fn () => $this->install($password));

        if ($this->option('json')) {
            $this->line(json_encode($fixture, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Fixture', 'Value'], [
                ['Wallet A user', $fixture['wallet_a']['email']],
                ['Wallet A open conversation', $fixture['wallet_a']['open_conversation_id']],
                ['Wallet A closed conversation', $fixture['wallet_a']['closed_conversation_id']],
                ['Wallet B conversation', $fixture['wallet_b']['conversation_id']],
                ['Operational conversation', $fixture['operational_conversation_id']],
            ]);
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function install(string $password): array
    {
        $role = Role::query()->updateOrCreate(
            ['name' => self::PREFIX.'Agent'],
            [
                'permission_type' => 'custom',
                'permissions' => [
                    'topweb_chat',
                    'topweb_chat.inbox',
                    'topweb_chat.inbox.view',
                    'topweb_chat.inbox.send',
                    'topweb_chat.inbox.notes',
                    'topweb_chat.inbox.notes.delete',
                    'topweb_chat.inbox.activities',
                    'topweb_chat.inbox.stage',
                    'leads.edit',
                    'activities.create',
                    'activities.edit',
                ],
            ]
        );
        $adminRole = Role::query()->updateOrCreate(
            ['name' => self::PREFIX.'Admin'],
            [
                'permission_type' => 'all',
                'permissions' => [],
            ]
        );
        $claimRole = Role::query()->updateOrCreate(
            ['name' => self::PREFIX.'Blind Claim Agent'],
            [
                'permission_type' => 'custom',
                'permissions' => [
                    'topweb_chat',
                    'topweb_chat.inbox',
                    'topweb_chat.inbox.view',
                ],
            ]
        );

        $walletA = $this->fixtureUser('E2E_FIXTURE_WALLET_A_EMAIL', 'topwebchat-e2e-wallet-a@example.test', 'Wallet A', $role, $password);
        $walletB = $this->fixtureUser('E2E_FIXTURE_WALLET_B_EMAIL', 'topwebchat-e2e-wallet-b@example.test', 'Wallet B', $role, $password);
        $this->fixtureUser('E2E_FIXTURE_ADMIN_EMAIL', 'topwebchat-e2e-admin@example.test', 'E2E Admin', $adminRole, $password);
        $claimUser = $this->fixtureUser('E2E_FIXTURE_CLAIM_EMAIL', 'topwebchat-e2e-claim@example.test', 'Blind Claim Agent', $claimRole, $password);

        $pipelineA = $this->pipeline(self::PREFIX.'Pipeline A');
        $pipelineB = $this->pipeline(self::PREFIX.'Pipeline B');
        $stageA1 = $this->stage($pipelineA, self::PREFIX.'Stage A1', 1);
        $this->stage($pipelineA, self::PREFIX.'Stage A2', 2);
        $stageB1 = $this->stage($pipelineB, self::PREFIX.'Stage B1', 1);
        $this->stage($pipelineB, self::PREFIX.'Stage B2', 2);

        $personA = Person::query()->updateOrCreate(
            ['name' => self::PREFIX.'Person A'],
            ['user_id' => $walletA->id]
        );
        $personB = Person::query()->updateOrCreate(
            ['name' => self::PREFIX.'Person B'],
            ['user_id' => $walletB->id]
        );

        $leadA = Lead::query()->updateOrCreate(
            ['title' => self::PREFIX.'Lead A'],
            [
                'person_id' => $personA->id,
                'user_id' => $walletA->id,
                'lead_pipeline_id' => $pipelineA->id,
                'lead_pipeline_stage_id' => $stageA1->id,
            ]
        );
        $leadB = Lead::query()->updateOrCreate(
            ['title' => self::PREFIX.'Lead B'],
            [
                'person_id' => $personB->id,
                'user_id' => $walletB->id,
                'lead_pipeline_id' => $pipelineB->id,
                'lead_pipeline_stage_id' => $stageB1->id,
            ]
        );

        $instance = Instance::query()->updateOrCreate(
            ['name' => self::PREFIX.'Offline Channel'],
            [
                'provider' => 'openwa',
                'status' => 'offline',
                'enabled' => false,
                'base_url' => 'https://e2e.invalid',
            ]
        );

        $openA = $this->conversation($instance, $personA, $leadA, 'e2e-wallet-a-open', 'open');
        $closedA = $this->conversation($instance, $personA, $leadA, 'e2e-wallet-a-closed', 'closed');
        $conversationB = $this->conversation($instance, $personB, $leadB, 'e2e-wallet-b', 'open');
        $blindConversation = Conversation::query()->updateOrCreate(
            ['remote_jid_key' => hash('sha256', 'e2e-blind-claim')],
            [
                'instance_id' => $instance->id,
                'person_id' => $personA->id,
                'lead_id' => null,
                'assigned_user_id' => null,
                'remote_jid' => 'e2e-blind-claim@s.whatsapp.net',
                'status' => 'open',
                'last_message_at' => now()->subMinutes(3),
            ]
        );

        foreach ([$openA, $closedA, $conversationB] as $conversation) {
            Message::query()->updateOrCreate(
                ['conversation_id' => $conversation->id, 'source' => 'e2e-fixture'],
                [
                    'operation_key' => (string) Str::uuid(),
                    'direction' => 'inbound',
                    'type' => 'text',
                    'content' => self::PREFIX.'message',
                    'status' => 'delivered',
                    'source' => 'e2e-fixture',
                    'sent_at' => now()->subMinutes(5),
                ]
            );
        }

        InternalNote::query()->updateOrCreate(
            ['conversation_id' => $openA->id, 'content' => self::PREFIX.'note'],
            ['user_id' => $walletA->id]
        );

        $this->activity($leadA, $personA, $walletA, 'call', now()->addDay(), now()->addDay()->addMinutes(30));
        $this->activity($leadA, $personA, $walletA, 'meeting', now()->subDay(), now()->subDay()->addMinutes(30), true);

        return [
            'wallet_a' => [
                'email' => $walletA->email,
                'person_name' => $personA->name,
                'lead_title' => $leadA->title,
                'open_conversation_id' => $openA->id,
                'closed_conversation_id' => $closedA->id,
            ],
            'wallet_b' => [
                'email' => $walletB->email,
                'person_name' => $personB->name,
                'lead_title' => $leadB->title,
                'conversation_id' => $conversationB->id,
            ],
            'operational_conversation_id' => $openA->id,
            'blind_conversation_id' => $blindConversation->id,
            'claim_user_email' => $claimUser->email,
            'pipeline_ids' => [$pipelineA->id, $pipelineB->id],
            'stage_ids' => [$stageA1->id, $stageB1->id],
        ];
    }

    private function fixtureUser(string $environmentKey, string $defaultEmail, string $name, Role $role, string $password): User
    {
        $email = (string) env($environmentKey, $defaultEmail);

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role_id' => $role->id,
                'status' => true,
            ]
        );
    }

    private function pipeline(string $name): Pipeline
    {
        return Pipeline::query()->updateOrCreate(['name' => $name], ['rotten_days' => 0, 'is_default' => false]);
    }

    private function stage(Pipeline $pipeline, string $name, int $sortOrder): Stage
    {
        return Stage::query()->updateOrCreate(
            ['name' => $name, 'lead_pipeline_id' => $pipeline->id],
            ['code' => strtolower(str_replace(' ', '-', $name)), 'sort_order' => $sortOrder, 'probability' => 50]
        );
    }

    private function conversation(Instance $instance, Person $person, Lead $lead, string $key, string $status): Conversation
    {
        return Conversation::query()->updateOrCreate(
            ['remote_jid_key' => hash('sha256', $key)],
            [
                'instance_id' => $instance->id,
                'person_id' => $person->id,
                'lead_id' => $lead->id,
                'assigned_user_id' => $lead->user_id,
                'remote_jid' => $key.'@s.whatsapp.net',
                'status' => $status,
                'last_message_at' => now()->subMinutes(5),
            ]
        );
    }

    private function activity(
        Lead $lead,
        Person $person,
        User $owner,
        string $type,
        mixed $from,
        mixed $to,
        bool $done = false
    ): void {
        $activity = Activity::query()->updateOrCreate(
            ['title' => self::PREFIX.$type],
            [
                'type' => $type,
                'schedule_from' => $from,
                'schedule_to' => $to,
                'is_done' => $done,
                'user_id' => $owner->id,
            ]
        );
        $activity->leads()->syncWithoutDetaching([$lead->id]);
        $activity->persons()->syncWithoutDetaching([$person->id]);
    }

    private function cleanup(): void
    {
        DB::transaction(function (): void {
            $conversationIds = Conversation::query()
                ->whereIn('remote_jid_key', [
                    hash('sha256', 'e2e-wallet-a-open'),
                    hash('sha256', 'e2e-wallet-a-closed'),
                    hash('sha256', 'e2e-wallet-b'),
                    hash('sha256', 'e2e-blind-claim'),
                ])->pluck('id');

            DB::table('topweb_chat_messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('topweb_chat_internal_notes')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('topweb_chat_attendances')->whereIn('conversation_id', $conversationIds)->delete();
            Conversation::query()->whereIn('id', $conversationIds)->delete();

            $activityIds = Activity::query()->where('title', 'like', self::PREFIX.'%')->pluck('id');
            DB::table('lead_activities')->whereIn('activity_id', $activityIds)->delete();
            DB::table('person_activities')->whereIn('activity_id', $activityIds)->delete();
            Activity::query()->whereIn('id', $activityIds)->delete();

            Lead::query()->where('title', 'like', self::PREFIX.'%')->delete();
            Person::query()->where('name', 'like', self::PREFIX.'%')->delete();
            Instance::query()->where('name', self::PREFIX.'Offline Channel')->delete();

            $users = User::query()->whereIn('email', [
                (string) env('E2E_FIXTURE_WALLET_A_EMAIL', 'topwebchat-e2e-wallet-a@example.test'),
                (string) env('E2E_FIXTURE_WALLET_B_EMAIL', 'topwebchat-e2e-wallet-b@example.test'),
            ])->pluck('id');
            User::query()->whereIn('id', $users)->delete();
            User::query()->where('email', (string) env('E2E_FIXTURE_CLAIM_EMAIL', 'topwebchat-e2e-claim@example.test'))->delete();
            Role::query()->where('name', self::PREFIX.'Agent')->delete();
            Role::query()->where('name', self::PREFIX.'Admin')->delete();
            Role::query()->where('name', self::PREFIX.'Blind Claim Agent')->delete();
        });
    }
}
