<?php

namespace App\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Webkul\Attribute\Repositories\AttributeRepository;

class SensitiveDataService
{
    public function __construct(protected AttributeRepository $attributeRepository) {}

    public function canView(?Authenticatable $user = null): bool
    {
        $user ??= auth()->guard('user')->user();

        return (bool) ($user?->can_view_sensitive_data ?? false);
    }

    public function authorize(?Authenticatable $user = null): void
    {
        abort_unless($this->canView($user), 403, trans('topwebcrm::sensitive-data.forbidden'));
    }

    public function classification(string $entityType, string $field, ?string $attributeType = null): ?string
    {
        $classification = config("sensitive-data.fields.{$entityType}.{$field}");

        if ($classification) {
            return $classification;
        }

        if ($attributeType) {
            $classification = config("sensitive-data.attribute_types.{$attributeType}");

            if ($classification) {
                return $classification;
            }
        }

        foreach (config('sensitive-data.document_patterns', []) as $pattern) {
            if (preg_match($pattern, $field)) {
                return 'document';
            }
        }

        return null;
    }

    public function isSensitive(string $entityType, string $field, ?string $attributeType = null): bool
    {
        return $this->classification($entityType, $field, $attributeType) !== null;
    }

    public function shouldProtect(
        string $entityType,
        string $field,
        ?string $attributeType = null,
        ?Authenticatable $user = null
    ): bool {
        return ! $this->canView($user) && $this->isSensitive($entityType, $field, $attributeType);
    }

    public function protect(
        string $entityType,
        string $field,
        mixed $value,
        ?string $attributeType = null,
        ?Authenticatable $user = null
    ): mixed {
        if (! $this->shouldProtect($entityType, $field, $attributeType, $user)) {
            return $value;
        }

        return $this->redactValue(
            $this->classification($entityType, $field, $attributeType),
            $value
        );
    }

    public function redactValue(?string $classification, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $value;
        }

