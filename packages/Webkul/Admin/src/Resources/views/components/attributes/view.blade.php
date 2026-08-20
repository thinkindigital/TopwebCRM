@props([
    'customAttributes' => [],
    'entity' => null,
    'allowEdit' => false,
    'url' => null,
])

<div class="flex flex-col gap-1">
    @foreach ($customAttributes as $attribute)
        @php
            $sensitiveData = app(\App\Services\SensitiveDataService::class);
            $rawValue = isset($entity) ? $entity[$attribute->code] : null;
            $isProtected = $sensitiveData->shouldProtect(
                $attribute->entity_type,
                $attribute->code,
                $attribute->type
            );
        @endphp

        @if ($isProtected)
            <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                <div class="label dark:text-white">{{ $attribute->name }}</div>

                <div class="font-medium dark:text-white">
                    {{ $sensitiveData->display($attribute->entity_type, $attribute->code, $rawValue, $attribute->type) }}
                </div>
            </div>
        @elseif (view()->exists($typeView = 'admin::components.attributes.view.' . $attribute->type))
            <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                <div class="label dark:text-white">{{ $attribute->name }}</div>

                <div class="font-medium dark:text-white">
                    @include ($typeView, [
                        'attribute' => $attribute,
                        'value' => $rawValue,
                        'allowEdit' => $allowEdit,
                        'url' => $url,
                    ])
                </div>
            </div>
        @endif
    @endforeach
</div>
