<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProvider;

class UnavailableNotificationProvider implements NotificationProvider
{
    public function sendCode(string $destination, string $code, string $purpose): void
    {
        abort(503);
    }

    public function notify(string $destination, string $messageKey, string $reference): void
    {
        abort(503);
    }
}
