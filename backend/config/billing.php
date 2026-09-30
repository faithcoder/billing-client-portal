<?php

return [
    'contract_approved' => (bool) env('BILLING_CONTRACT_APPROVED', false),
    'allowed_hosts' => array_filter(explode(',', (string) env('BILLING_ALLOWED_HOSTS', ''))),
    'idempotent_posting' => (bool) env('BILLING_IDEMPOTENT_POSTING', false),
    'reference_lookup' => (bool) env('BILLING_REFERENCE_LOOKUP', false),
    'atomic_settlement' => (bool) env('BILLING_ATOMIC_SETTLEMENT', false),
    'mode' => env('BILLING_MODE'),
    'url' => env('BILLING_API_URL'),
    'token' => env('BILLING_API_TOKEN'),
    'timezone' => 'Asia/Dhaka',
    'currency' => 'BDT',
];
