<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Documento;
use App\Models\Emisor;
use App\Models\Expediente;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Registro de un documento en papel (7.3, 7.3.5): escaneo → texto → formulario prellenado → número. */
class RegistroFisicoService
{
    // El escaneo subido espera su confirmación un día; después hay que volver a subirlo.
    private const ESPERA = 86_400;

    public function __construct(
        private readonly TextoDocumentoService $texto,
        private readonly OcrService $ocr,
        private readonly ExtraccionService $extraccion,
        private readonly ExpedienteService $expedientes,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * Guarda el escaneo y propone los campos. El OCR aquí es síncrono para prellenar; si el servicio de IA no responde, el formulario queda vacío y el OCR se encola al registrar.
     *
     * @return array{sha256: string, nombre: string, paginas: ?int, campos: array<string, mixed>, mismo_archivo: ?array{id: int, numero: ?string}}
     */
    public function prellenar(UploadedFile $archivo): array
    {
        $mime = $archivo->getMimeType();
        ['ruta' => $ruta, 'sha256' => $sha256] = Documento::guardarArchivo($archivo->getContent());
        $rutaAbsoluta = Storage::disk('originales')->path($ruta);

        $texto = $this->texto->extraer($rutaAbsoluta, $mime);
        $paginas = $this->texto->paginas($rutaAbsoluta, $mime);
        $porOcr = false;
        if ($texto === null && in_array($mime, OcrService::MIMES, true)) {
            try {
                $resultado = $this->ocr->reconocer($rutaAbsoluta, $mime);
                $texto = trim($resultado['texto']) ?: null;
                $paginas ??= $resultado['paginas'];
                $porOcr = $texto !== null;
            } catch (Throwable) {
                // Sin IA no hay prellenado, pero se puede registrar a mano (principio 2).
            }
        }

        Cache::put("registro-fisico:{$sha256}", [
            'ruta' => $ruta, 'mime' => $mime, 'nombre' => $archivo->getClientOriginalName(), 'paginas' => $paginas,
            'texto' => $texto, 'por_ocr' => $porOcr,
        ], self::ESPERA);

        $campos = $texto ? $this->extraccion->extraer($texto) : [];
        $igual = Documento::where('sha256', $sha256)->with('expediente')->first()?->expediente;

        return [
            'sha256' => $sha256,
            'nombre' => $archivo->getClientOriginalName(),
            'paginas' => $paginas,
            'campos' => $campos + ['folios' => $paginas],
            'mismo_archivo' => $igual ? ['id' => $igual->id, 'numero' => $igual->numero_registro] : null,
        ];
    }

    /**
     * Crea el expediente con número de registro, dentro de la transacción que comprueba duplicados (7.3.5).
     *
     * @param  array<string, mixed>  $datos  validados por RegistroFisicoRequest
     *
     * @throws ValidationException si es un duplicado
     */
    public function registrar(array $datos): Expediente
    {
        $archivo = Cache::get("registro-fisico:{$datos['sha256']}");
        if (! $archivo) {
            throw ValidationException::withMessages(['archivo' => 'El escaneo ya no está disponible; vuelve a subirlo.']);
        }
        $numero = isset($datos['numero_documento']) ? ExtraccionService::normalizarNumero($datos['numero_documento']) : null;

        try {
            $expediente = DB::transaction(function () use ($datos, $archivo, $numero) {
                $this->comprobarDuplicados($datos, $numero);

                $emisor = isset($datos['emisor_id']) ? Emisor::find($datos['emisor_id']) : null;
                $expediente = Expediente::create([
                    'origen' => OrigenExpediente::Fisico,
                    'estado' => EstadoExpediente::PorRevisar,
                    'asunto' => $datos['asunto'],
                    'remitente_nombre' => $emisor?->nombre,
                    'fecha_ingreso' => now(),
                    'tipo_documento_id' => $datos['tipo_documento_id'] ?? null,
                    'numero_documento' => $numero,
                    'numero_documento_original' => $datos['numero_documento'] ?? null,
                    'fecha_documento' => $datos['fecha_documento'] ?? null,
                    'folios' => $datos['folios'],
                    'motivo_folios' => $datos['motivo_folios'] ?? null,
                    'requiere_respuesta' => $datos['requiere_respuesta'],
                ]);
                Documento::create([
                    'expediente_id' => $expediente->id,
                    'nombre_original' => $archivo['nombre'],
                    'ruta' => $archivo['ruta'],
                    'mime' => $archivo['mime'],
                    'tamano' => Storage::disk('originales')->size($archivo['ruta']),
                    'paginas' => $archivo['paginas'],
                    'sha256' => $datos['sha256'],
                    'texto_extraido' => $archivo['texto'],
                    'es_adjunto' => false,
                ])->forceFill(['texto_por_ocr' => $archivo['por_ocr']])->save();
                $this->auditoria->registrar('expediente.creado', $expediente, despues: [
                    'origen' => 'fisico', 'sha256' => $datos['sha256'], 'folios' => $datos['folios'],
                ]);

                // Mismo número y auditoría que un correo confirmado (6.2).
                return $this->expedientes->confirmar($expediente, ['emisor_id' => $datos['emisor_id'] ?? null, 'tipo_documento_id' => $datos['tipo_documento_id'] ?? null]);
            });
        } catch (UniqueConstraintViolationException) {
            // Dos registros simultáneos del mismo documento: el índice único es la última barrera.
            $this->comprobarDuplicados($datos, $numero);
            throw ValidationException::withMessages(['numero_documento' => 'Este documento se acaba de registrar.']);
        }

        Cache::forget("registro-fisico:{$datos['sha256']}");

        return $expediente;
    }

    /** Clave de negocio: bloquea. Mismo archivo o mismo emisor, asunto y fecha: avisa hasta que se confirme. */
    private function comprobarDuplicados(array $datos, ?string $numero): void
    {
        $vigentes = fn () => Expediente::where('estado', '!=', EstadoExpediente::Anulado);

        if ($numero && isset($datos['emisor_id'], $datos['tipo_documento_id'], $datos['fecha_documento'])) {
            $existente = $vigentes()->where('emisor_id', $datos['emisor_id'])->where('tipo_documento_id', $datos['tipo_documento_id'])
                ->where('numero_documento', $numero)
                ->whereRaw('EXTRACT(YEAR FROM fecha_documento) = ?', [(int) substr($datos['fecha_documento'], 0, 4)])
                ->first();
            if ($existente) {
                throw ValidationException::withMessages([
                    'numero_documento' => "Ya registrado como {$existente->numero_registro} ({$existente->codigo}).",
                    'duplicado_id' => (string) $existente->id,
                ]);
            }
        }

        if ($datos['confirmar_duplicado'] ?? false) {
            return;
        }
        if (Documento::where('sha256', $datos['sha256'])->exists()) {
            throw ValidationException::withMessages(['duplicado' => 'Este mismo archivo ya está en otro expediente. Si es otro documento, confirma para registrarlo.']);
        }
        if (isset($datos['emisor_id'], $datos['fecha_documento'])
            && $vigentes()->where('emisor_id', $datos['emisor_id'])->whereDate('fecha_documento', $datos['fecha_documento'])
                ->whereRaw('lower(asunto) = lower(?)', [$datos['asunto']])->exists()) {
            throw ValidationException::withMessages(['duplicado' => 'Ya hay un documento del mismo emisor, asunto y fecha. Si es otro, confirma para registrarlo.']);
        }
    }
}
