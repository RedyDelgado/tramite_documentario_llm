<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\OriginalController;
use App\Http\Controllers\UsuarioController;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('auth/Login', [
        'google' => GoogleController::habilitado(),
        'rolesDesarrollo' => app()->isLocal()
            ? collect(RolesSeeder::ROLES)->except('otros')->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()
            : [],
    ]))->name('login');

    Route::get('/auth/google', [GoogleController::class, 'redirigir'])->name('google.redirigir');
    Route::get('/auth/google/callback', [GoogleController::class, 'volver'])->middleware('throttle:10,1')->name('google.callback');

    // Atajo sin Google para desarrollo; nunca se registra fuera de local.
    if (app()->isLocal()) {
        Route::post('/dev/entrar', function (Request $request) {
            $rol = $request->validate(['rol' => ['required', 'in:superadmin,director,administrativo,coordinador']])['rol'];
            Auth::login(User::role($rol)->where('activo', true)->orderBy('id')->firstOrFail());
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

    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');

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
