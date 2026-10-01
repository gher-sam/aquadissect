<?php

namespace App\Support;

class GoogleAccess
{
    public static function ready(): bool
    {
        return config('services.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
