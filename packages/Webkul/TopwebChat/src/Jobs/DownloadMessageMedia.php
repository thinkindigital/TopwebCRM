<?php

namespace Webkul\TopwebChat\Jobs;

use App\Services\SensitiveFileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Instance;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Providers\Contracts\MessagingProvider;

class DownloadMessageMedia implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public array $backoff = [15, 60, 180];

    public function __construct(public int $messageId) {}

    public function handle(
        MessagingProvider $provider,
        SensitiveFileService $sensitiveFiles
    ): void {
        $message = Message::query()->findOrFail($this->messageId);

        if (! $message->hasMedia() || $message->mediaIsStored()) {
            return;
        }

        $conversation = Conversation::query()->findOrFail($message->conversation_id);
        $instance = Instance::query()->findOrFail($conversation->instance_id);

        $metadata = $message->metadata ?? [];
        $chatId = data_get($metadata, 'chat_id')
            ?: data_get($metadata, 'chat_jid')
            ?: $conversation->remote_jid;
        $providerMessageId = $message->provider_message_id;

        if (! $chatId || ! $providerMessageId) {
            $this->updateStatus($message, 'unavailable');

            return;
        }

        $this->updateStatus($message, 'downloading');
        $contents = $provider->downloadMedia(
            $instance,
            $chatId,
            $providerMessageId
        );
        $size = strlen($contents);
        $maximumSize = config('topweb-chat.openwa.media_max_bytes', 52428800);

        if ($size === 0 || $size > $maximumSize) {
            throw new RuntimeException('Provider media is empty or exceeds the configured limit.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents)
            ?: 'application/octet-stream';
        $extension = $this->extensionFor($mime);
        $path = sprintf(
            'topweb-chat/%d/%s.%s',
            $message->conversation_id,
            $message->provider_message_key ?: hash('sha256', $providerMessageId),
            $extension
        );

        $sensitiveFiles->put($path, $contents);

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                'has_media' => true,
                'media_status' => 'stored',
                'media_path' => $path,
                'media_mime' => $mime,
                'media_size' => $size,
                'media_name' => 'whatsapp-'.$message->id.'.'.$extension,
            ]),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message && ! $message->mediaIsStored()) {
            $this->updateStatus($message, 'failed');
        }
    }

    private function updateStatus(Message $message, string $status): void
    {
        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                'has_media' => true,
                'media_status' => $status,
            ]),
        ]);
    }

    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            default => 'bin',
        };
    }
}
