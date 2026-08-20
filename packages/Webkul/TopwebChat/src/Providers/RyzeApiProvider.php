<?php

namespace Webkul\TopwebChat\Providers;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

class RyzeApiProvider implements MessagingProvider
{
    public function sendText(Instance $instance, string $recipient, string $message): array
    {
        $response = $this->client($instance)->post(
            '/api/message/text/'.rawurlencode($instance->name),
            [
                'number' => $recipient,
                'message' => $message,
                'source' => 'topweb_chat',
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.send_failed'),
            true
        );

        return $response->json();
    }

    public function connectionStatus(Instance $instance): string
    {
        $response = $this->client($instance)->get('/api/instance/list', [
            'instanceName' => $instance->name,
        ]);

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.instance_status_failed')
        );

        return (string) (
            $response->json('instances.0.connection.state')
            ?: $response->json('instances.0.status')
            ?: 'unknown'
        );
    }

    public function history(
        Instance $instance,
        string $recipient,
        int $count = 100,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null
    ): array {
        $payload = [
            'number' => $recipient,
            'count' => min(max($count, 1), 200),
        ];

        if ($from) {
            $payload['from'] = $from->toIso8601String();
        }

        if ($to) {
            $payload['to'] = $to->toIso8601String();
        }

        $response = $this->client($instance)->post(
            '/api/chat/history/'.rawurlencode($instance->name),
            $payload
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.history_failed')
        );

        return [
            'messages' => $response->json('messages', []),
            'has_more' => (bool) $response->json('hasMore', false),
            'chat_jid' => $response->json('chat_jid'),
        ];
    }

    public function markChatRead(
        Instance $instance,
        string $recipient,
        bool $read = true
    ): void {
        $response = $this->client($instance)->post(
            '/api/chat/markChatRead/'.rawurlencode($instance->name),
            [
                'number' => $recipient,
                'read' => $read,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.mark_read_failed')
        );
    }

    public function configureWebhook(
        Instance $instance,
        string $url,
        string $authorization
    ): void {
        $response = $this->client($instance)->post(
            '/api/events/webhook/'.rawurlencode($instance->name),
            [
                'label' => 'topweb-chat',
                'enabled' => true,
                'url' => $url,
                'authorization' => $authorization,
                'events' => [
                    'message.exchange',
                    'message.status',
                    'instance.state',
                ],
                'mediaBase64' => false,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_failed')
        );
    }

    private function client(Instance $instance): PendingRequest
    {
        return Http::baseUrl(config('topweb-chat.base_url'))
            ->acceptJson()
            ->asJson()
            ->withHeaders(['token' => $instance->token])
            ->connectTimeout(config('topweb-chat.connect_timeout'))
            ->timeout(config('topweb-chat.timeout'));
    }

    private function ensureSuccessful(
        Response $response,
        string $fallbackMessage,
        bool $nonIdempotent = false
    ): void {
        if ($response->successful() && $response->json('success') !== false) {
            return;
        }

        $status = $response->status();

        $reset = (int) $response->header('X-RateLimit-Reset');
        $retryAfter = $reset > time() ? $reset - time() : $reset;

        throw new ProviderRequestException(
            $fallbackMessage,
            $nonIdempotent && ($status === 408 || $status >= 500),
            $status,
            min(max($retryAfter, 1), 300)
        );
    }
}
