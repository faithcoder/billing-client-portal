<?php

namespace App\Integrations\Billing\DTO;

class BillData extends ValidatedData
{
    protected function rules(): array
    {
        $r = ['external_bill_id' => $this->id(), 'bill_number' => $this->id(), 'bill_month' => 'required|date_format:Y-m', 'municipality' => 'required|array', 'municipality.name_bn' => 'required|string', 'customer' => 'required|array', 'customer.external_customer_id' => $this->id(), 'customer.name' => 'required|string', 'customer.address' => 'required|array', 'connection' => 'required|array', 'connection.external_account_id' => $this->id(), 'connection.customer_type' => 'required|array', 'meter_readings' => 'present|nullable|array', 'currency' => 'required|in:BDT', 'breakdown' => 'required|array', 'deadlines.issue_date' => 'required|date_format:Y-m-d', 'deadlines.due_date' => 'required|date_format:Y-m-d', 'deadlines.payment_cutoff_at' => 'present|nullable|date', 'deadlines.timezone' => 'required|in:Asia/Dhaka', 'authoritative_totals' => 'required|array', 'authoritative_totals.as_of' => 'required|date', 'previous_payment' => 'present|nullable|array', 'status' => 'required|in:unpaid,partially_paid,paid,void,unknown', 'source_updated_at' => 'present|nullable|date', 'source_version' => 'present|nullable|string'];
        foreach (['current_charges', 'arrears', 'arrears_surcharge', 'rebate', 'advance', 'late_fee'] as $k) {
            $r['breakdown.'.$k.'_minor'] = $this->money();
        }
        foreach (['before_deadline', 'after_deadline', 'paid_amount', 'outstanding_balance'] as $k) {
            $r['authoritative_totals.'.$k.'_minor'] = $this->money();
        }

        $r += ['municipality.name_en' => 'required|string', 'customer.address.*' => 'nullable|string', 'customer.guardian_name' => 'present|nullable|string', 'customer.mobile_number' => 'present|nullable|string', 'customer.old_customer_reference' => 'present|nullable|string', 'connection.meter_number' => 'present|nullable|string', 'connection.category' => 'required|string', 'connection.customer_type.label_bn' => 'required|string', 'connection.customer_type.label_en' => 'required|string', 'connection.pipe_size' => 'required|array', 'connection.pipe_size.value' => 'required|string', 'connection.pipe_size.unit' => 'present|nullable|string', 'connection.service_address' => 'required|array', 'connection.service_address.*' => 'nullable|string', 'meter_readings.*' => 'nullable|string', 'payment_instructions' => 'sometimes|nullable|string', 'paid_date' => 'sometimes|nullable|date_format:Y-m-d'];
        if (isset($this->validationInput['previous_payment'])) {
            $r['previous_payment.amount_minor'] = $this->money();
            $r['previous_payment.payment_date'] = 'required|date_format:Y-m-d';
        }

        return $r;
    }

    public function summary(): array
    {
        $d = $this->data;

        return ['external_bill_id' => $d['external_bill_id'], 'bill_number' => $d['bill_number'], 'external_account_id' => $d['connection']['external_account_id'], 'external_customer_id' => $d['customer']['external_customer_id'], 'type' => $d['connection']['customer_type'], 'bill_month' => $d['bill_month'], 'issue_date' => $d['deadlines']['issue_date'], 'due_date' => $d['deadlines']['due_date'], 'currency' => $d['currency'], 'amount_minor' => $d['authoritative_totals']['before_deadline_minor'], 'paid_amount_minor' => $d['authoritative_totals']['paid_amount_minor'], 'outstanding_balance_minor' => $d['authoritative_totals']['outstanding_balance_minor'], 'paid_date' => $d['paid_date'] ?? null, 'status' => $d['status'], 'source_updated_at' => $d['source_updated_at']];
    }
}
