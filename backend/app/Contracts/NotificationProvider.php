<?php

namespace App\Contracts;

interface NotificationProvider
{
    public function sendCode(string $destination, string $code, string $purpose): void;

    public function notify(string $destination, string $messageKey, string $reference): void;
}
