<?php

namespace App\Support;

class CertificateShare
{
    /**
     * Public page where the certificate can be viewed and downloaded.
     */
    public static function url(string $code): string
    {
        return rtrim((string) config('app.frontend_url'), '/')
            .'/verificar.html?code='.rawurlencode($code);
    }
}
