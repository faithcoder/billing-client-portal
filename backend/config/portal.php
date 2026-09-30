<?php

return ['notifications' => env('NOTIFICATION_DRIVER'), 'verification_ttl' => 300, 'verification_max_attempts' => 5, 'upload_max_kb' => 5120, 'scanner' => env('UPLOAD_SCANNER', 'unavailable')];
