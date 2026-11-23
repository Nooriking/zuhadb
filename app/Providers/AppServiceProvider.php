<?php

namespace App\Providers;

use App\Ai\Contracts\AiProvider;
use App\Ai\Providers\OpenAiProvider;
use App\Ai\Tools\AiToolCaller;
use App\Ai\Tools\AiToolRegistry;
use App\Ai\Tools\DescribeDatabaseTableTool;
use App\Ai\Tools\GetDatabaseRelationshipsTool;
use App\Ai\Tools\GetProductStockTool;
use App\Ai\Tools\ListDatabaseTablesTool;
use App\Ai\Tools\ListLowStockTool;
use App\Ai\Tools\QueryDatabaseTool;
use App\Ai\Tools\SearchProductsTool;
use App\Listeners\LogAuthenticationEvents;
use App\Models\Customer;
use App\Models\Investor;
use App\Models\Product;
use App\Models\Sale;
use App\Policies\CustomerPolicy;
use App\Policies\InvestorPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProvider::class, function () {
            $driver = (string) config('ai.provider', 'openai');

            return match ($driver) {
                'openai' => new OpenAiProvider,
                default => throw new InvalidArgumentException("Unsupported AI provider [{$driver}]."),
            };
        });

        $this->app->singleton(AiToolRegistry::class, function ($app) {
            $registry = new AiToolRegistry;

            // Core catalog/inventory tools — required for Phase 1C/1D chat.
            $registry->register($app->make(SearchProductsTool::class));
            $registry->register($app->make(GetProductStockTool::class));
            $registry->register($app->make(ListLowStockTool::class));

            // Phase 1E database intelligence — optional; one failure must not disable chat.
            $optionalTools = [
                ListDatabaseTablesTool::class,
                DescribeDatabaseTableTool::class,
                GetDatabaseRelationshipsTool::class,
                QueryDatabaseTool::class,
            ];

            foreach ($optionalTools as $toolClass) {
                try {
                    $registry->register($app->make($toolClass));
                } catch (Throwable $e) {
                    Log::warning('ai.tools.registration_failed', [
                        'tool_class' => $toolClass,
                        'exception' => $e::class,
                        'message' => 'Optional AI database tool failed to register; chat remains available.',
                    ]);
                }
            }

            return $registry;
        });

        $this->app->singleton(AiToolCaller::class, function ($app) {
            return new AiToolCaller($app->make(AiToolRegistry::class));
        });
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
        Gate::policy(Investor::class, InvestorPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $perMinute = max(5, (int) config('business.login_max_attempts', 5) * 2);

            return Limit::perMinute($perMinute)->by($request->ip());
        });

        // Bound for Phase 1B chat routes — registered now so config is enforced consistently.
        RateLimiter::for('ai-chat', function (Request $request) {
            $perMinute = max(1, (int) config('ai.rate_limit.per_minute', 20));
            $key = $request->user()?->id ?: $request->ip();

            return Limit::perMinute($perMinute)->by('ai-chat:'.$key);
        });

        $authLogger = app(LogAuthenticationEvents::class);
        Event::listen(Login::class, [$authLogger, 'handleLogin']);
        Event::listen(Logout::class, [$authLogger, 'handleLogout']);
        Event::listen(Failed::class, [$authLogger, 'handleFailed']);
    }
}
