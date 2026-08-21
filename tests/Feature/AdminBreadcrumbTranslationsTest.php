<?php

use Diglactic\Breadcrumbs\Manager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;

it('has translations for every admin breadcrumb label', function () {
    preg_match_all(
        "/__\('([^']+)'\)/",
        file_get_contents(base_path('routes/breadcrumbs.php')),
        $matches
    );

    $missing = collect($matches[1])
        ->unique()
        ->reject(fn (string $key) => Lang::has($key, 'pt_BR'))
        ->values()
        ->all();

    expect($missing)->toBe([], 'Missing pt_BR breadcrumb translations: '.implode(', ', $missing));

    $nonStrings = collect($matches[1])
        ->unique()
        ->reject(fn (string $key) => is_string(Lang::get($key, [], 'pt_BR')))
        ->values()
        ->all();

    expect($nonStrings)->toBe([], 'Non-string pt_BR breadcrumb translations: '.implode(', ', $nonStrings));
});

it('defines every static admin breadcrumb used by a view', function () {
    $names = collect(File::allFiles(base_path('packages/Webkul/Admin/src/Resources/views')))
        ->flatMap(function (SplFileInfo $file) {
            preg_match_all(
                '/<x-admin::breadcrumbs[^>]*\sname="([^"]+)"/s',
                $file->getContents(),
                $matches
            );

            return $matches[1];
        })
        ->unique()
        ->values();

    $missing = $names
        ->reject(fn (string $name) => app(Manager::class)->exists($name))
        ->all();

    expect($missing)->toBe([], 'Undefined admin breadcrumbs: '.implode(', ', $missing));
});
