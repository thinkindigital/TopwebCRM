<?php

namespace Webkul\TopwebChat\Services;

use DomainException;
use Webkul\TopwebChat\Models\Instance;

class WebhookUrlService
{
    public function forInstance(Instance $instance): string
    {
        $baseUrl = config('topweb-chat.public_url') ?: config('app.url');
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (
            in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! app()->environment('local', 'testing')
        ) {
            throw new DomainException(trans('topweb_chat::app.webhook.public_url_required'));
        }

        return rtrim($baseUrl, '/')
            .'/api/topweb-chat/webhooks/openwa/'
            .$instance->getRouteKey();
    }
}
