<?php

namespace App\Services\Requests;

use App\Contracts\ServiceRequestSync;
use App\Models\ServiceRequest;

class LocalOnlySync implements ServiceRequestSync
{
    public function supported(): bool
    {
        return false;
    }

    public function submit(ServiceRequest $r): void
    {
        throw new \LogicException('No approved external service-request contract.');
    }
}
