<?php

namespace Webkul\TopwebChat\Providers;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Webkul\TopwebChat\Exceptions\ProviderRequestException;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

class OpenWaProvider implements MessagingProvider
{
    // --- Session Management ---

    public function createSession(Instance $instance, array $options = []): array
    {
        $payload = [
            'name' => $options['name'] ?? $instance->name,
            'config' => $options['config'] ?? [],
            'proxyUrl' => $options['proxyUrl'] ?? null,
            'proxyType' => $options['proxyType'] ?? null,
        ];

        $response = $this->client($instance)->post('/api/sessions', $payload);

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_create_failed')
        );

        return $response->json();
    }

    public function startSession(Instance $instance): array
    {
        $response = $this->client($instance)->post("/api/sessions/{$instance->session_uuid}/start");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_start_failed')
        );

        return $response->json();
    }

    public function stopSession(Instance $instance): array
    {
        $response = $this->client($instance)->post("/api/sessions/{$instance->session_uuid}/stop");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_stop_failed')
        );

        return $response->json();
    }

    public function logoutSession(Instance $instance): array
    {
        $response = $this->client($instance)->post("/api/sessions/{$instance->session_uuid}/logout");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_logout_failed')
        );

        return $response->json();
    }

    public function forceKillSession(Instance $instance): array
    {
        $response = $this->client($instance)->post("/api/sessions/{$instance->session_uuid}/force-kill");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_force_kill_failed')
        );

        return $response->json();
    }

    public function getSessionStatus(Instance $instance): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.instance_status_failed')
        );

        $data = $response->json();
        $instance->update([
            'status' => $data['status'] ?? 'unknown',
            'engine_loaded' => $data['engineLoaded'] ?? false,
            'restriction' => $data['restriction'] ?? null,
            'last_synced_at' => now(),
        ]);

        return $data;
    }

    public function getQrCode(Instance $instance): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/qr");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.qr_failed')
        );

        return $response->json();
    }

    public function getPairingCode(Instance $instance, string $phoneNumber): array
    {
        $response = $this->client($instance)->post("/api/sessions/{$instance->session_uuid}/pairing-code", [
            'phoneNumber' => $phoneNumber,
        ]);

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.pairing_code_failed')
        );

        return $response->json();
    }

    public function listSessions(Instance $instance): array
    {
        $response = $this->client($instance)->get('/api/sessions', [
            'limit' => 1000,
        ]);

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_list_failed')
        );

        return $response->json();
    }

    public function getSessionConfig(Instance $instance): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/config");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_config_failed')
        );

        return $response->json();
    }

    public function updateSessionConfig(Instance $instance, array $config): array
    {
        $response = $this->client($instance)->patch("/api/sessions/{$instance->session_uuid}/config", $config);

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.session_config_update_failed')
        );

        return $response->json();
    }

    // --- Messaging ---

    public function sendText(Instance $instance, string $recipient, string $message, array $options = []): array
    {
        $payload = [
            'chatId' => $recipient,
            'text' => $message,
        ];

        if (isset($options['mentions'])) {
            $payload['mentions'] = $options['mentions'];
        }
        if (isset($options['linkPreview'])) {
            $payload['linkPreview'] = $options['linkPreview'];
        }
        if (isset($options['customLinkPreview'])) {
            $payload['customLinkPreview'] = $options['customLinkPreview'];
        }
        if (isset($options['quotedMessageId'])) {
            $payload['quotedMessageId'] = $options['quotedMessageId'];
        }

        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/send-text",
            $payload
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.send_failed')
        );

        return $response->json();
    }

    public function sendMedia(Instance $instance, string $recipient, array $mediaData): array
    {
        $payload = [
            'chatId' => $recipient,
        ];

        if (isset($mediaData['url'])) {
            $payload['url'] = $mediaData['url'];
        } elseif (isset($mediaData['base64'])) {
            $payload['base64'] = $mediaData['base64'];
            $payload['mimetype'] = $mediaData['mimetype'] ?? 'application/octet-stream';
        }

        if (isset($mediaData['filename'])) {
            $payload['filename'] = $mediaData['filename'];
        }
        if (isset($mediaData['caption'])) {
            $payload['caption'] = $mediaData['caption'];
        }
        if (isset($mediaData['mentions'])) {
            $payload['mentions'] = $mediaData['mentions'];
        }
        if (isset($mediaData['quotedMessageId'])) {
            $payload['quotedMessageId'] = $mediaData['quotedMessageId'];
        }

        // Determine endpoint based on mimetype
        $mimetype = $mediaData['mimetype'] ?? '';
        $endpoint = match (true) {
            str_starts_with($mimetype, 'image/') => 'send-image',
            str_starts_with($mimetype, 'video/') => 'send-video',
            str_starts_with($mimetype, 'audio/') => 'send-audio',
            str_starts_with($mimetype, 'application/') => 'send-document',
            $mimetype === 'image/webp' => 'send-sticker',
            default => 'send-document',
        };

        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/send-{$endpoint}",
            $payload
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.send_media_failed')
        );

        return $response->json();
    }

    public function sendLocation(Instance $instance, string $recipient, float $latitude, float $longitude, array $options = []): array
    {
        $payload = [
            'chatId' => $recipient,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        if (isset($options['description'])) {
            $payload['description'] = $options['description'];
        }
        if (isset($options['address'])) {
            $payload['address'] = $options['address'];
        }

        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/send-location",
            $payload
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.send_location_failed')
        );

        return $response->json();
    }

    public function sendContact(Instance $instance, string $recipient, string $contactName, string $contactNumber, array $options = []): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/send-contact",
            [
                'chatId' => $recipient,
                'contactName' => $contactName,
                'contactNumber' => $contactNumber,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.send_contact_failed')
        );

        return $response->json();
    }

    public function sendReaction(Instance $instance, string $chatId, string $messageId, string $emoji): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/react",
            [
                'chatId' => $chatId,
                'messageId' => $messageId,
                'emoji' => $emoji,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.reaction_failed')
        );

        return $response->json();
    }

    public function editMessage(Instance $instance, string $chatId, string $messageId, string $body): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/edit",
            [
                'chatId' => $chatId,
                'messageId' => $messageId,
                'body' => $body,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.edit_failed')
        );

        return $response->json();
    }

    public function deleteMessage(Instance $instance, string $chatId, string $messageId, bool $forEveryone = true): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/delete",
            [
                'chatId' => $chatId,
                'messageId' => $messageId,
                'forEveryone' => $forEveryone,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.delete_failed')
        );

        return $response->json();
    }

    public function forwardMessage(Instance $instance, string $fromChatId, string $toChatId, string $messageId): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/forward",
            [
                'fromChatId' => $fromChatId,
                'toChatId' => $toChatId,
                'messageId' => $messageId,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.forward_failed')
        );

        return $response->json();
    }

    public function sendSticker(Instance $instance, string $recipient, string $mediaData): array
    {
        // Sticker is just a webp image
        return $this->sendMedia($instance, $recipient, [
            'mimetype' => 'image/webp',
            'base64' => $mediaData,
            'filename' => 'sticker.webp',
        ]);
    }

    public function sendPoll(Instance $instance, string $recipient, string $name, array $options): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/send-poll",
            [
                'chatId' => $recipient,
                'name' => $name,
                'options' => $options,
                'allowMultipleAnswers' => false,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.poll_failed')
        );

        return $response->json();
    }

    public function sendBulk(Instance $instance, array $messages, array $options = []): array
    {
        $payload = [
            'messages' => $messages,
            'options' => $options ?? [],
        ];

        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/messages/send-bulk",
            $payload
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.bulk_failed')
        );

        return $response->json();
    }

    // --- History & Status ---

    public function history(
        Instance $instance,
        string $recipient,
        int $count = 100,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null
    ): array {
        $query = [
            'limit' => min(max($count, 1), 200),
        ];

        if ($from) {
            $query['from'] = $from->toIso8601String();
        }
        if ($to) {
            $query['to'] = $to->toIso8601String();
        }

        $response = $this->client($instance)->get(
            "/api/sessions/{$instance->session_uuid}/messages/{$recipient}/history",
            $query
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.history_failed')
        );

        return [
            'messages' => $response->json('messages', []),
            'has_more' => $response->json('hasMore', false),
            'chat_jid' => $response->json('chat_jid', $recipient),
        ];
    }

    public function markChatRead(Instance $instance, string $recipient, bool $read = true): void
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/chats/{$recipient}/read",
            ['read' => $read]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.mark_read_failed')
        );
    }

    public function connectionStatus(Instance $instance): string
    {
        $data = $this->getSessionStatus($instance);
        return $data['status'] ?? 'unknown';
    }

    // --- Webhook ---

    public function configureWebhook(
        Instance $instance,
        string $url,
        string $secret,
        array $events = [],
        array $headers = [],
        array $filters = [],
        int $retryCount = 3
    ): void {
        $payload = [
            'url' => $url,
            'secret' => $secret,
            'events' => $events ?: config('topweb-chat.openwa.webhook_events', [
                'message.received',
                'message.sent',
                'message.ack',
                'message.failed',
                'message.revoked',
                'message.reaction',
                'message.edited',
                'session.status',
                'session.authenticated',
                'session.disconnected',
                'session.reconnect_loop',
                'session.restriction',
                'group.join',
                'group.leave',
                'group.update',
                'group.join_request',
                'call.received',
                'call.accepted',
                'call.rejected',
                'call.missed',
                'status.received',
            ]),
            'headers' => $headers,
            'filters' => $filters,
            'retryCount' => $retryCount,
        ];

        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/webhooks",
            $payload
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_failed')
        );
    }

    public function testWebhook(Instance $instance, string $webhookId): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/webhooks/{$webhookId}/test"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_test_failed')
        );

        return $response->json();
    }

    public function listWebhooks(Instance $instance): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/webhooks");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_list_failed')
        );

        return $response->json();
    }

    public function getWebhook(Instance $instance, string $webhookId): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/webhooks/{$webhookId}");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_get_failed')
        );

        return $response->json();
    }

    public function updateWebhook(Instance $instance, string $webhookId, array $data): array
    {
        $response = $this->client($instance)->put(
            "/api/sessions/{$instance->session_uuid}/webhooks/{$webhookId}",
            $data
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_update_failed')
        );

        return $response->json();
    }

    public function deleteWebhook(Instance $instance, string $webhookId): void
    {
        $response = $this->client($instance)->delete(
            "/api/sessions/{$instance->session_uuid}/webhooks/{$webhookId}"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.webhook_delete_failed')
        );
    }

    // --- Media ---

    public function downloadMedia(Instance $instance, string $chatId, string $messageId): string
    {
        $response = $this->client($instance)->get(
            "/api/sessions/{$instance->session_uuid}/messages/{$chatId}/{$messageId}/media",
            [],
            ['Accept' => '*/*']
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.media_download_failed')
        );

        return $response->body();
    }

    // --- Contacts ---

    public function checkContact(Instance $instance, string $number): array
    {
        $response = $this->client($instance)->get(
            "/api/sessions/{$instance->session_uuid}/contacts/check/{$number}"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.contact_check_failed')
        );

        return $response->json();
    }

    public function getContact(Instance $instance, string $contactId): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/contacts/{$contactId}");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.contact_get_failed')
        );

        return $response->json();
    }

    public function getContactPhone(Instance $instance, string $contactId): ?string
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/contacts/{$contactId}/phone");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.contact_phone_failed')
        );

        return $response->json('phone') ?? null;
    }

    // --- Groups ---

    public function listGroups(Instance $instance): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/groups");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.groups_list_failed')
        );

        return $response->json();
    }

    public function getGroup(Instance $instance, string $groupId): array
    {
        $response = $this->client($instance)->get("/api/sessions/{$instance->session_uuid}/groups/{$groupId}");

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_get_failed')
        );

        return $response->json();
    }

    public function createGroup(Instance $instance, string $subject, array $participants = []): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups",
            [
                'subject' => $subject,
                'participants' => $participants,
            ]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_create_failed')
        );

        return $response->json();
    }

    public function addParticipants(Instance $instance, string $groupId, array $participants): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/participants",
            ['participants' => $participants]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_add_participants_failed')
        );

        return $response->json();
    }

    public function removeParticipants(Instance $instance, string $groupId, array $participants): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/participants/remove",
            ['participants' => $participants]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_remove_participants_failed')
        );

        return $response->json();
    }

    public function promoteParticipants(Instance $instance, string $groupId, array $participants): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/participants/promote",
            ['participants' => $participants]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_promote_failed')
        );

        return $response->json();
    }

    public function demoteParticipants(Instance $instance, string $groupId, array $participants): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/participants/demote",
            ['participants' => $participants]
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_demote_failed')
        );

        return $response->json();
    }

    public function getMembershipRequests(Instance $instance, string $groupId): array
    {
        $response = $this->client($instance)->get(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/membership-requests"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_requests_failed')
        );

        return $response->json();
    }

    public function approveMembershipRequest(Instance $instance, string $groupId, string $participantId): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/membership-requests/{$participantId}/approve"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_approve_failed')
        );

        return $response->json();
    }

    public function rejectMembershipRequest(Instance $instance, string $groupId, string $participantId): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/groups/{$groupId}/membership-requests/{$participantId}/reject"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.group_reject_failed')
        );

        return $response->json();
    }

    // --- Calls ---

    public function rejectCall(Instance $instance, string $callId): array
    {
        $response = $this->client($instance)->post(
            "/api/sessions/{$instance->session_uuid}/calls/{$callId}/reject"
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.call_reject_failed')
        );

        return $response->json();
    }

    // --- Profile ---

    public function updateProfile(Instance $instance, array $data): array
    {
        $response = $this->client($instance)->put(
            "/api/sessions/{$instance->session_uuid}/profile",
            $data
        );

        $this->ensureSuccessful(
            $response,
            trans('topweb_chat::app.provider.profile_update_failed')
        );

        return $response->json();
    }

    // --- Helpers ---

    private function client(Instance $instance): PendingRequest
    {
        return Http::baseUrl(rtrim($instance->getBaseUrl(), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-Key' => $instance->getApiKey(),
            ])
            ->connectTimeout(config('topweb-chat.connect_timeout', 10))
            ->timeout(config('topweb-chat.timeout', 30));
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
        $reset = (int) $response->header('Retry-After') ?: (int) $response->header('X-RateLimit-Reset');
        $retryAfter = $reset > time() ? $reset - time() : $reset;

        throw new ProviderRequestException(
            $fallbackMessage,
            $nonIdempotent && ($status === 408 || $status >= 500),
            $status,
            min(max($retryAfter, 1), 300)
        );
    }
}