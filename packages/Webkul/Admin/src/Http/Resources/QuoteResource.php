<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $sensitiveData = app(SensitiveDataService::class);

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'description' => $sensitiveData->protect('quotes', 'description', $this->description),
            'billing_address' => $sensitiveData->protect('quotes', 'billing_address', $this->billing_address),
            'shipping_address' => $sensitiveData->protect('quotes', 'shipping_address', $this->shipping_address),
            'discount_percent' => $sensitiveData->protect('quotes', 'discount_percent', $this->discount_percent),
            'discount_amount' => $sensitiveData->protect('quotes', 'discount_amount', $this->discount_amount),
            'tax_amount' => $sensitiveData->protect('quotes', 'tax_amount', $this->tax_amount),
            'adjustment_amount' => $sensitiveData->protect('quotes', 'adjustment_amount', $this->adjustment_amount),
            'sub_total' => $sensitiveData->protect('quotes', 'sub_total', $this->sub_total),
            'grand_total' => $sensitiveData->protect('quotes', 'grand_total', $this->grand_total),
            'expired_at' => $this->expired_at,
            'user' => new UserResource($this->user),
            'person' => new PersonResource($this->person),
            'leads' => LeadResource::collection($this->leads),
        ];
    }
}
