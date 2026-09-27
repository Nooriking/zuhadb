<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvents;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Policies\CustomerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Password::defaults(function () {
            return Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        // Until a Gmail App Password is set, keep password-reset codes in the log.
        if (config('mail.default') === 'smtp' && blank(config('mail.mailers.smtp.password'))) {
            config(['mail.default' => 'log']);
        }

        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $perMinute = max(5, (int) config('business.login_max_attempts', 5) * 2);

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        $authLogger = app(LogAuthenticationEvents::class);
        Event::listen(Login::class, [$authLogger, 'handleLogin']);
        Event::listen(Logout::class, [$authLogger, 'handleLogout']);
        Event::listen(Failed::class, [$authLogger, 'handleFailed']);
    }
}
