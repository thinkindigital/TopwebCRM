<?php

use Illuminate\Support\Facades\File;

it('contains no RyzeAPI contracts in the executable TopwebChat package', function () {
    $references = collect(File::allFiles(base_path('packages/Webkul/TopwebChat')))
        ->filter(fn (SplFileInfo $file) => in_array($file->getExtension(), ['php', 'blade.php'], true)
            || str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(fn (SplFileInfo $file) => str_contains(strtolower($file->getContents()), 'ryze'))
        ->map(fn (SplFileInfo $file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($references)->toBe([], 'RyzeAPI references remain in: '.implode(', ', $references));
});
