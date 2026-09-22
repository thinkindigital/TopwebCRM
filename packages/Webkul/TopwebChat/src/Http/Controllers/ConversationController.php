<?php

namespace Webkul\TopwebChat\Http\Controllers;

use App\Services\SensitiveDataService;
use App\Services\SensitiveFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Pipeline;
use Webkul\TopwebChat\Jobs\MarkConversationRead;
use Webkul\TopwebChat\Jobs\SyncConversationHistory;
use Webkul\TopwebChat\Models\Conversation;
use Webkul\TopwebChat\Models\Message;
use Webkul\TopwebChat\Repositories\ConversationRepository;
use Webkul\TopwebChat\Services\ConversationAccessService;
use Webkul\TopwebChat\Services\InboundLeadAssociationService;
use Webkul\TopwebChat\Services\MessageService;
use Webkul\TopwebChat\Services\NextActionService;
use Webkul\TopwebChat\Support\TopwebChatError;
use Webkul\User\Models\User;

class ConversationController
{
    public function __construct(
        protected ConversationRepository $conversationRepository,
        protected ConversationAccessService $access,
        protected MessageService $messages,
        protected SensitiveDataService $sensitiveData,
        protected SensitiveFileService $sensitiveFiles,
        protected NextActionService $nextActions,
        protected InboundLeadAssociationService $inboundLeadAssociation
    ) {}

    public function index(Request $request): View
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox'), 403);

        $user = auth()->guard('user')->user();

        return view('topweb_chat::conversations.index', $this->queueData($request, $user) + [
            'selectedConversation' => null,
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $this->loadConversation($conversation);
        $this->dispatchHistorySync($conversation);

        $viewData = $this->contextData($conversation, $user) + [
            'workspaceStyle' => $this->workspaceStyle(),
        ];

        return view('topweb_chat::conversations.show', $viewData + $this->queueData(
            $request,
            $user,
            bouncer()->hasPermission('topweb_chat.inbox')
        ) + [
            'selectedConversation' => $conversation,
        ]);
    }

    public function context(Request $request, Conversation $conversation): Response
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();
        $this->access->authorizeView($user, $conversation);

        $this->loadConversation($conversation);

        return response()->view(
            'topweb_chat::conversations.partials.crm-context',
            $this->contextData($conversation, $user)
        );
    }

