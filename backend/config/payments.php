<?php

return ['gateway' => env('PAYMENT_GATEWAY'), 'merchant' => env('PAYMENT_MERCHANT_ID', 'demo-merchant'), 'max_sync_attempts' => 5];
