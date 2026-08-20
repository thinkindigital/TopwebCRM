<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailResource extends JsonResource
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
            'subject' => $sensitiveData->protect('emails', 'subject', $this->subject),
            'source' => $sensitiveData->protect('emails', 'source', $this->source),
            'user_type' => $this->user_type,
            'name' => $sensitiveData->protect('emails', 'name', $this->name),
            'reply' => $sensitiveData->protect('emails', 'reply', $this->reply),
            'is_read' => $this->is_read,
            'folders' => $this->folders,
            'from' => $sensitiveData->protect('emails', 'from', $this->from),
            'sender' => $sensitiveData->protect('emails', 'sender', $this->sender),
            'reply_to' => $sensitiveData->protect('emails', 'reply_to', $this->reply_to),
            'cc' => $sensitiveData->protect('emails', 'cc', $this->cc),
            'bcc' => $sensitiveData->protect('emails', 'bcc', $this->bcc),
            'unique_id' => $sensitiveData->protect('emails', 'unique_id', $this->unique_id),
            'message_id' => $sensitiveData->protect('emails', 'message_id', $this->message_id),
            'reference_ids' => $sensitiveData->protect('emails', 'reference_ids', $this->reference_ids),
            'person' => new PersonResource($this->person),
            'lead' => new LeadResource($this->lead),
            'parent_id' => $this->parent_id,
            'parent' => $this->parent ? new EmailResource($this->parent) : null,
            'attachments' => $sensitiveData->canView() ? EmailAttachmentResource::collection($this->attachments) : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
