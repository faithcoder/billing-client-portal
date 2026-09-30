<?php

namespace App\Policies;

use App\Models\ExternalAccountLink;
use App\Models\User;

class ExternalAccountLinkPolicy
{
    public function view(User $user, ExternalAccountLink $link): bool
    {
        return (string) $link->user_id === (string) $user->id && $link->status === 'verified';
    }

    public function delete(User $user, ExternalAccountLink $link): bool
    {
        return $this->view($user, $link);
    }
}
