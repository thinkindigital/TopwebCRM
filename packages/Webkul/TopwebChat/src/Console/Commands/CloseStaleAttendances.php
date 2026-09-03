<?php

namespace Webkul\TopwebChat\Console\Commands;

use Illuminate\Console\Command;
use Webkul\TopwebChat\Services\AttendanceService;

class CloseStaleAttendances extends Command
{
    protected $signature = 'topweb-chat:close-stale-attendances';

    protected $description = 'Close WhatsApp attendance activities after the inactivity window';

    public function handle(AttendanceService $attendances): int
    {
        $closed = $attendances->closeStale();

        $this->components->info("WhatsApp attendances closed: {$closed}.");

        return self::SUCCESS;
    }
}