    private function loadConversation(Conversation $conversation): void
    {
        $conversation->load([
            'person',
            'lead.pipeline',
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
    }

    private function dispatchHistorySync(Conversation $conversation): void
    {
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
    }

    /**
     * Dados do painel de contexto; mesma fonte para o show e o fragmento.
     */
    private function contextData(Conversation $conversation, User $user): array
    {
        $isAdministrator = $this->access->isAdministrator($user);
        $canTransferLead = $conversation->lead
            && bouncer()->hasPermission('topweb_chat.inbox.assign')
            && bouncer()->hasPermission('leads.edit')
            && $this->access->canAccessLead($user, $conversation->lead);

        return [
            'conversation' => $conversation,
            'user' => $user,
            'isAdmin' => $isAdministrator,
            'canReleaseConversation' => $conversation->assigned_user_id
                && ($isAdministrator || (int) $conversation->assigned_user_id === (int) $user->id),
            'remoteId' => $this->sensitiveData->canView()
                ? $conversation->remote_jid
                : $this->sensitiveData->maskPhone($conversation->remote_jid),
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
            'leadPipelines' => $conversation->lead
                ? Pipeline::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canTransferLead' => $canTransferLead,
            'leadCandidates' => $conversation->lead
                ? collect()
                : ($conversation->person
                    ? $this->inboundLeadAssociation->operationalLeads($conversation->person)
                        ->filter(fn ($lead) => $lead->user_id === null
                            || $this->access->canAccessLead($user, $lead))
                    : collect()),
            'assignableUsers' => ($isAdministrator || $canTransferLead)
                ? User::query()->where('status', 1)->orderBy('name')->get()
                : collect(),
            'canViewSensitiveMedia' => $this->sensitiveData->canView($user),
            'canViewNotes' => bouncer()->hasPermission('topweb_chat.inbox.notes'),
            'nextAction' => $conversation->lead_id
                ? $this->nextActions->envelope(
                    $this->nextActions->nextForLead($conversation->lead_id),
                    $user,
                    $isAdministrator
                )
                : null,
            'recentActions' => $this->nextActions->recentEnvelopes(
                $conversation->lead_id,
                $user,
                $isAdministrator
            ),
        ];
    }

    private function queueData(Request $request, User $user, bool $queueAvailable = true): array
    {
        $queue = $request->string('queue', 'mine')->toString();

        if (! in_array($queue, ['mine', 'unassigned', 'waiting', 'all'], true)) {
            $queue = 'mine';
        }

        $isAdministrator = $this->access->isAdministrator($user);

        if (! $isAdministrator && $queue === 'all') {
            $queue = 'mine';
        }

        if (! $queueAvailable) {
            return [
                'queue' => $queue,
                'queueAvailable' => false,
                'queueConversations' => collect(),
                'nextActionFlags' => [],
                'queueCounts' => ['mine' => 0, 'unassigned' => 0, 'waiting' => 0, 'all' => 0],
                'workspaceStyle' => $this->workspaceStyle(),
            ];
        }

        $conversations = $this->conversationRepository
            ->accessibleQuery($user, $queue)
            ->paginate(30)
            ->withQueryString();

        return [
            'queue' => $queue,
            'queueAvailable' => true,
            'queueConversations' => $conversations,
            'nextActionFlags' => $this->nextActions->flagsForLeadIds(
                $conversations->getCollection()->pluck('lead_id')->filter()->all()
            ),
            'queueCounts' => [
                'mine' => $this->conversationRepository->accessibleQuery($user, 'mine')->count(),
                'unassigned' => $this->conversationRepository->accessibleQuery($user, 'unassigned')->count(),
                'waiting' => $this->conversationRepository->accessibleQuery($user, 'waiting')->count(),
                'all' => $isAdministrator
                    ? $this->conversationRepository->accessibleQuery($user, 'all')->count()
                    : 0,
            ],
            'workspaceStyle' => $this->workspaceStyle(),
        ];
    }

    private function workspaceStyle(): string
    {
        // S7: configuração administrativa persistida > ENV > R1K.
        // Persistido ausente ou inválido equivale a ausente (cai no ENV).
        $style = strtoupper((string) (core()->getConfigData('topwebchat.appearance.style.workspace_style') ?? ''));

        if (! in_array($style, ['R1', 'R1K'], true)) {
            $style = strtoupper((string) config('topweb-chat.workspace_style', 'R1K'));
        }

        return in_array($style, ['R1', 'R1K'], true) ? $style : 'R1K';
    }

    public function messages(Request $request, Conversation $conversation): Response
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $this->access->authorizeView(
            auth()->guard('user')->user(),
            $conversation
        );

        $user = auth()->guard('user')->user();
        $canViewSensitiveMedia = $this->sensitiveData->canView($user);

        // E-03: fragmento do servidor como representação canônica da timeline.
        if ($request->string('fragment')->toString() === 'timeline') {
            $conversation->load([
                'messages' => fn ($query) => $query
                    ->orderByRaw('COALESCE(sent_at, created_at) DESC')
                    ->orderByDesc('id')
                    ->limit(100),
                'internalNotes' => fn ($query) => $query->with('user')->orderBy('id'),
            ]);
            $conversation->setRelation('messages', $conversation->messages->reverse()->values());

            return response()->view('topweb_chat::conversations.partials.timeline-messages', [
                'conversation' => $conversation,
                'canViewSensitiveMedia' => $canViewSensitiveMedia,
                'canViewNotes' => bouncer()->hasPermission('topweb_chat.inbox.notes'),
            ]);
        }
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
                'error' => TopwebChatError::envelope(
                    $message->error_code ?: $message->last_error,
                    $message->trace_id
                ),
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
            'event' => ['required', Rule::in([
                'client.initialized',
                'client.send_failed',
                'client.refresh_failed',
            ])],
            'error_code' => ['nullable', Rule::in(TopwebChatError::codes())],
            'trace_id' => ['nullable', 'string', 'size:26', 'regex:/^[0-9A-HJKMNP-TV-Z]{26}$/'],
            'context' => ['nullable', 'array'],
            'context.payload_last_id' => ['nullable', 'string', 'max:32'],
            'context.dom_last_id' => ['nullable', 'string', 'max:32'],
            'context.timeline_connected' => ['nullable', 'boolean'],
            'context.form_connected' => ['nullable', 'boolean'],
            'context.scroll_top' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'context.scroll_height' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'context.client_height' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'context.browser_locale' => [
                'nullable',
                'string',
                'max:35',
                'regex:/^[A-Za-z]{2,8}(?:-[A-Za-z0-9]{2,8}){0,3}$/',
            ],
            'context.attempt' => ['nullable', 'integer', 'min:0', 'max:100'],
            'context.http_status' => ['nullable', 'integer', 'between:100,599'],
            'context.operation' => ['nullable', 'string', 'max:80'],
            'context.retryable' => ['nullable', 'in:true,false,conditional'],
            'context.cause_code' => ['nullable', Rule::in(TopwebChatError::codes())],
        ]);

        $allowedContext = collect($validated['context'] ?? [])->only([
            'payload_last_id',
            'dom_last_id',
            'timeline_connected',
            'form_connected',
            'scroll_top',
            'scroll_height',
            'client_height',
            'browser_locale',
            'attempt',
            'http_status',
            'operation',
            'retryable',
            'cause_code',
        ])->all();

        Log::channel('topweb_chat_client')->log(
            $validated['level'],
            $validated['event'],
            array_merge($allowedContext, [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'error_code' => $validated['error_code'] ?? null,
                'trace_id' => $validated['trace_id'] ?? null,
                'technical_event' => $validated['event'],
                'environment' => app()->environment(),
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

    public function destroyByPerson(
        Person $person
    ): JsonResponse {
        abort_unless(bouncer()->hasPermission('topweb_chat.settings.index'), 403);

        $deletedCount = Conversation::query()
            ->where('person_id', $person->id)
            ->delete();

        return response()->json([
            'message' => trans('topweb_chat::app.conversations.deleted_by_person', ['count' => $deletedCount]),
        ]);
    }
}
