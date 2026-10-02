<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\FeriadoController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\InstruccionFrecuenteController;
use App\Http\Controllers\OriginalController;
use App\Http\Controllers\PlazoAreaController;
use App\Http\Controllers\ReglaDerivacionController;
use App\Http\Controllers\ReglaNoTramiteController;
use App\Http\Controllers\ResponsableController;
use App\Http\Controllers\TipoDocumentoController;
use App\Http\Controllers\TipoTramiteController;
use App\Http\Controllers\UmbralController;
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
    Route::post('areas/{area}/fusionar', [AreaController::class, 'fusionar'])->name('areas.fusionar');
    Route::resource('responsables', ResponsableController::class)->except(['show', 'destroy']);

    Route::resource('tipos-tramite', TipoTramiteController::class)->parameters(['tipos-tramite' => 'tipo'])->except(['show', 'destroy']);
    Route::patch('tipos-tramite/{tipo}/estado', [TipoTramiteController::class, 'cambiarEstado'])->name('tipos-tramite.estado');
    Route::resource('plazos', PlazoAreaController::class)->except(['show']);
    Route::resource('feriados', FeriadoController::class)->except(['show']);
    Route::resource('reglas-derivacion', ReglaDerivacionController::class)->parameters(['reglas-derivacion' => 'regla'])->except(['show', 'destroy']);
    Route::patch('reglas-derivacion/{regla}/estado', [ReglaDerivacionController::class, 'cambiarEstado'])->name('reglas-derivacion.estado');
    Route::post('emisores/rapido', [EmisorController::class, 'rapido'])->name('emisores.rapido');
    Route::post('emisores/{emisor}/fusionar', [EmisorController::class, 'fusionar'])->name('emisores.fusionar');
    Route::resource('emisores', EmisorController::class)->parameters(['emisores' => 'emisor'])->except(['show', 'destroy']);
    Route::resource('tipos-documento', TipoDocumentoController::class)->parameters(['tipos-documento' => 'tipo'])->except(['show', 'destroy']);
    Route::resource('instrucciones', InstruccionFrecuenteController::class)->parameters(['instrucciones' => 'instruccion'])->except(['show', 'destroy']);
    Route::resource('reglas-no-tramite', ReglaNoTramiteController::class)->parameters(['reglas-no-tramite' => 'regla'])->except(['show', 'destroy']);
    Route::get('umbrales', [UmbralController::class, 'edit'])->name('umbrales.edit');
    Route::put('umbrales', [UmbralController::class, 'update'])->name('umbrales.update');

    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');

    Route::get('expedientes', [ExpedienteController::class, 'index'])->name('expedientes.index');
    Route::get('expedientes/{expediente}', [ExpedienteController::class, 'show'])->name('expedientes.show');
    Route::post('expedientes/{expediente}/confirmar', [ExpedienteController::class, 'confirmar'])->name('expedientes.confirmar');
    Route::post('expedientes/{expediente}/no-tramite', [ExpedienteController::class, 'noTramite'])->name('expedientes.no-tramite');
    Route::post('expedientes/{expediente}/devolver', [ExpedienteController::class, 'devolver'])->name('expedientes.devolver');
    Route::post('expedientes/{expediente}/anular', [ExpedienteController::class, 'anular'])->name('expedientes.anular');
    Route::post('expedientes/{expediente}/derivar', [AtencionController::class, 'derivar'])->name('expedientes.derivar');
    Route::post('expedientes/{expediente}/tomar', [AtencionController::class, 'tomar'])->name('expedientes.tomar');
    Route::post('expedientes/{expediente}/comentar', [AtencionController::class, 'comentar'])->name('expedientes.comentar');
    Route::post('expedientes/{expediente}/solicitar-cierre', [AtencionController::class, 'solicitarCierre'])->name('expedientes.solicitar-cierre');
    Route::post('expedientes/{expediente}/resolver-cierre', [AtencionController::class, 'resolverCierre'])->name('expedientes.resolver-cierre');

    Route::get('documentos/{documento}/descargar', [OriginalController::class, 'documento'])->name('documentos.descargar');
    Route::get('correos/{correo}/eml', [OriginalController::class, 'correo'])->name('correos.eml');

    // Catálogo de componentes (5.2): referencia de diseño, solo en local.
    if (app()->isLocal()) {
        Route::get('/ui', fn () => Inertia::render('ui/Catalogo'))->name('ui');
    }
});