        return match ($classification) {
            'email' => $this->maskEmailValue($value),
            'phone' => $this->maskPhoneValue($value),
            'document' => $this->maskDocument((string) $value),
            'address' => $this->maskAddress($value),
            'activity' => null,
            'financial', 'hidden' => null,
            default => null,
        };
    }

    public function display(
        string $entityType,
        string $field,
        mixed $value,
        ?string $attributeType = null,
        ?Authenticatable $user = null
    ): string {
        $protectedValue = $this->protect($entityType, $field, $value, $attributeType, $user);

        if (is_array($protectedValue)) {
            if (array_is_list($protectedValue)) {
                $protectedValue = collect($protectedValue)
                    ->map(fn ($item) => is_array($item) ? ($item['value'] ?? null) : $item)
                    ->filter()
                    ->implode(', ');
            } else {
                $protectedValue = collect($protectedValue)
                    ->only(['address', 'postcode', 'city', 'state', 'country'])
                    ->filter()
                    ->implode(', ');
            }
        }

        if ($protectedValue === null && $value !== null && $value !== '') {
            return config('sensitive-data.mask');
        }

        return (string) ($protectedValue ?? '');
    }

    public function sanitizeInput(string $entityType, array $data, ?Authenticatable $user = null): array
    {
        if ($this->canView($user)) {
            return $data;
        }

        foreach (array_keys(config("sensitive-data.fields.{$entityType}", [])) as $field) {
            Arr::forget($data, $field);
        }

        $attributes = $this->attributeRepository->findWhere([
            'entity_type' => $entityType,
        ]);

        foreach ($attributes as $attribute) {
            if ($this->isSensitive($entityType, $attribute->code, $attribute->type)) {
                Arr::forget($data, $attribute->code);
            }
        }

        if ($entityType === 'leads' && isset($data['person']) && is_array($data['person'])) {
            $data['person'] = $this->sanitizeInput('persons', $data['person'], $user);
        }

        return $data;
    }

    public function maskActivityAdditional(string $activityType, mixed $additional, ?Authenticatable $user = null): mixed
    {
        if ($this->canView($user)) {
            return $additional;
        }

        if ($activityType === 'system') {
            return null;
        }

        if ($activityType !== 'email' || ! is_array($additional)) {
            return null;
        }

        $additional = Arr::only($additional, [
            'folders',
            'from',
            'to',
            'cc',
            'bcc',
            'reply_to',
            'sender',
        ]);

        foreach (['from', 'to', 'cc', 'bcc', 'reply_to', 'sender'] as $field) {
            if (array_key_exists($field, $additional)) {
                $additional[$field] = $this->maskEmailValue($additional[$field]);
            }
        }

        return $additional;
    }

    public function redactDashboard(string $type, mixed $statistics, ?Authenticatable $user = null): mixed
    {
        if ($this->canView($user)) {
            return $statistics;
        }

        if (in_array($type, [
            'revenue-stats',
            'revenue-by-sources',
            'revenue-by-types',
            'top-selling-products',
            'top-persons',
        ], true)) {
            return [];
        }

        if ($type === 'over-all' && is_array($statistics)) {
            $statistics['average_lead_value'] = [
                'previous' => null,
                'current' => null,
                'progress' => 0,
                'formatted_total' => config('sensitive-data.mask'),
                'redacted' => true,
            ];
        }

        return $statistics;
    }

    public function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return config('sensitive-data.mask');
        }

        [$local, $domain] = explode('@', $email, 2);
        $visibleLength = min(2, mb_strlen($local));

        return mb_substr($local, 0, $visibleLength)
            .str_repeat('*', max(4, mb_strlen($local) - $visibleLength))
            .'@'.$domain;
    }

    public function maskPhone(string $phone): string
    {
        $digitCount = preg_match_all('/\d/', $phone);

        if ($digitCount === 0 || $digitCount === false) {
            return config('sensitive-data.mask');
        }

        if ($digitCount <= 4) {
            return preg_replace('/\d/', 'X', $phone) ?: config('sensitive-data.mask');
        }

        $position = 0;

        return preg_replace_callback('/\d/', function ($match) use (&$position, $digitCount) {
            $position++;

            return $position <= 2 || $position > $digitCount - 2 ? $match[0] : 'X';
        }, $phone) ?? config('sensitive-data.mask');
    }

    public function maskDocument(string $document): string
    {
        $characterCount = preg_match_all('/[\pL\pN]/u', $document);

        if ($characterCount === 0 || $characterCount === false) {
            return config('sensitive-data.mask');
        }

        $position = 0;

        return preg_replace_callback('/[\pL\pN]/u', function ($match) use (&$position, $characterCount) {
            $position++;

            return $position <= 3 || $position > $characterCount - 2 ? $match[0] : 'X';
        }, $document) ?? config('sensitive-data.mask');
    }

    protected function maskEmailValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->map(function ($item) {
                if (is_array($item)) {
                    if (isset($item['value'])) {
                        $item['value'] = $this->maskEmail((string) $item['value']);
                    }

                    return $item;
                }

                return $this->maskEmail((string) $item);
            })->all();
        }

        return $this->maskEmail((string) $value);
    }

    protected function maskPhoneValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->map(function ($item) {
                if (is_array($item)) {
                    if (isset($item['value'])) {
                        $item['value'] = $this->maskPhone((string) $item['value']);
                    }

                    return $item;
                }

                return $this->maskPhone((string) $item);
            })->all();
        }

        return $this->maskPhone((string) $value);
    }

    protected function maskAddress(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        foreach (['address', 'street', 'line1', 'line2', 'house_number', 'postcode', 'postal_code', 'zip'] as $field) {
            if (array_key_exists($field, $value) && $value[$field]) {
                $value[$field] = config('sensitive-data.mask');
            }
        }

        return $value;
    }
}
