<?php

namespace Webkul\TopwebChat\Services;

use DomainException;
use Illuminate\Support\Facades\Event;
use Webkul\Contact\Models\Person;
use Webkul\Contact\Repositories\PersonRepository;

class ContactResolverService
{
    public function __construct(
        protected PersonRepository $personRepository,
        protected RemoteIdentityService $remoteIdentity
    ) {}

    public function resolve(string $remoteId, ?string $displayName = null): Person
    {
        $phone = $this->remoteIdentity->phone($remoteId);

        if (! $phone) {
            throw new DomainException(trans('topweb_chat::app.contacts.identity_requires_review'));
        }

        $matches = collect();

        foreach (Person::query()->whereNotNull('contact_numbers')->cursor() as $person) {
            foreach ($person->contact_numbers ?? [] as $contactNumber) {
                if (
                    $this->remoteIdentity->phone((string) ($contactNumber['value'] ?? ''))
                    === $phone
                ) {
                    $matches->push($person);

                    break;
                }
            }
        }

        if ($matches->count() > 1) {
            throw new DomainException(trans('topweb_chat::app.contacts.duplicate_phone'));
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        Event::dispatch('contacts.person.create.before');

        $data = [
            'entity_type' => 'persons',
            'name' => $displayName ?: trans('topweb_chat::app.contacts.unknown'),
        ];

        $data['contact_numbers'] = [[
            'value' => $phone,
            'label' => 'work',
        ]];

        $person = $this->personRepository->create($data);

        Event::dispatch('contacts.person.create.after', $person);

        return $person;
    }
}
