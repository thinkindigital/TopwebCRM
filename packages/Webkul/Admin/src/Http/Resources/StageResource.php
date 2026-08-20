<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'lead_value' => $leadValue,
            'formatted_lead_value' => $leadValue === null && $this->lead_value !== null
                ? config('sensitive-data.mask')
                : core()->formatBasePrice($leadValue),
            'is_user_defined' => $this->is_user_defined,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
