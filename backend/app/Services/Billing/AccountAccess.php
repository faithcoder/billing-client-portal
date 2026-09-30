<?php

namespace App\Services\Billing;

use App\Models\ExternalAccountLink;
use App\Models\User;

class AccountAccess
{
    public function account(User $user): ExternalAccountLink
    {
        return ExternalAccountLink::where('user_id', $user->id)->where('status', 'verified')->firstOrFail();
    }

    public function filters(User $user, array $filters): array
    {
        $link = $this->account($user);
        foreach (['external_account_id', 'external_customer_id'] as $k) {
            abort_if(isset($filters[$k]) && $filters[$k] !== $link->$k, 404);
        }

return [...$filters, 'external_account_id' => $link->external_account_id];
    }
}
