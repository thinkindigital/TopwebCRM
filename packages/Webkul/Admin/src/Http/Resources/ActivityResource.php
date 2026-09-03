<?php

namespace Webkul\Admin\Http\Resources;

use App\Services\SensitiveDataService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Webkul\TopwebChat\Services\MediaProjectionAccessService;

class ActivityResource extends JsonResource
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
        $additional = is_array($this->resource->additional)
            ? $this->resource->additional
            : json_decode($this->resource->additional, true);
        $user = auth()->guard('user')->user();
        $files = $sensitiveData->canView($user)
            ? $this->files->filter(
                fn ($file) => app(MediaProjectionAccessService::class)
                    ->canAccess($user, $file)
            )
            : collect();

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id ?? null,
            'title' => $sensitiveData->protect('activities', 'title', $this->title),
            'type' => $this->type,
            'comment' => $sensitiveData->protect('activities', 'comment', $this->comment),
            'additional' => $sensitiveData->maskActivityAdditional($this->type, $additional),
            'schedule_from' => $this->schedule_from,
            'schedule_to' => $this->schedule_to,
            'is_done' => $this->is_done,
            'user' => new UserResource($this->user),
            'files' => ActivityFileResource::collection($files),
            'participants' => ActivityParticipantResource::collection($this->participants),
            'location' => $sensitiveData->protect('activities', 'location', $this->location),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
