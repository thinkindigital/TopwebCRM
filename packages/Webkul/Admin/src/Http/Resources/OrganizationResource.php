<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
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
            'address' => $sensitiveData->protect('organizations', 'address', $this->address),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
