<?php

namespace App\Http\Controllers;

use App\Jobs\SyncPayment;
use App\Models\AuditLog;
use App\Models\ExternalAccountLink;
use App\Models\PaymentAttempt;
use App\Models\PaymentOutbox;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Audit;
use App\Services\Payments\ReconcileService;
use App\Services\Payments\SyncService;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminController extends Controller
{
    public function summary()
    {
        Gate::authorize('access-admin');

        return response()->json(['data' => ['scope' => 'portal_only_excludes_bank_and_offline', 'currency' => 'BDT', 'verified_collections_minor' => (string) PaymentAttempt::where('status', 'succeeded')->sum('amount_minor'), 'posted_upstream_minor' => (string) PaymentAttempt::where('status', 'succeeded')->where('sync_status', 'synced')->sum('amount_minor'), 'pending_gateway' => PaymentAttempt::whereIn('status', ['initiated', 'pending'])->count(), 'awaiting_sync' => PaymentAttempt::where('status', 'succeeded')->where('sync_status', '!=', 'synced')->count(), 'open_applications' => ServiceRequest::where('kind', 'application')->whereNotIn('status', ['approved', 'rejected'])->count(), 'open_complaints' => ServiceRequest::where('kind', 'complaint')->whereNotIn('status', ['closed', 'resolved'])->count(), 'upstream_municipal_totals' => null]]);
    }

    public function listing(Request $r, string $module)
    {
        Gate::authorize('access-admin');
        $d = $r->validate(['q' => 'nullable|string|max:128', 'status' => 'nullable|string|max:32', 'sync_status' => 'nullable|string|max:32', 'kind' => 'nullable|in:application,complaint', 'role' => 'nullable|in:client,support,admin', 'page' => 'sometimes|integer|min:1']);
        $q = $this->query($module, $d);
        $p = $q->paginate(15);

        return response()->json(['data' => $p->items(), 'meta' => ['pagination' => ['page' => $p->currentPage(), 'per_page' => 15, 'total' => $p->total(), 'has_next' => $p->hasMorePages()]]]);
    }

    private function query(string $module, array $d)
    {
        $q = match ($module) {
            'users' => User::query()->select('id', 'name', 'email', 'role', 'created_at'),'account-links' => ExternalAccountLink::query(),'payments' => PaymentAttempt::query()->select('id', 'user_id', 'reference', 'external_bill_id', 'external_account_id', 'amount_minor', 'currency', 'status', 'sync_status', 'review_reason', 'created_at'),'service-requests' => ServiceRequest::query(),'audit-logs' => AuditLog::query(),default => abort(404)
        };
        foreach (['status', 'sync_status', 'kind', 'role'] as $key) {
            if (! empty($d[$key]) && match ($key) {
                'status' => in_array($module, ['payments', 'service-requests', 'account-links']),'sync_status' => $module === 'payments','kind' => $module === 'service-requests','role' => $module === 'users'
            }) {
                $q->where($key, $d[$key]);
            }
        }
        if (! empty($d['q'])) {
            $column = match ($module) {
                'users' => 'email','account-links' => 'external_account_id','audit-logs' => 'event',default => 'reference'
            };
            $q->where($column, 'like', '%'.addcslashes($d['q'], '%_\\').'%');
        }

        return $q->orderByDesc($module === 'audit-logs' ? 'id' : 'created_at');
    }

    public function role(Request $r, User $user)
    {
        Gate::authorize('manage-roles');
        $d = $r->validate(['role' => 'required|in:client,support,admin']);
        DB::transaction(function () use ($user, $d) {
            $admins = User::where('role', 'admin')->lockForUpdate()->get();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($user->role === 'admin' && $d['role'] !== 'admin' && $admins->count() <= 1, 409);
            $old = $user->role;
            $user->forceFill(['role' => $d['role']])->save();
            Audit::record('user.role_changed', (string) $user->id, ['from' => $old, 'to' => $d['role']]);
        });

        return response()->json(['data' => null]);
    }

    public function revoke(ExternalAccountLink $link)
    {
        Gate::authorize('manage-roles');
        $link->update(['status' => 'revoked', 'revoked_at' => now()]);
        Audit::record('account_link.admin_revoked', (string) $link->id);

        return response()->json(['data' => null]);
    }

    public function retry(PaymentAttempt $payment)
    {
        Gate::authorize('reconcile', $payment);
        abort_unless($payment->status === 'succeeded' && $payment->sync_status === 'failed', 409);
        $o = PaymentOutbox::where('payment_id', $payment->id)->firstOrFail();
        abort_if($o->attempts >= config('payments.max_sync_attempts'), 409);
        $o->update(['available_at' => now()]);
        Audit::record('payment.sync_retry', $payment->id);
        SyncPayment::dispatch($payment->id);

        return response()->json(['data' => ['queued' => true]], 202);
    }

    public function reconcile(PaymentAttempt $payment, SyncService $sync, ReconcileService $gateway)
    {
        Gate::authorize('reconcile', $payment);
        Audit::record('payment.reconcile', $payment->id);
        if ($payment->status === 'succeeded') {
            $sync->lookupOnly($payment);
        } else {
            $gateway->run($payment);
        }

        return response()->json(['data' => null]);
    }

    public function events(PaymentAttempt $payment)
    {
        Gate::authorize('view', $payment);
        Gate::authorize('access-admin');
        $p = DB::table('payment_events')->where('payment_id', $payment->id)->orderByDesc('id')->paginate(20);

        return response()->json(['data' => $p->items(), 'meta' => ['pagination' => ['page' => $p->currentPage(), 'per_page' => 20, 'total' => $p->total(), 'has_next' => $p->hasMorePages()]]]);
    }

    public function export(Request $r)
    {
        $admin = $r->is('api/v1/admin/*');
        if ($admin) {
            Gate::authorize('manage-finance');
        }$d = $r->validate(['q' => 'nullable|string|max:128', 'status' => 'nullable|in:initiated,pending,succeeded,failed,cancelled,expired', 'sync_status' => 'nullable|in:not_required,pending,syncing,synced,failed,needs_review']);
        $q = PaymentAttempt::query()->orderBy('created_at');
        if (! $admin) {
            $q->where('user_id', $r->user()->id);
        }foreach ($d as $k => $v) {
            if ($v) {
                $k === 'q' ? $q->where('reference', 'like', '%'.addcslashes($v, '%_\\').'%') : $q->where($k, $v);
            }
        }$rows = $q->limit(1000)->get();
        Audit::record('payments.export', null, ['scope' => $admin ? 'admin' : 'self', 'rows' => $rows->count(), 'limit' => 1000]);

        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, ['Reference', 'Bill', 'Account', 'Minor units', 'Currency', 'Gateway status', 'Billing sync'], ',', '"', '');
            foreach ($rows as $p) {
                fputcsv($f, array_map([Csv::class, 'cell'], [$p->reference, $p->external_bill_id, $p->external_account_id, $p->amount_minor, $p->currency, $p->status, $p->sync_status]), ',', '"', '');
            }fclose($f);
        }, 'portal-payments.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Export-Limit' => '1000']);
    }
}
