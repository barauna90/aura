<?php

namespace App\Providers;

use App\Services\Billing\AsaasGateway;
use App\Services\Billing\PaymentGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Gateway de pagamento abstrato — implementação padrão: Asaas (chave configurada no painel).
        $this->app->bind(PaymentGateway::class, AsaasGateway::class);
    }

    public function boot(): void
    {
        Gate::define('reviewer', fn ($user) => $user->hasRole('REVIEWER'));
        Gate::define('admin', fn ($user) => $user->hasRole('ADMIN'));
        Vite::useAggressivePrefetching();
    }
}
