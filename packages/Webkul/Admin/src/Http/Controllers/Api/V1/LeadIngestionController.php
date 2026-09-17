<?php

namespace Webkul\Admin\Http\Controllers\Api\V1;

use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Lead\Services\LeadIngestionService;

class LeadIngestionController extends Controller
{
    public function __construct(
        protected LeadIngestionService $ingestions
    ) {}

    /**
     * #19: cria/atualiza Lead via integração externa, com idempotência.
     * Repetição da mesma chave devolve os mesmos IDs sem duplicar nada.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->tokenCan('leads:ingest'), 403);

        $data = $request->validate([
            'source' => ['required', 'string', 'max:60'],
            'source_lead_id' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'person.name' => ['required', 'string', 'max:255'],
            'person.emails' => ['nullable', 'array'],
            'person.emails.*.value' => ['nullable', 'email', 'max:255'],
            'lead.title' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->ingestions->ingest($data);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result, $result['duplicate'] ? 200 : 201);
    }
}
