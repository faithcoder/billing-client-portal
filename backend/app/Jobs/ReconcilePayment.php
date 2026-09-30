<?php

namespace App\Jobs;

use App\Models\PaymentAttempt;
use App\Services\Payments\ReconcileService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReconcilePayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 40;

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public string $paymentId) {}

    public function handle(ReconcileService $s): void
    {
        $p = PaymentAttempt::find($this->paymentId);
        if ($p && $p->status !== 'succeeded') {
            $s->run($p);
        }
    }
}
