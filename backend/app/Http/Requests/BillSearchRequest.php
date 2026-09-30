<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['external_bill_id' => 'sometimes|string|max:128', 'external_account_id' => 'sometimes|string|max:128', 'external_customer_id' => 'sometimes|string|max:128', 'status' => 'sometimes|in:unpaid,partially_paid,paid,void,unknown', 'month_from' => 'sometimes|date_format:Y-m', 'month_to' => $this->filled('month_from') ? 'sometimes|date_format:Y-m|after_or_equal:month_from' : 'sometimes|date_format:Y-m', 'page' => 'sometimes|integer|min:1|max:1000000', 'per_page' => 'sometimes|integer|min:1|max:50'];
    }

    public function filters(): array
    {
        $d = $this->validated();
        $d['page'] = (int) ($d['page'] ?? 1);
        $d['per_page'] = (int) ($d['per_page'] ?? 10);

        return $d;
    }
}
