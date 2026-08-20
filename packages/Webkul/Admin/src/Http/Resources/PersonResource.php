<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
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

        return [
            'id' => $this->id,
            'name' => $this->name,
            'emails' => $sensitiveData->protect('persons', 'emails', $this->emails),
            'contact_numbers' => $sensitiveData->protect('persons', 'contact_numbers', $this->contact_numbers),
            'organization' => $this->organization ? new OrganizationResource($this->organization) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
