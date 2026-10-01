<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /** Solo el superadmin ve Horizon, también en local (el paquete lo abre a todos en local). */
    protected function authorization(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null) => (bool) $user?->hasRole('superadmin'));

        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }
}
