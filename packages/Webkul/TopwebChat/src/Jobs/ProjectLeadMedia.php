<?php

namespace Webkul\TopwebChat\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Services\LeadMediaProjector;

class ProjectLeadMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(public int $messageId) {}

    public function handle(LeadMediaProjector $projector): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message) {
            $projector->project($message);
        }
    }
}
