<?php

namespace Webkul\Core;

use Illuminate\Support\Facades\Vite as BaseVite;
use Throwable;
use Webkul\Core\Exceptions\ViterNotFound;

class Vite
{
    /**
     * Return the asset URL.
     *
     * @return string
     */
    public function asset(string $filename, string $namespace = 'admin')
    {
        $viters = config('krayin-vite.viters');

        if (empty($viters[$namespace])) {
            throw new ViterNotFound($namespace);
        }

        $url = trim($filename, '/');

        $viteUrl = trim($viters[$namespace]['package_assets_directory'], '/').'/'.$url;

        return BaseVite::useHotFile($viters[$namespace]['hot_file'])
            ->useBuildDirectory($viters[$namespace]['build_directory'])
            ->asset($viteUrl);
    }

    /**
     * Return the asset URL, degrading gracefully when the build manifest
     * is missing. Error pages must never fail because assets are broken.
     */
    public function safeAsset(string $filename, string $namespace = 'admin'): string
    {
        try {
            return (string) $this->asset($filename, $namespace);
        } catch (Throwable) {
            return '';
        }
    }

    public function set(mixed $entryPoints, string $namespace = 'admin')
    {
        $viters = config('krayin-vite.viters');

        if (empty($viters[$namespace])) {
            throw new ViterNotFound($namespace);
        }

        return BaseVite::useHotFile($viters[$namespace]['hot_file'])
            ->useBuildDirectory($viters[$namespace]['build_directory'])
            ->withEntryPoints($entryPoints);
    }
}
