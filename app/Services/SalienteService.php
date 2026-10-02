<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Documento;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\PlantillaDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Documentos salientes (7.3.4): ninguno sale sin aprobación explícita y auditada. */
class SalienteService
{
    public function __construct(
        private readonly SecuenciaService $secuencias,
        private readonly GeneradorDocumentoService $generador,
        private readonly AuditoriaService $auditoria,
        private readonly EnvioService $envios,
    ) {}

    /** Asunto y cuerpo de la plantilla con los datos del expediente de origen («un dato se escribe una sola vez», 7.3.5). */
    public function desdePlantilla(PlantillaDocumento $plantilla, ?Expediente $expediente, string $area): array
    {
        $expediente?->loadMissing('emisor');
        $valores = [
            '{{expediente.codigo}}' => $expediente?->codigo ?? '',
            '{{expediente.numero}}' => $expediente?->numero_registro ?? '',
            '{{expediente.asunto}}' => $expediente?->asunto ?? '',
            '{{expediente.documento}}' => $expediente?->numero_documento_original ?? $expediente?->numero_documento ?? '',
            '{{expediente.fecha}}' => $expediente?->fecha_ingreso->setTimezone(config('app.timezone'))->format('d/m/Y') ?? '',
            '{{remitente}}' => $expediente?->emisor?->nombre ?? $expediente?->remitente_nombre ?? '',
            '{{fecha}}' => now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
            '{{area}}' => $area,
        ];

        return ['asunto' => strtr($plantilla->asunto, $valores), 'cuerpo' => strtr($plantilla->cuerpo, $valores)];
    }

    /** @param array<string, mixed> $datos validados por SalienteRequest */
    public function crear(array $datos, User $autor): DocumentoSaliente
    {
        return DB::transaction(function () use ($datos, $autor) {
            $saliente = DocumentoSaliente::create([...$datos, 'creado_por' => $autor->id]);
            $this->auditoria->registrar('saliente.creado', $saliente, despues: ['expediente_id' => $saliente->expediente_id, 'asunto' => $saliente->asunto]);

            return $saliente;
        });
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(DocumentoSaliente $saliente, array $datos): DocumentoSaliente
    {
        $this->exigirEstado($saliente, ['borrador'], 'editar');

        return DB::transaction(function () use ($saliente, $datos) {
            $saliente->update($datos);
            $this->auditoria->registrarCambios('saliente.editado', $saliente);

            return $saliente;
        });
    }

    public function enviarARevision(DocumentoSaliente $saliente): void
    {
        $this->exigirEstado($saliente, ['borrador'], 'enviar a revisión');
        $this->cambiarEstado($saliente, 'en_revision', 'saliente.en_revision');
    }

    /** Devuelve al borrador con la observación de quien revisa. */
    public function devolver(DocumentoSaliente $saliente, string $observacion, User $revisor): void
    {
        $this->exigirEstado($saliente, ['en_revision'], 'devolver');
        $saliente->forceFill(['observacion' => $observacion])->save();
        $this->cambiarEstado($saliente, 'borrador', 'saliente.devuelto', ['observacion' => $observacion, 'revisor' => $revisor->id]);
    }

    /**
     * Aprobación explícita: numera (tipo, área, año) y genera el PDF final en la misma transacción (7.3.4, 6.2).
     *
     * @throws ReglaDeNegocio
     */
    public function aprobar(DocumentoSaliente $saliente, User $aprobador): DocumentoSaliente
    {
        $saliente = DB::transaction(function () use ($saliente, $aprobador) {
            $saliente = DocumentoSaliente::lockForUpdate()->with(['tipoDocumento', 'area'])->findOrFail($saliente->id);
            $this->exigirEstado($saliente, ['en_revision'], 'aprobar');

            $anio = now()->year;
            $secuencia = $this->secuencias->siguiente("saliente:{$saliente->tipo_documento_id}:{$saliente->area_id}", $anio);
            $saliente->forceFill([
                'anio' => $anio,
                'secuencia' => $secuencia,
                'numero' => $this->formatear($saliente, $secuencia, $anio),
                'estado' => 'aprobado',
                'aprobado_por' => $aprobador->id,
                'aprobado_at' => now(),
                'observacion' => null,
            ]);
            ['ruta' => $ruta, 'sha256' => $sha] = Documento::guardarArchivo($this->generador->pdf($saliente));
            $saliente->forceFill(['ruta_pdf' => $ruta, 'sha256_pdf' => $sha])->save();

            $this->auditoria->registrar('saliente.aprobado', $saliente, antes: ['estado' => 'en_revision'], despues: [
                'estado' => 'aprobado', 'numero' => $saliente->numero, 'aprobado_por' => $aprobador->id, 'sha256_pdf' => $sha,
            ]);

            return $saliente;
        });

        // Envío automático: aprobado, sale sin intervención manual, salvo que espere el PDF firmado (7.3.4).
        if (! $saliente->esperar_firma) {
            $this->envios->despachar($saliente);
        }

        return $saliente;
    }

    /** «OFICIO N.º 012-2026-DGA»: formato del tipo de documento (5.1, 7.3.4). */
    public function formatear(DocumentoSaliente $s, int $secuencia, int $anio): string
    {
        $siglas = $s->area->siglas ?: Str::of($s->area->nombre)->ascii()->upper()->explode(' ')
            ->filter(fn ($p) => mb_strlen($p) > 2)->map(fn ($p) => $p[0])->implode('');

        return strtr($s->tipoDocumento->formato_numero, [
            '{TIPO}' => Str::upper($s->tipoDocumento->nombre),
            '{NUMERO}' => sprintf('%03d', $secuencia),
            '{ANIO}' => (string) $anio,
            '{AREA}' => $siglas,
        ]);
    }

    /** @param list<string> $estados */
    private function exigirEstado(DocumentoSaliente $s, array $estados, string $verbo): void
    {
        if (! in_array($s->estado, $estados, true)) {
            throw new ReglaDeNegocio('Un documento '.mb_strtolower(DocumentoSaliente::ESTADOS[$s->estado])." no se puede {$verbo}.");
        }
    }

    private function cambiarEstado(DocumentoSaliente $s, string $estado, string $accion, array $extra = []): void
    {
        $antes = $s->estado;
        $s->forceFill(['estado' => $estado])->save();
        $this->auditoria->registrar($accion, $s, antes: ['estado' => $antes], despues: ['estado' => $estado, ...$extra]);
    }
}
