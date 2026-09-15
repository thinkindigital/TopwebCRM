<?php

namespace Webkul\Lead\Services;

use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Models\LeadIngestion;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\User\Models\User;

/**
 * #19: ingestão idempotente de Leads por integrações externas.
 * Sem Activity/saudação (importação nunca abre atendimento), sem
 * produtos/cotações/IA (non-goals da slice). Resposta só com IDs.
 */
class LeadIngestionService
{
    public function __construct(
        protected LeadRepository $leads,
        protected PersonRepository $persons,
        protected SourceRepository $sources
    ) {}

    /**
     * @return array{lead_id: int, person_id: ?int, duplicate: bool}
     */
    public function ingest(array $data): array
    {
        $existing = LeadIngestion::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing) {
            return [
                'lead_id' => (int) $existing->lead_id,
                'person_id' => $existing->person_id !== null ? (int) $existing->person_id : null,
                'duplicate' => true,
            ];
        }

        $owner = User::query()->find($data['owner_id']);

        if (! $owner || ! $owner->status) {
            throw new DomainException('Owner inválido ou inativo.');
        }

        $source = $this->sources->findOneByField('name', $this->sourceName($data['source']));

        try {
            return DB::transaction(function () use ($data, $owner, $source) {
                $person = $this->findOrCreatePerson($data['person'] ?? [], (int) $owner->id);

                $lead = $this->leads->create([
                    'title' => $data['lead']['title'],
                    'user_id' => $owner->id,
                    'person_id' => $person?->id,
                    'lead_source_id' => $source?->id,
                ]);

                $ingestion = LeadIngestion::query()->create([
                    'source' => $data['source'],
                    'source_lead_id' => $data['source_lead_id'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                    'lead_id' => $lead->id,
                    'person_id' => $person?->id,
                ]);

                return [
                    'lead_id' => (int) $ingestion->lead_id,
                    'person_id' => $ingestion->person_id !== null ? (int) $ingestion->person_id : null,
                    'duplicate' => false,
                ];
            });
        } catch (QueryException $exception) {
            // Corrida concorrente na mesma chave: devolve o registro vencedor.
            $winner = LeadIngestion::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($winner) {
                return [
                    'lead_id' => (int) $winner->lead_id,
                    'person_id' => $winner->person_id !== null ? (int) $winner->person_id : null,
                    'duplicate' => true,
                ];
            }

            throw $exception;
        }
    }

    protected function sourceName(string $source): string
    {
        return match (mb_strtolower($source)) {
            'meta' => 'Meta Ads',
            'google' => 'Google Ads',
            default => $source,
        };
    }

    protected function findOrCreatePerson(array $person, int $ownerId): ?object
    {
        if (empty($person['name'])) {
            return null;
        }

        $email = $person['emails'][0]['value'] ?? null;

        if (is_string($email)) {
            $found = $this->persons->findWhere([
                ['emails', 'LIKE', '%"value":"'.$email.'"%'],
            ])->first();

            if ($found) {
                return $found;
            }
        }

        return $this->persons->create(array_merge($person, [
            'entity_type' => 'persons',
            'user_id' => $ownerId,
        ]));
    }
}
