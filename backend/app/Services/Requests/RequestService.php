<?php

namespace App\Services\Requests;

use App\Contracts\BillingSystemClient;
use App\Jobs\NotifyRequest;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Audit;
use App\Services\Billing\AccountAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RequestService
{
    public function create(User $user, array $d): ServiceRequest
    {
        if (! empty($d['external_account_id']) || ! empty($d['external_bill_id'])) {
            $link = app(AccountAccess::class)->account($user);
            abort_if(! empty($d['external_account_id']) && $link->external_account_id !== $d['external_account_id'], 404);
            $d['external_account_id'] = $link->external_account_id;
            if (! empty($d['external_bill_id'])) {
                app(BillingSystemClient::class)->bill($d['external_bill_id'], $link->external_account_id);
            }
        }

        return DB::transaction(function () use ($user, $d) {
            $id = (string) Str::uuid();
            $s = ServiceRequest::create([...$d, 'id' => $id, 'user_id' => $user->id, 'reference' => ($d['kind'] === 'application' ? 'APP-' : 'REQ-').$id, 'status' => 'draft']);
            $this->event($s, $user, 'Draft created');

            return $s;
        });
    }

    public function submit(User $user, ServiceRequest $s): ServiceRequest
    {
        Gate::authorize('update', $s);

        return DB::transaction(function () use ($s, $user) {
            $s = ServiceRequest::whereKey($s->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $s);
            if ($s->kind === 'application') {
                Validator::make($s->form_data ?? [], ['applicant_name' => 'required|string|max:120', 'contact_phone' => 'required|string|max:30', 'service_address' => 'required|string|max:1000', 'connection_type' => 'required|in:residential,commercial,other', 'pipe_size' => 'nullable|string|max:30'])->validate();
            } else {
                Validator::make($s->toArray(), ['category' => 'required|in:billing,meter,water_supply,profile_correction,other', 'description' => 'required|string|min:10|max:5000'])->validate();
            }
            $s->update(['status' => 'submitted']);
            $this->event($s, $user, 'Submitted');
            Audit::record('service_request.submitted', $s->id);
            NotifyRequest::dispatch($s->id)->afterCommit();

            return $s;
        });
    }

    public function review(User $user, ServiceRequest $s, string $status, string $message): ServiceRequest
    {
        Gate::authorize('review', $s);
        if (in_array($status, ['approved', 'rejected'])) {
            Gate::authorize('manage-roles');
        }
        $allowed = $s->kind === 'application' ? ['submitted' => ['under_review', 'more_information_required', 'rejected'], 'under_review' => ['more_information_required', 'approved', 'rejected'], 'more_information_required' => ['under_review', 'rejected']] : ['submitted' => ['under_review', 'in_progress', 'more_information_required'], 'under_review' => ['in_progress', 'resolved', 'more_information_required'], 'in_progress' => ['resolved', 'more_information_required'], 'more_information_required' => ['in_progress'], 'resolved' => ['closed', 'in_progress']];

        return DB::transaction(function () use ($s, $user, $status, $message, $allowed) {
            $s = ServiceRequest::whereKey($s->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($status, $allowed[$s->status] ?? []), 409);
            $s->update(['status' => $status]);
            $this->event($s, $user, $message);
            Audit::record('service_request.reviewed', $s->id, ['status' => $status]);
            NotifyRequest::dispatch($s->id)->afterCommit();

            return $s;
        });
    }

    public function event(ServiceRequest $s, User $user, string $message): void
    {
        DB::table('service_request_events')->insert(['service_request_id' => $s->id, 'actor_id' => $user->id, 'status' => $s->status, 'message' => $message, 'created_at' => now()]);
    }
}
