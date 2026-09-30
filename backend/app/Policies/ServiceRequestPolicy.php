<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function view(User $u, ServiceRequest $s): bool
    {
        return (string) $u->id === (string) $s->user_id || in_array($u->role, ['support', 'admin']);
    }

    public function update(User $u, ServiceRequest $s): bool
    {
        return (string) $u->id === (string) $s->user_id && in_array($s->status, ['draft', 'more_information_required']);
    }

    public function review(User $u, ServiceRequest $s): bool
    {
        return in_array($u->role, ['support', 'admin']);
    }
}
