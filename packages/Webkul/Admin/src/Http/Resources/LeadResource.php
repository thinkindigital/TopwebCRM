<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request
     * @return array
     */
    public function toArray($request)
    {
        $sensitiveData = app(SensitiveDataService::class);
        $leadValue = $sensitiveData->protect('leads', 'lead_value', $this->lead_value);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'lead_value' => $leadValue,
            'formatted_lead_value' => $leadValue === null && $this->lead_value !== null
                ? config('sensitive-data.mask')
                : core()->formatBasePrice($leadValue),
            'status' => $this->status,
            'expected_close_date' => $this->expected_close_date,
            'rotten_days' => $this->rotten_days,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'person' => $this->person ? new PersonResource($this->person) : null,
            'user' => $this->user ? new UserResource($this->user) : null,
            'type' => $this->type ? new TypeResource($this->type) : null,
            'source' => $sensitiveData->canView() && $this->source ? new SourceResource($this->source) : null,
            'pipeline' => $this->pipeline ? new PipelineResource($this->pipeline) : null,
            'stage' => $this->stage ? new StageResource($this->stage) : null,
            'tags' => TagResource::collection($this->tags),
        ];
    }
}
