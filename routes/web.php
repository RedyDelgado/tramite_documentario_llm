<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\AtencionController;
use App\Http\Controllers\EmisorController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\FeriadoController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\IaController;
use App\Http\Controllers\InstruccionFrecuenteController;
use App\Http\Controllers\OriginalController;
use App\Http\Controllers\OriginalFisicoController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\PlazoAreaController;
use App\Http\Controllers\RegistroFisicoController;
use App\Http\Controllers\ReglaDerivacionController;
use App\Http\Controllers\ReglaNoTramiteController;
use App\Http\Controllers\ResponsableController;
use App\Http\Controllers\SalienteController;
use App\Http\Controllers\SerieController;
use App\Http\Controllers\TipoDocumentoController;
use App\Http\Controllers\TipoTramiteController;
use App\Http\Controllers\UbicacionFisicaController;
use App\Http\Controllers\UmbralController;
use App\Http\Controllers\UsuarioController;
use App\Models\Expediente;
use App\Models\User;
use App\Services\PanelService;
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
    // Panel de KPIs (8): solo quien ve expedientes; el superadmin no ve contenido de trámites (5).
    Route::get('/', fn (Request $request, PanelService $panel) => Inertia::render('Inicio', [
        'indicadores' => $request->user()->can('viewAny', Expediente::class) ? $panel->indicadores($request->user()) : null,
    ]))->name('inicio');

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
    Route::resource('ubicaciones', UbicacionFisicaController::class)->parameters(['ubicaciones' => 'ubicacion'])->except(['show', 'destroy']);
    Route::resource('reglas-no-tramite', ReglaNoTramiteController::class)->parameters(['reglas-no-tramite' => 'regla'])->except(['show', 'destroy']);
    Route::resource('plantillas', PlantillaController::class)->parameters(['plantillas' => 'plantilla'])->except(['show', 'destroy']);
    Route::get('ia', [IaController::class, 'index'])->name('ia.index');
    Route::post('ia/correcciones/{correccion}', [IaController::class, 'resolver'])->name('ia.correcciones.resolver');
    Route::get('umbrales', [UmbralController::class, 'edit'])->name('umbrales.edit');
    Route::put('umbrales', [UmbralController::class, 'update'])->name('umbrales.update');

    Route::resource('usuarios', UsuarioController::class)->except(['show', 'destroy']);
    Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');

    Route::get('registro/nuevo', [RegistroFisicoController::class, 'create'])->name('registro.create');
    Route::post('registro/prellenar', [RegistroFisicoController::class, 'prellenar'])->name('registro.prellenar');
    Route::post('registro', [RegistroFisicoController::class, 'store'])->name('registro.store');

    // Destino del QR impreso (7.3): abre el expediente; los permisos los aplica su vista.
    Route::get('qr/{codigo}', fn (string $codigo) => to_route('expedientes.show', Expediente::porCodigoEn($codigo) ?? abort(404)))->name('qr');
    Route::post('expedientes/{expediente}/original', [OriginalFisicoController::class, 'mover'])->name('expedientes.original');
    Route::get('expedientes/{expediente}/constancia', [OriginalFisicoController::class, 'constancia'])->name('expedientes.constancia');
    Route::get('expedientes/{expediente}/etiqueta', [OriginalFisicoController::class, 'etiqueta'])->name('expedientes.etiqueta');
    Route::get('movimientos/{movimiento}/cargo', [OriginalFisicoController::class, 'cargo'])->name('movimientos.cargo');
    Route::post('movimientos/{movimiento}/cargo', [OriginalFisicoController::class, 'adjuntarCargo'])->name('movimientos.cargo.adjuntar');

    Route::post('expedientes/{expediente}/serie', [SerieController::class, 'agrupar'])->name('expedientes.serie');
    Route::delete('expedientes/{expediente}/serie', [SerieController::class, 'quitar'])->name('expedientes.serie.quitar');

    Route::get('salientes/plantilla/{plantilla}', [SalienteController::class, 'plantilla'])->name('salientes.plantilla');
    Route::post('salientes/{saliente}/revision', [SalienteController::class, 'revision'])->name('salientes.revision');
    Route::post('salientes/{saliente}/devolver', [SalienteController::class, 'devolver'])->name('salientes.devolver');
    Route::post('salientes/{saliente}/aprobar', [SalienteController::class, 'aprobar'])->name('salientes.aprobar');
    Route::post('salientes/{saliente}/firmado', [SalienteController::class, 'firmado'])->name('salientes.firmado');
    Route::get('salientes/{saliente}/descargar/{formato}', [SalienteController::class, 'descargar'])->name('salientes.descargar');
    Route::resource('salientes', SalienteController::class)->parameters(['salientes' => 'saliente'])->except(['destroy']);

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
