<?php

namespace Webkul\TopwebChat\Http\Controllers;

use App\Services\SensitiveDataService;
use App\Services\SensitiveFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Webkul\TopwebChat\Jobs\MarkConversationRead;
use Webkul\TopwebChat\Jobs\SyncConversationHistory;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Repositories\ConversationRepository;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\User\Models\User;

class ConversationController
{
    public function __construct(
        protected ConversationRepository $conversationRepository,
        protected ConversationAccessService $access,
        protected MessageService $messages,
        protected SensitiveDataService $sensitiveData,
        protected SensitiveFileService $sensitiveFiles
    ) {}

    public function index(Request $request): View
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox'), 403);

        $user = auth()->guard('user')->user();
        $queue = $request->string('queue', 'mine')->toString();

        if (! in_array($queue, ['mine', 'unassigned', 'all'], true)) {
            $queue = 'mine';
        }

        if (! $this->access->isAdministrator($user) && $queue === 'all') {
            $queue = 'mine';
        }

        return view('topweb_chat::conversations.index', [
            'queue' => $queue,
            'conversations' => $this->conversationRepository
                ->accessibleQuery($user, $queue)
                ->paginate(30)
                ->withQueryString(),
            'selectedConversation' => null,
        ]);
    }

    public function show(Conversation $conversation): View
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $conversation->load([
            'person',
            'lead',
            'assignedUser',
            'instance',
            'messages' => fn ($query) => $query
                ->orderByRaw('COALESCE(sent_at, created_at) DESC')
                ->orderByDesc('id')
                ->limit(100),
            'internalNotes' => fn ($query) => $query->with('user')->latest()->limit(100),
        ]);

        $conversation->setRelation(
            'messages',
            $conversation->messages->reverse()->values()
        );

        if ($conversation->instance?->enabled) {
            try {
                Bus::chain([
                    new SyncConversationHistory($conversation->id, true),
                    new MarkConversationRead($conversation->id),
                ])->dispatch();
            } catch (Throwable $exception) {
                Cache::put(
                    "topweb-chat:provider-unavailable:{$conversation->instance_id}",
                    true,
                    now()->addMinutes(5)
                );

                Log::warning('OpenWA background synchronization failed while loading a conversation.', [
                    'conversation_id' => $conversation->id,
                    'instance_id' => $conversation->instance_id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return view('topweb_chat::conversations.show', [
            'conversation' => $conversation,
            'historyUnavailable' => Cache::has(
                "topweb-chat:history-unavailable:{$conversation->instance_id}"
            ),
            'readUnavailable' => Cache::has(
                "topweb-chat:read-unavailable:{$conversation->instance_id}"
            ),
            'providerUnavailable' => Cache::has(
                "topweb-chat:provider-unavailable:{$conversation->instance_id}"
            ),
            'pipelineStages' => $conversation->lead
                ? $conversation->lead->pipeline->stages
                : collect(),
            'assignableUsers' => $this->access->isAdministrator($user)
                ? User::query()->where('status', 1)->orderBy('name')->get()
                : collect(),
            'canViewSensitiveMedia' => $this->sensitiveData->canView($user),
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $this->access->authorizeView(
            auth()->guard('user')->user(),
            $conversation
        );

        $user = auth()->guard('user')->user();
        $canViewSensitiveMedia = $this->sensitiveData->canView($user);
        $messages = $conversation->messages()
            ->orderByRaw('COALESCE(sent_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'type' => $message->type,
                'content' => $message->content,
                'status' => $message->status,
                'sent_at' => ($message->sent_at ?? $message->created_at)
                    ?->toIso8601String(),
                'can_retry' => $this->messages->canRetry($message),
                'retry_url' => route('admin.topweb_chat.messages.retry', [
                    'conversation' => $conversation,
                    'message' => $message,
                ]),
                'has_media' => $message->hasMedia(),
                'media_status' => $message->hasMedia()
                    ? data_get($message->metadata, 'media_status', 'queued')
                    : null,
                'media_mime' => $canViewSensitiveMedia && $message->mediaIsStored()
                    ? data_get($message->metadata, 'media_mime')
                    : null,
                'media_url' => $canViewSensitiveMedia && $message->mediaIsStored()
                    ? route('admin.topweb_chat.messages.media', [
                        'conversation' => $conversation,
                        'message' => $message,
                    ])
                    : null,
            ]);

        return response()->json([
            'messages' => $messages,
            'instance' => [
                'status' => $conversation->instance()->value('status') ?: 'unknown',
            ],
        ]);
    }

    public function clientEvent(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $validated = $request->validate([
            'level' => ['required', 'in:info,warning,error'],
            'event' => ['required', 'string', 'max:80'],
            'context' => ['nullable', 'array'],
        ]);

        $allowedContext = collect($validated['context'] ?? [])->only([
            'payload_last_id',
            'dom_last_id',
            'timeline_connected',
            'form_connected',
            'scroll_top',
            'scroll_height',
            'client_height',
            'message',
        ])->all();

        Log::channel('topweb_chat_client')->log(
            $validated['level'],
            $validated['event'],
            array_merge($allowedContext, [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            ])
        );

        return response()->json(status: 202);
    }

    public function media(
        Conversation $conversation,
        Message $message
    ): StreamedResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);
        $this->sensitiveData->authorize($user);

        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->mediaIsStored(), 404);

        $metadata = $message->metadata ?? [];

        return $this->sensitiveFiles->inline(
            (string) data_get($metadata, 'media_path'),
            (string) data_get($metadata, 'media_mime', 'application/octet-stream'),
            (string) data_get($metadata, 'media_name', 'whatsapp-media.bin')
        );
    }
}
