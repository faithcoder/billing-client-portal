<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProvider;

class FakeNotificationProvider implements NotificationProvider
{
    public function sendCode(string $destination, string $code, string $purpose): void
    {
        $this->guard(); /* No delivery or logging. Local-only test code is documented. */
    }

    public function notify(string $destination, string $messageKey, string $reference): void
    {
        $this->guard();
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('portal.notifications') === 'fake', 503);
    }
}
