@props([
    'customAttributes' => [],
    'entity'           => null,
    'canAddNew'        => true,
])

@foreach ($customAttributes as $attribute)
    @php
        $sensitiveData = app(\App\Services\SensitiveDataService::class);
        $validations = [];

        if ($attribute->is_required) {
            $validations[] = 'required';
        }

        if ($attribute->type == 'price') {
            $validations[] = 'decimal';
        }

        $validations[] = $attribute->validation;

        $validations = implode('|', array_filter($validations));

        $key = 'installer::app.seeders.attributes.'.$attribute->entity_type.'.'.str_replace('_', '-', $attribute->code);
        $label = trans($key);
        if ($label === $key) {
            $label = $attribute->name;
        }

        $value = isset($entity) ? $entity[$attribute->code] : null;
        $isProtected = isset($entity) && $sensitiveData->shouldProtect(
            $attribute->entity_type,
            $attribute->code,
            $attribute->type
        );
    @endphp

    <x-admin::form.control-group class="mb-2.5 w-full">
        <x-admin::form.control-group.label
            for="{{ $attribute->code }}"
            :class="$attribute->is_required ? 'required' : ''"
        >
            {{ $label }}

            @if ($attribute->type == 'price')
                <span class="currency-code">({{ core()->currencySymbol(config('app.currency')) }})</span>
            @endif
        </x-admin::form.control-group.label>

        @if (isset($attribute))
            @if ($isProtected)
                <p class="rounded-md bg-gray-100 px-3 py-2 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                    {{ $sensitiveData->display($attribute->entity_type, $attribute->code, $value, $attribute->type) }}
                </p>
            @else
                <x-admin::attributes.edit.index
                    :attribute="$attribute"
                    :validations="$validations"
                    :value="$value"
                    :can-add-new="$canAddNew"
                />
            @endif
        @endif

        <x-admin::form.control-group.error :control-name="$attribute->code" />
    </x-admin::form.control-group>
@endforeach
