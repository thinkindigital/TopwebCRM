<?php

namespace Webkul\TopwebChat\Services;

use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;

class InboundLeadAssociationService
{
    public function __construct(
        protected LeadRepository $leads,
        protected PipelineRepository $pipelines
    ) {}

    public function associate(Person $person): ?Lead
    {
        $person = Person::query()->lockForUpdate()->findOrFail($person->id);
        $operationalLeads = $this->operationalLeads($person);

        if ($operationalLeads->count() === 1) {
            return $operationalLeads->first();
        }

        if ($operationalLeads->count() > 1) {
            return null;
        }

        if (! config('topweb-chat.inbound.auto_create_lead', true)) {
            return null;
        }

        return $this->createForPersonLocked($person);
    }

    public function createForPerson(Person $person): Lead
    {
        return DB::transaction(function () use ($person) {
            $person = Person::query()->lockForUpdate()->findOrFail($person->id);

            return $this->createForPersonLocked($person);
        });
    }

    private function createForPersonLocked(Person $person): Lead
    {
        $pipeline = $this->pipelines->getDefaultPipeline();
        $stage = $pipeline?->stages()->first();

        if (! $pipeline || ! $stage) {
            throw new DomainException('Inbound Lead defaults are not configured.');
        }

        return $this->leads->create([
            'title' => trans('topweb_chat::app.inbound.lead_title', [
                'name' => $person->name,
            ]),
            'user_id' => null,
            'person_id' => $person->id,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $stage->id,
            'status' => true,
            'entity_type' => 'leads',
        ]);
    }

    /**
     * A closed or terminal Lead is not a candidate for a new inbound thread.
     */
    public function operationalLeads(Person $person): Collection
    {
        return $person->leads()
            ->whereNull('closed_at')
            ->whereHas('stage', fn ($query) => $query->whereNotIn('code', ['won', 'lost']))
            ->latest('leads.updated_at')
            ->get();
    }
}
