<?php

namespace App\Services\Billing;

class IntegrationReadiness
{
    public function available(): bool
    {
        // Collection readiness remains closed for live mode until the gateway,
        // ownership delivery and actual upstream contract are accepted.
        return app()->environment(['local', 'testing']) && config('billing.mode') === 'mock';
    }
}
