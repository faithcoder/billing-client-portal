<?php

namespace App\Policies;

use App\Models\PaymentAttempt;
use App\Models\User;

class PaymentAttemptPolicy
{
    public function view(User $u, PaymentAttempt $p): bool
    {
        return (string) $u->id === (string) $p->user_id || in_array($u->role, ['support', 'admin']);
    }

    public function reconcile(User $u, PaymentAttempt $p): bool
    {
        return $u->role === 'admin';
    }
}
