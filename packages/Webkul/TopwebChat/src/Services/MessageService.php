<?php

namespace Webkul\TopwebChat\Services;

use App\Services\SensitiveFileService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Webkul\TopwebChat\Jobs\SendMessage;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\User\Models\User;

class MessageService
{
    public const OUTBOUND_MEDIA_MIMES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'audio' => ['audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4', 'audio/aac'],
        'video' => ['video/mp4', 'video/quicktime', 'video/webm'],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ];

    public function __construct(
        protected ConversationAccessService $access,
        protected AttendanceService $attendances,
        protected SensitiveFileService $sensitiveFiles
    ) {}

    public function queueText(
        Conversation $conversation,
        User $user,
        string $content,
        string $operationKey
    ): Message {
        $message = DB::transaction(function () use (
            $conversation,
            $user,
            $content,
            $operationKey
        ) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($lockedConversation->assigned_user_id === null) {
                $leadOwnerId = $lockedConversation->lead_id !== null
                    ? $lockedConversation->lead?->user_id
                    : null;

                // D04: com Lead, so o dono assume; a projecao espelha o dono.
                if ($lockedConversation->lead_id !== null
                    && ! $this->access->isAdministrator($user)
                    && ($leadOwnerId === null || (int) $leadOwnerId !== (int) $user->id)
                ) {
                    throw new AuthorizationException;
                }

                $lockedConversation->update([
                    'assigned_user_id' => $lockedConversation->lead_id !== null
                        ? $leadOwnerId
                        : $user->id,
                ]);
            } elseif (
                $lockedConversation->assigned_user_id !== $user->id
                && ! $this->access->isAdministrator($user)
            ) {
                throw new AuthorizationException;
            }

            $existingMessage = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->where('operation_key', $operationKey)
                ->first();

            if ($existingMessage) {
                return $existingMessage;
            }

            $instance = $lockedConversation->instance()->first();

            if (! $instance?->enabled || $instance->status !== 'ready') {
                throw new DomainException(
                    trans('topweb_chat::app.messages.instance_not_connected')
                );
            }

            return Message::query()->create([
                'conversation_id' => $lockedConversation->id,
                'user_id' => $user->id,
                'operation_key' => $operationKey,
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => $content,
                'status' => 'queued',
                'source' => 'topweb_chat',
            ]);
        });

        $wasRecentlyCreated = $message->wasRecentlyCreated;

        $this->attendances->recordHumanOutbound($message);

        if ($wasRecentlyCreated) {
            SendMessage::dispatch($message->id);
        }

        return $message->fresh();
    }

    public static function outboundMediaType(string $mime): ?string
    {
        foreach (self::OUTBOUND_MEDIA_MIMES as $type => $mimes) {
            if (in_array(strtolower($mime), $mimes, true)) {
                return $type;
            }
        }

        return null;
    }

    public function queueMedia(
        Conversation $conversation,
        User $user,
        UploadedFile $file,
        ?string $caption,
        string $operationKey
    ): Message {
        $maximumBytes = (int) config('topweb-chat.openwa.media_max_bytes', 104858624);

        if ($file->getSize() === false || $file->getSize() > $maximumBytes) {
            throw new DomainException(
                trans('topweb_chat::app.messages.media_too_large')
            );
        }

        $type = self::outboundMediaType((string) $file->getMimeType());

        if ($type === null) {
            throw new DomainException(
                trans('topweb_chat::app.messages.media_type_not_supported')
            );
        }

        $message = DB::transaction(function () use (
            $conversation,
            $user,
            $file,
            $caption,
            $type,
            $operationKey
        ) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            if ($lockedConversation->assigned_user_id === null) {
                $leadOwnerId = $lockedConversation->lead_id !== null
                    ? $lockedConversation->lead?->user_id
                    : null;

                // D04: com Lead, so o dono assume; a projecao espelha o dono.
                if ($lockedConversation->lead_id !== null
                    && ! $this->access->isAdministrator($user)
                    && ($leadOwnerId === null || (int) $leadOwnerId !== (int) $user->id)
                ) {
                    throw new AuthorizationException;
                }

                $lockedConversation->update([
                    'assigned_user_id' => $lockedConversation->lead_id !== null
                        ? $leadOwnerId
                        : $user->id,
                ]);
            } elseif (
                $lockedConversation->assigned_user_id !== $user->id
                && ! $this->access->isAdministrator($user)
            ) {
                throw new AuthorizationException;
            }

            $existingMessage = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->where('operation_key', $operationKey)
                ->first();

            if ($existingMessage) {
                return $existingMessage;
            }

            $instance = $lockedConversation->instance()->first();

            if (! $instance?->enabled || $instance->status !== 'ready') {
                throw new DomainException(
                    trans('topweb_chat::app.messages.instance_not_connected')
                );
            }

            $path = $this->sensitiveFiles->store($file, 'topweb-chat/outbound');

            return Message::query()->create([
                'conversation_id' => $lockedConversation->id,
                'user_id' => $user->id,
                'operation_key' => $operationKey,
                'direction' => 'outgoing',
                'type' => $type,
                'content' => $caption,
                'status' => 'queued',
                'source' => 'topweb_chat',
                'metadata' => [
                    'has_media' => true,
                    'media_status' => 'stored',
                    'media_path' => $path,
                    'media_mime' => $file->getMimeType(),
                    'media_original_name' => $file->getClientOriginalName(),
                ],
            ]);
        });

        $wasRecentlyCreated = $message->wasRecentlyCreated;

        $this->attendances->recordHumanOutbound($message);

        if ($wasRecentlyCreated) {
            SendMessage::dispatch($message->id);
        }

        return $message->fresh();
    }

    public static function validateBatchFile(UploadedFile $file): ?string
    {
        $maximumBytes = (int) config('topweb-chat.openwa.media_max_bytes', 104858624);

        if ($file->getSize() === false || $file->getSize() > $maximumBytes) {
            return 'too_large';
        }

        if (self::outboundMediaType((string) $file->getMimeType()) === null) {
            return 'type_not_supported';
        }

        return null;
    }

    /**
     * Enfileira um lote de anexos: uma Message por arquivo, cada uma com seu
     * operation_key, mais no máximo uma Message de texto separada (nunca a
     * mesma legenda duplicada). Falha parcial por item; canal indisponível
     * rejeita o lote inteiro antes de persistir qualquer item novo.
     *
     * @param  array<int, array{file: UploadedFile, operation_key: string}>  $items
     * @param  array{content: string, operation_key: string}|null  $text
     * @return array{messages: array, rejected: array, text_message_id: ?int}
     */
    public function queueBatch(
        Conversation $conversation,
        User $user,
        array $items,
        ?array $text = null
    ): array {
        $instance = $conversation->instance()->first();

        if (! $instance?->enabled || $instance->status !== 'ready') {
            throw new DomainException(
                trans('topweb_chat::app.messages.instance_not_connected')
            );
        }

        $accepted = [];
        $rejected = [];

        foreach (array_values($items) as $index => $item) {
            $error = self::validateBatchFile($item['file']);

            if ($error !== null) {
                $rejected[] = [
                    'index' => $index,
                    'operation_key' => $item['operation_key'],
                    'error_code' => $error,
                ];

                continue;
            }

            $duplicate = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('operation_key', $item['operation_key'])
                ->exists();

            try {
                $message = $this->queueMedia(
                    $conversation,
                    $user,
                    $item['file'],
                    null,
                    $item['operation_key']
                );
            } catch (DomainException $exception) {
                $rejected[] = [
                    'index' => $index,
                    'operation_key' => $item['operation_key'],
                    'error_code' => 'rejected',
                ];

                continue;
            }

            $accepted[] = [
                'index' => $index,
                'operation_key' => $item['operation_key'],
                'message_id' => $message->id,
                'duplicate' => $duplicate,
            ];
        }

        $textMessageId = null;

        if ($text !== null) {
            $textMessage = $this->queueText(
                $conversation,
                $user,
                $text['content'],
                $text['operation_key']
            );
            $textMessageId = $textMessage->id;
        }

        return [
            'messages' => $accepted,
            'rejected' => $rejected,
            'text_message_id' => $textMessageId,
        ];
    }

    public function retry(Message $message, Conversation $conversation, User $user): Message
    {
        $retryMessage = DB::transaction(function () use ($message, $conversation, $user) {
            $lockedConversation = Conversation::query()
                ->lockForUpdate()
                ->findOrFail($conversation->id);

            $this->access->authorizeView($user, $lockedConversation);

            $lockedMessage = Message::query()
                ->where('conversation_id', $lockedConversation->id)
                ->lockForUpdate()
                ->findOrFail($message->id);

            if (! $this->canRetry($lockedMessage)) {
                throw new DomainException(
                    trans('topweb_chat::app.messages.retry_not_available')
                );
            }

            $instance = $lockedConversation->instance()->first();

            if (! $instance?->enabled || $instance->status !== 'ready') {
                throw new DomainException(
                    trans('topweb_chat::app.messages.instance_not_connected')
                );
            }

            $lockedMessage->update([
                'status' => 'queued',
                'failed_at' => null,
                'last_error' => null,
            ]);

            return $lockedMessage;
        });

        SendMessage::dispatch($retryMessage->id);

        return $retryMessage->fresh();
    }

    public function canRetry(Message $message): bool
    {
        return $message->direction === 'outgoing'
            && $message->status === 'failed'
            && in_array($message->last_error, [
                'provider_instance_not_connected',
                'media_file_missing',
            ], true)
            && $message->provider_message_id === null;
    }
}
