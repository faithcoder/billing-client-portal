<?php

namespace App\Integrations\Billing\DTO;

class CustomerData extends ValidatedData
{
    protected function rules(): array
    {
        return ['external_customer_id' => $this->id(), 'external_account_id' => $this->id(), 'name' => 'required|string', 'address' => 'required|array', 'address.*' => 'nullable|string', 'phone' => 'present|nullable|string', 'currency' => 'required|in:BDT', 'outstanding_balance_minor' => $this->money(), 'source_updated_at' => 'present|nullable|date', 'customer_type' => 'required|array', 'customer_type.label_bn' => 'required|string', 'customer_type.label_en' => 'required|string'];
    }
}
