<?php

namespace App\Services\Billing;

class IntegrationReadiness
{
    public function available(): bool
    {
        // No upstream routes are guessed. Even configured live mode stays closed
        // until a documented, validated adapter is implemented in a later phase.
        return app()->environment(['local', 'testing']) && config('billing.mode') === 'mock';
    }
}
