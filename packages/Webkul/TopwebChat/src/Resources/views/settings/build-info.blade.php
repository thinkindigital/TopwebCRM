<x-admin::layouts>
    <x-slot:title>Build Info</x-slot>

    <section class="rounded-lg border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <h1 class="text-xl font-bold text-gray-800 dark:text-white">Build Info</h1>
        <dl class="mt-4 grid gap-3 text-sm text-gray-700 dark:text-gray-200">
            @foreach ($build as $name => $value)
                <div class="grid gap-1 sm:grid-cols-[14rem_1fr]">
                    <dt class="font-semibold">{{ str_replace('_', ' ', ucfirst($name)) }}</dt>
                    <dd class="font-mono break-all">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-admin::layouts>
