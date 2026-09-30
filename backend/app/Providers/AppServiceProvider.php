<?php

namespace App\Providers;

use App\Contracts\AccountDirectory;
use App\Contracts\BillingSystemClient;
use App\Contracts\MalwareScanner;
use App\Contracts\NotificationProvider;
use App\Contracts\PaymentGateway;
use App\Contracts\ServiceRequestSync;
use App\Integrations\Billing\HttpBillingSystemClient;
use App\Integrations\Billing\MockBillingSystemClient;
use App\Integrations\Gateways\FakeGateway;
use App\Integrations\Gateways\UnavailableGateway;
use App\Models\User;
use App\Services\Notifications\FakeNotificationProvider;
use App\Services\Notifications\UnavailableNotificationProvider;
use App\Services\Requests\LocalOnlySync;
use App\Services\Uploads\DevelopmentScanner;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MalwareScanner::class, DevelopmentScanner::class);
        $this->app->bind(ServiceRequestSync::class, LocalOnlySync::class);

        $this->app->bind(PaymentGateway::class, fn () => app()->environment(['local', 'testing']) && config('payments.gateway') === 'fake' ? new FakeGateway : new UnavailableGateway);

        $this->app->bind(BillingSystemClient::class, fn () => config('billing.mode') === 'mock' ? new MockBillingSystemClient : new HttpBillingSystemClient);
        $this->app->bind(AccountDirectory::class, fn () => app(BillingSystemClient::class));
        $this->app->bind(NotificationProvider::class, fn () => app()->environment(['local', 'testing']) && config('portal.notifications') === 'fake' ? new FakeNotificationProvider : new UnavailableNotificationProvider);
    }

    public function boot(): void
    {
        Gate::define('access-admin', fn (User $user) => in_array($user->role, ['support', 'admin']));
        Gate::define('manage-finance', fn (User $user) => $user->role === 'admin');
        Gate::define('manage-roles', fn (User $user) => $user->role === 'admin');
        RateLimiter::for('verification', fn (Request $request) => [Limit::perMinute(5)->by('verify-ip:'.$request->ip()), Limit::perHour(20)->by('verify-user:'.($request->user()?->id ?? $request->ip()))]);
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('identity:'.hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())),
        ]);
    }
}
