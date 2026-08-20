<?php

namespace Webkul\TopwebChat\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\InternalNote;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Models\WebhookEvent;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        Instance::class,
        Conversation::class,
        Message::class,
        InternalNote::class,
        WebhookEvent::class,
    ];
}
