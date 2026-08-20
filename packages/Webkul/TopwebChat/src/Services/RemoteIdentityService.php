<?php

namespace Webkul\TopwebChat\Services;

class RemoteIdentityService
{
    public function canonical(string $remoteId): string
    {
        $remoteId = strtolower(trim($remoteId));

        if (
            str_ends_with($remoteId, '@lid')
            || str_ends_with($remoteId, '@g.us')
            || str_ends_with($remoteId, '@newsletter')
        ) {
            return $remoteId;
        }

        $remoteId = preg_replace('/@s\.whatsapp\.net$/', '', $remoteId);

        return preg_replace('/\D+/', '', $remoteId);
    }

    public function key(string $remoteId): string
    {
        return hash('sha256', $this->canonical($remoteId));
    }

    public function phone(string $remoteId): ?string
    {
        $canonical = $this->canonical($remoteId);

        if (str_contains($canonical, '@')) {
            return null;
        }

        return strlen($canonical) >= 8 ? $canonical : null;
    }
}
