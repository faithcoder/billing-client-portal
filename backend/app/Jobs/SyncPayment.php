<?php

namespace App\Jobs;

use App\Services\Payments\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public string $paymentId) {}

    public function handle(SyncService $sync): void
    {
        $sync->run($this->paymentId);
    }
}
