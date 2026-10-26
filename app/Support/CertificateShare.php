<?php

namespace App\Support;

use App\Models\Company;

class CertificateShare
{
    /**
     * Public page where the certificate can be viewed and downloaded.
     */
    public static function url(string $code, ?Company $company = null): string
    {
        $base = $company?->domain
            ? $company->frontendUrl()
            : rtrim((string) config('app.frontend_url'), '/');

        return $base.'/v/'.rawurlencode($code);
    }
}
