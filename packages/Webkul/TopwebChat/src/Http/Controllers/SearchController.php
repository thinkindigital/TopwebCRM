<?php

namespace Webkul\TopwebChat\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\TopwebChat\Repositories\ConversationRepository;

class SearchController
{
    public function __construct(
        protected ConversationRepository $conversations
    ) {}

    /**
     * V-06: busca segura — carteira primeiro, nome/título depois, sem
     * identificadores. Fora do escopo: mesma forma, vazio (zero oráculo).
     */
    public function search(Request $request): JsonResponse
    {
        abort_unless(bouncer()->hasPermission('topweb_chat.inbox.view'), 403);

        $user = auth()->guard('user')->user();

        $results = $this->conversations
            ->search($user, (string) $request->string('q'))
            ->map(fn ($conversation) => [
                'id' => $conversation->id,
                'title' => $conversation->person?->name
                    ?? $conversation->lead?->title
                    ?? trans('topweb_chat::app.contacts.unknown'),
                'subtitle' => $conversation->lead?->title,
                'url' => route('admin.topweb_chat.show', $conversation),
            ])
            ->values();

        return response()->json(['data' => $results]);
    }
}
