<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\OriginalController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('auth/Login', [
        'rolesDesarrollo' => app()->isLocal() ? ['superadmin', 'director', 'administrativo', 'coordinador'] : [],
    ]))->name('login');

    // Solo local: el inicio de sesión con Google llega en la fase 2; nunca se registra fuera de local.
    if (app()->isLocal()) {
        Route::post('/dev/entrar', function (Request $request) {
            $rol = $request->validate(['rol' => ['required', 'in:superadmin,director,administrativo,coordinador']])['rol'];
            Auth::login(User::role($rol)->orderBy('id')->firstOrFail());
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

    Route::get('expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
    Route::get('expedientes/{expediente}', [ExpedienteController::class, 'show'])->name('expedientes.show');
    Route::post('expedientes/{expediente}/confirmar', [ExpedienteController::class, 'confirmar'])->name('expedientes.confirmar');
    Route::post('expedientes/{expediente}/no-tramite', [ExpedienteController::class, 'noTramite'])->name('expedientes.no-tramite');
    Route::post('expedientes/{expediente}/devolver', [ExpedienteController::class, 'devolver'])->name('expedientes.devolver');
    Route::post('expedientes/{expediente}/anular', [ExpedienteController::class, 'anular'])->name('expedientes.anular');

    Route::get('documentos/{documento}/descargar', [OriginalController::class, 'documento'])->name('documentos.descargar');
    Route::get('correos/{correo}/eml', [OriginalController::class, 'correo'])->name('correos.eml');

    // Catálogo de componentes (5.2): referencia de diseño, solo en local.
    if (app()->isLocal()) {
        Route::get('/ui', fn () => Inertia::render('ui/Catalogo'))->name('ui');
    }
});
