<?php

use App\Jobs\ReconcilePayment;
use App\Jobs\SyncPayment;
use App\Models\PaymentAttempt;
use App\Models\PaymentOutbox;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('portal:demo-user {email} {--admin}', function () {
    if (! app()->environment('local')) {
        $this->error('Demo users are local-only.');

        return 1;
    }
    $password = $this->secret('Choose a local password (at least 12 characters)');
    $input = ['email' => $this->argument('email'), 'password' => $password];
    $validator = Validator::make($input, ['email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $user = new User(['name' => 'Demo resident', ...$input]);
    $user->role = $this->option('admin') ? 'admin' : 'client';
    $user->save();
    $this->info('Local user created. No billing account is linked.');
})->purpose('Create a local-only user with an interactively entered password');

Artisan::command('portal:reconcile', function () {
    PaymentAttempt::whereIn('status', ['initiated', 'pending'])->where('sync_status', '!=', 'needs_review')->orderBy('created_at')->limit(100)->get()->each(fn ($p) => ReconcilePayment::dispatch($p->id));
    PaymentOutbox::whereNotNull('available_at')->where('available_at', '<=', now())->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))->limit(100)->get()->each(fn ($o) => SyncPayment::dispatch($o->payment_id));
})->purpose('Queue bounded gateway and billing reconciliation');
Schedule::command('portal:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('portal:demo', function () {
    if (! app()->environment('local') || config('billing.mode') !== 'mock') {
        $this->error('Local mock mode required.');

        return 1;
    }
    $password = $this->secret('Choose a password for the three synthetic demo accounts (12+ characters)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Password too short.');

        return 1;
    }
    foreach (['resident' => 'client', 'support' => 'support', 'admin' => 'admin'] as $name => $role) {
        $email = $name.'@example.invalid';
        if (User::where('email', $email)->exists()) {
            $this->line($email.' already exists; unchanged.');

            continue;
        }
        $user = new User(['name' => 'Demo '.$name, 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->save();
        $this->line($email.' created.');
    }
    $this->info('Synthetic account 000007 / customer 000042; alternative 000008 / 000043. Local fake OTP: 123456. No accounts were auto-linked.');
})->purpose('Create three synthetic local users without preset credentials or bypassing ownership proof');
