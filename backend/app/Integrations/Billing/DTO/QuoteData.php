<?php

namespace App\Integrations\Billing\DTO;

class QuoteData extends ValidatedData
{
    protected function rules(): array
    {
        return ['quote_id' => $this->id(), 'reservation_id' => 'present|nullable|string', 'external_bill_id' => $this->id(), 'external_account_id' => $this->id(), 'external_customer_id' => $this->id(), 'currency' => 'required|in:BDT', 'payable_amount_minor' => $this->money(), 'outstanding_balance_minor' => $this->money(), 'collection_fee_minor' => $this->money(), 'total_charge_minor' => $this->money(), 'payment_mode' => 'required|in:full_single_bill', 'eligible' => 'required|boolean', 'issued_at' => 'required|date', 'expires_at' => 'required|date', 'source_version' => 'required|string', 'source_updated_at' => 'present|nullable|date'];
    }
}
