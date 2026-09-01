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
        $digits = preg_replace('/\D+/', '', $remoteId);

        // Brazilian mobile numbers may arrive from OpenWA without the ninth
        // digit (55 + area code + 8 digits), while CRM contacts commonly keep
        // the canonical 55 + area code + 9 + 8 digits form.
        if (
            strlen($digits) === 12
            && str_starts_with($digits, '55')
            && (int) ($digits[4] ?? 0) >= 6
        ) {
            $digits = substr($digits, 0, 4).'9'.substr($digits, 4);
        }

        return $digits;
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
