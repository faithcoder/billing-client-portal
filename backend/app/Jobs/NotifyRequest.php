<?php

namespace App\Jobs;

use App\Contracts\NotificationProvider;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $requestId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(NotificationProvider $provider): void
    {
        $s = ServiceRequest::findOrFail($this->requestId);
        $user = User::findOrFail($s->user_id);
        $provider->notify($user->email, 'service_request.updated', $s->reference);
    }
}
