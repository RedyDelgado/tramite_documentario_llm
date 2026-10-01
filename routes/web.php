<?php

use App\Http\Controllers\AreaController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('auth/Login'))->name('login');

    // Solo local: el inicio de sesión con Google llega en la fase 2; nunca se registra fuera de local.
    if (app()->isLocal()) {
        Route::post('/dev/entrar', function (Request $request) {
            Auth::login(User::role('superadmin')->firstOrFail());
            $request->session()->regenerate();

            return to_route('inicio');
        })->name('dev.entrar');
    }
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => Inertia::render('Inicio'))->name('inicio');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    })->name('logout');

    Route::resource('areas', AreaController::class)->except(['show', 'destroy']);
    Route::patch('areas/{area}/estado', [AreaController::class, 'cambiarEstado'])->name('areas.estado');

    // Catálogo de componentes (5.2): referencia de diseño, solo en local.
    if (app()->isLocal()) {
        Route::get('/ui', fn () => Inertia::render('ui/Catalogo'))->name('ui');
    }
});
