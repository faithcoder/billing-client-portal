<?php

namespace App\Services;

use App\Models\AuditLog;

class Audit
{
    public static function record(string $event, ?string $subject = null, array $metadata = []): void
    {
        AuditLog::create(['actor_id' => auth()->id(), 'event' => $event, 'subject' => $subject, 'metadata' => $metadata, 'request_id' => request()->attributes->get('request_id')]);
    }
}
