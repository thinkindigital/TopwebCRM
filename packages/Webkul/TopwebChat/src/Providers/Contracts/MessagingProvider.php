<?php

namespace Webkul\TopwebChat\Providers\Contracts;

use Carbon\CarbonInterface;
use Webkul\TopwebChat\Models\Instance;

interface MessagingProvider
{
    // --- Session Management ---
    public function createSession(Instance $instance, array $options = []): array;
    public function startSession(Instance $instance): array;
    public function stopSession(Instance $instance): array;
    public function logoutSession(Instance $instance): array;
    public function forceKillSession(Instance $instance): array;
    public function getSessionStatus(Instance $instance): array;
    public function getQrCode(Instance $instance): array;
    public function getPairingCode(Instance $instance, string $phoneNumber): array;
    public function listSessions(Instance $instance): array;
    public function getSessionConfig(Instance $instance): array;
    public function updateSessionConfig(Instance $instance, array $config): array;

    // --- Messaging ---
    public function sendText(Instance $instance, string $recipient, string $message, array $options = []): array;
    public function sendMedia(Instance $instance, string $recipient, array $mediaData): array;
    public function sendLocation(Instance $instance, string $recipient, float $latitude, float $longitude, array $options = []): array;
    public function sendContact(Instance $instance, string $recipient, string $contactName, string $contactNumber, array $options = []): array;
    public function sendReaction(Instance $instance, string $chatId, string $messageId, string $emoji): array;
    public function editMessage(Instance $instance, string $chatId, string $messageId, string $body): array;
    public function deleteMessage(Instance $instance, string $chatId, string $messageId, bool $forEveryone = true): array;
    public function forwardMessage(Instance $instance, string $fromChatId, string $toChatId, string $messageId): array;
    public function sendSticker(Instance $instance, string $recipient, string $mediaData): array;
    public function sendPoll(Instance $instance, string $recipient, string $name, array $options): array;
    public function sendBulk(Instance $instance, array $messages, array $options = []): array;

    // --- History & Status ---
    public function history(
        Instance $instance,
        string $recipient,
        int $count = 100,
        ?\Carbon\CarbonInterface $from = null,
        ?\Carbon\CarbonInterface $to = null
    ): array;
    public function markChatRead(Instance $instance, string $recipient, bool $read = true): void;
    public function connectionStatus(Instance $instance): string;

    // --- Webhook ---
    public function configureWebhook(
        Instance $instance,
        string $url,
        string $secret,
        array $events = [],
        array $headers = [],
        array $filters = [],
        int $retryCount = 3
    ): void;
    public function testWebhook(Instance $instance, string $webhookId): array;
    public function listWebhooks(Instance $instance): array;
    public function getWebhook(Instance $instance, string $webhookId): array;
    public function updateWebhook(Instance $instance, string $webhookId, array $data): array;
    public function deleteWebhook(Instance $instance, string $webhookId): void;

    // --- Media ---
    public function downloadMedia(Instance $instance, string $chatId, string $messageId): string;

    // --- Contacts ---
    public function checkContact(Instance $instance, string $number): array;
    public function getContact(Instance $instance, string $contactId): array;
    public function getContactPhone(Instance $instance, string $contactId): ?string;

    // --- Groups ---
    public function listGroups(Instance $instance): array;
    public function getGroup(Instance $instance, string $groupId): array;
    public function createGroup(Instance $instance, string $subject, array $participants = []): array;
    public function addParticipants(Instance $instance, string $groupId, array $participants): array;
    public function removeParticipants(Instance $instance, string $groupId, array $participants): array;
    public function promoteParticipants(Instance $instance, string $groupId, array $participants): array;
    public function demoteParticipants(Instance $instance, string $groupId, array $participants): array;
    public function getMembershipRequests(Instance $instance, string $groupId): array;
    public function approveMembershipRequest(Instance $instance, string $groupId, string $participantId): array;
    public function rejectMembershipRequest(Instance $instance, string $groupId, string $participantId): array;

    // --- Calls ---
    public function rejectCall(Instance $instance, string $callId): array;

    // --- Profile ---
    public function updateProfile(Instance $instance, array $data): array;
}
