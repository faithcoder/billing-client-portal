<?php

namespace App\Contracts;

use App\Models\ServiceRequest;

interface ServiceRequestSync
{
    public function supported(): bool;

    public function submit(ServiceRequest $request): void;
}
