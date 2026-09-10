<?php

namespace Webkul\Admin\Http\Controllers\Contact\Persons;

use App\Services\SensitiveDataService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Prettus\Repository\Criteria\RequestCriteria;
use Webkul\Admin\DataGrids\Contact\PersonDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Admin\Http\Resources\PersonResource;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\TopwebChat\Models\Conversation;

class PersonController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(
        protected PersonRepository $personRepository,
        protected SensitiveDataService $sensitiveData
    ) {
        request()->request->add(['entity_type' => 'persons']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(PersonDataGrid::class)->process();
        }

        return view('admin::contacts.persons.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::contacts.persons.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.person.create.before');

        $canViewSensitive = $this->sensitiveData->canView();
        $duplicate = $this->findDuplicateByPhone($request->input('contact_numbers', []));

        if ($duplicate) {
            if ($canViewSensitive) {
                $message = trans('admin::app.contacts.persons.index.already-linked-warning', ['name' => $duplicate->name]);

                if (request()->ajax()) {
                    return response()->json(['message' => $message], 409);
                }

                session()->flash('warning', $message);

                return redirect()->route('admin.contacts.persons.index');
            }

            return $this->pendingReviewResponse();
        }

        $data = $request->all();

        if (! $canViewSensitive) {
            $data['user_id'] = null;
        }

        $person = $this->personRepository->create($data);

        Event::dispatch('contacts.person.create.after', $person);

        if (! $canViewSensitive) {
            return $this->pendingReviewResponse($person);
        }

        if (request()->ajax()) {
            return response()->json([
                'data' => new PersonResource($person),
                'message' => trans('admin::app.contacts.persons.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.create-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Neutral review-flow answer: identical whether the phone matched
     * an existing person or not, so the response never leaks portfolio
     * membership to users without the sensitive-data grant.
     */
    private function pendingReviewResponse(?Person $person = null): RedirectResponse|JsonResponse
    {
        $message = trans('admin::app.contacts.persons.index.pending-review');

        if (request()->ajax()) {
            return response()->json([
                'data' => $person ? new PersonResource($person) : null,
                'message' => $message,
            ], 202);
        }

        session()->flash('success', $message);

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Find an existing person sharing any submitted phone number.
     *
     * Compares digit-only variants (country code, optional 9th digit)
     * so formatting never hides a duplicate from the distributor.
     */
    private function findDuplicateByPhone(mixed $contactNumbers): ?Person
    {
        $digits = collect(is_array($contactNumbers) ? $contactNumbers : [])
            ->map(fn ($contact) => preg_replace('/\D+/', '', (string) data_get($contact, 'value')))
            ->filter()
            ->flatMap(function (string $number) {
                $variants = [$number];

                if (str_starts_with($number, '55') && strlen($number) === 13) {
                    $variants[] = substr($number, 0, 4).substr($number, 5);
                }

                if (str_starts_with($number, '55') && strlen($number) === 12) {
                    $variants[] = substr($number, 0, 4).'9'.substr($number, 4);
                }

                return $variants;
            })
            ->unique()
            ->values();

        if ($digits->isEmpty()) {
            return null;
        }

        return Person::query()->where(function ($query) use ($digits) {
            foreach ($digits as $digit) {
                $query->orWhere('contact_numbers', 'LIKE', '%'.$digit.'%');
            }
        })->first();
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $person = $this->personRepository->findOrFail($id);

        return view('admin::contacts.persons.view', compact('person'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $person = $this->personRepository->findOrFail($id);

        return view('admin::contacts.persons.edit', compact('person'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse|JsonResponse
    {
        Event::dispatch('contacts.person.update.before', $id);

        $data = $this->sensitiveData->sanitizeInput('persons', $request->all());

        if (! $this->sensitiveData->canView()) {
            $existingPerson = $this->personRepository->findOrFail($id);
            $data['emails'] = $existingPerson->emails;

            if (! empty($existingPerson->contact_numbers)) {
                $data['contact_numbers'] = $existingPerson->contact_numbers;
            }
        }

        $person = $this->personRepository->update(
            $data,
            $id
        );

        Event::dispatch('contacts.person.update.after', $person);

        if (request()->ajax()) {
            return response()->json([
                'data' => new PersonResource($person),
                'message' => trans('admin::app.contacts.persons.index.update-success'),
            ], 200);
        }

        session()->flash('success', trans('admin::app.contacts.persons.index.update-success'));

        return redirect()->route('admin.contacts.persons.index');
    }

    /**
     * Search person results.
     */
    public function search(): JsonResource
    {
        if (! $this->sensitiveData->canView()) {
            request()->query->set('searchFields', 'name:like');
            request()->query->set('orderBy', 'name');
            request()->query->set('sortedBy', 'asc');
        }

        $personRepository = $this->personRepository
            ->pushCriteria(app(RequestCriteria::class));

        if ($searchTerm = request()->query('query')) {
            $personRepository = $personRepository->scopeQuery(function ($query) use ($searchTerm) {
                return $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', '%'.$searchTerm.'%');

                    if ($this->sensitiveData->canView()) {
                        $q->orWhere('emails', 'like', '%'.$searchTerm.'%')
                            ->orWhere('contact_numbers', 'like', '%'.$searchTerm.'%');
                    }
                });
            });
        }

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $persons = $personRepository->findWhereIn('user_id', $userIds);
        } else {
            $persons = $personRepository->all();
        }

        return PersonResource::collection($persons);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $person = $this->personRepository->findOrFail($id);

        if (
            $person->leads
            && $person->leads->count() > 0
        ) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }

        try {
            Event::dispatch('contacts.person.delete.before', $person);

            $person->delete();

            Event::dispatch('contacts.person.delete.after', $person);

            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-success'),
            ], 200);

        } catch (Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass destroy the specified resources from storage.
     */
    public function massDestroy(MassDestroyRequest $request): JsonResponse
    {
        try {
            $persons = $this->personRepository->findWhereIn('id', $request->input('indices', []));

            $deletedCount = 0;

            $blockedCount = 0;

            $blockedReasons = [];

            foreach ($persons as $person) {
                $reasons = [];

                if (
                    $person->leads
                    && $person->leads->count() > 0
                ) {
                    $reasons[] = trans('admin::app.contacts.persons.index.has_leads');
                }

                $conversationCount = Conversation::query()
                    ->where('person_id', $person->id)
                    ->count();

                if ($conversationCount > 0) {
                    $reasons[] = trans('admin::app.contacts.persons.index.has_conversations', ['count' => $conversationCount]);
                }

                if (! empty($reasons)) {
                    $blockedCount++;
                    $blockedReasons[$person->id] = $reasons;

                    continue;
                }

                Event::dispatch('contact.person.delete.before', $person);

                $this->personRepository->delete($person->id);

                Event::dispatch('contact.person.delete.after', $person);

                $deletedCount++;
            }

            $statusCode = 200;

            switch (true) {
                case $deletedCount > 0 && $blockedCount === 0:
                    $message = trans('admin::app.contacts.persons.index.all-delete-success');

                    break;

                case $deletedCount > 0 && $blockedCount > 0:
                    $message = trans('admin::app.contacts.persons.index.partial-delete-warning');

                    break;

                case $deletedCount === 0 && $blockedCount > 0:
                    $message = trans('admin::app.contacts.persons.index.none-delete-warning');

                    $statusCode = 400;

                    break;

                default:
                    $message = trans('admin::app.contacts.persons.index.no-selection');

                    $statusCode = 400;

                    break;
            }

            return response()->json([
                'message' => $message,
                'blocked' => $blockedReasons,
            ], $statusCode);
        } catch (Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.persons.index.delete-failed'),
            ], 400);
        }
    }
}
