<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SensitiveFileService
{
    public function diskName(): string
    {
        $disk = config('sensitive-data.storage.disk', 'private');

        if ($disk === $this->legacyDiskName()) {
            throw new RuntimeException('Sensitive storage must use a dedicated private disk.');
        }

        return $disk;
    }

    public function legacyDiskName(): string
    {
        return config('sensitive-data.storage.legacy_disk', 'public');
    }

    public function put(string $path, string $contents): void
    {
        try {
            $stored = $this->disk()->put($path, $contents);
        } catch (Throwable) {
            throw new RuntimeException('Unable to store sensitive file.');
        }

        if (! $stored) {
            throw new RuntimeException('Unable to store sensitive file.');
        }
    }

    public function size(string $path): int
    {
        try {
            return $this->disk()->size($path);
        } catch (Throwable) {
            throw new RuntimeException('Unable to inspect sensitive file.');
        }
    }

    public function store(UploadedFile $file, string $directory): string
    {
        try {
            $path = $file->store($directory, $this->diskName());
        } catch (Throwable) {
            throw new RuntimeException('Unable to store sensitive upload.');
        }

        if (! $path) {
            throw new RuntimeException('Unable to store sensitive upload.');
        }

        return $path;
    }

    public function download(string $path, ?string $name = null): StreamedResponse
    {
        if ($this->disk()->exists($path)) {
            return $this->disk()->download($path, $name);
        }

        return $this->legacyDisk()->download($path, $name);
    }

    public function inline(
        string $path,
        string $mime,
        ?string $name = null
    ): StreamedResponse {
        $disk = $this->disk()->exists($path)
            ? $this->disk()
            : $this->legacyDisk();

        return $disk->response($path, $name, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    public function migrationStatus(string $path): string
    {
        $existsOnPrivateDisk = $this->disk()->exists($path);
        $existsOnLegacyDisk = $this->legacyDisk()->exists($path);

        if ($existsOnPrivateDisk && $existsOnLegacyDisk) {
            return $this->disk()->size($path) === $this->legacyDisk()->size($path)
                ? 'duplicate'
                : 'conflict';
        }

        if ($existsOnPrivateDisk) {
            return 'private';
        }

        if ($existsOnLegacyDisk) {
            return 'legacy';
        }

        return 'missing';
    }

    public function migrateLegacy(string $path): string
    {
        $status = $this->migrationStatus($path);

        if ($status === 'private') {
            return 'already-private';
        }

        if ($status === 'duplicate') {
            if (! $this->legacyDisk()->delete($path)) {
                throw new RuntimeException('Unable to remove public sensitive file.');
            }

            return 'removed-public-duplicate';
        }

        if ($status !== 'legacy') {
            return $status;
        }

        $stream = $this->legacyDisk()->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read legacy sensitive file.');
        }

        try {
            $stored = $this->disk()->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        if (
            ! $stored
            || ! $this->disk()->exists($path)
            || $this->disk()->size($path) !== $this->legacyDisk()->size($path)
        ) {
            $this->disk()->delete($path);

            throw new RuntimeException('Unable to verify migrated sensitive file.');
        }

        if (! $this->legacyDisk()->delete($path)) {
            throw new RuntimeException('Unable to remove migrated public sensitive file.');
        }

        return 'migrated';
    }

    protected function disk(): FilesystemAdapter
    {
        return Storage::disk($this->diskName());
    }

    protected function legacyDisk(): FilesystemAdapter
    {
        return Storage::disk($this->legacyDiskName());
    }
}
