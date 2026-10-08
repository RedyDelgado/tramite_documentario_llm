<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Area;
use App\Models\Documento;
use App\Models\DocumentoSaliente;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Documentos salientes (7.3.4): ninguno sale sin aprobación explícita y auditada. */
class SalienteService
{
    public function __construct(
        private readonly SecuenciaService $secuencias,
        private readonly AuditoriaService $auditoria,
        private readonly EnvioService $envios,
    ) {}

    /** @param array<string, mixed> $datos validados por SalienteRequest */
    public function crear(array $datos, UploadedFile $borrador, User $autor): DocumentoSaliente
    {
        return DB::transaction(function () use ($datos, $borrador, $autor) {
            $saliente = new DocumentoSaliente([...$datos, 'creado_por' => $autor->id]);
            $saliente->forceFill($this->guardarBorrador($borrador))->save();
            $this->auditoria->registrar('saliente.creado', $saliente, despues: ['expediente_id' => $saliente->expediente_id, 'asunto' => $saliente->asunto]);

            return $saliente;
        });
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(DocumentoSaliente $saliente, array $datos, ?UploadedFile $borrador = null): DocumentoSaliente
    {
        $this->exigirEstado($saliente, ['borrador'], 'editar');

        return DB::transaction(function () use ($saliente, $datos, $borrador) {
            $saliente->fill($datos);
            if ($borrador) {
                $saliente->forceFill($this->guardarBorrador($borrador));
            }
            $saliente->save();
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
     * Aprobación explícita: numera (tipo, área, año) en una transacción (7.3.4, 6.2). El número va luego en el Word,
     * y sale el documento final que se suba con él.
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
                'numero' => $this->formatear($saliente->tipoDocumento, $saliente->area, $secuencia, $anio),
                'estado' => 'aprobado',
                'aprobado_por' => $aprobador->id,
                'aprobado_at' => now(),
                'observacion' => null,
            ]);
            $saliente->save();

            $this->auditoria->registrar('saliente.aprobado', $saliente, antes: ['estado' => 'en_revision'], despues: [
                'estado' => 'aprobado', 'numero' => $saliente->numero, 'aprobado_por' => $aprobador->id, 'sha256_borrador' => $saliente->sha256_borrador,
            ]);

            return $saliente;
        });

        // Sale solo cuando se suba el documento final con su número (EnvioService::subirFirmado).
        if (! $saliente->esperar_firma) {
            $this->envios->despachar($saliente);
        }

        return $saliente;
    }

    /** «OFICIO N.º 012-2026-DGA»: formato del tipo de documento (5.1, 7.3.4). */
    public function formatear(TipoDocumento $tipo, Area $area, int $secuencia, int $anio): string
    {
        $siglas = $area->siglas ?: Str::of($area->nombre)->ascii()->upper()->explode(' ')
            ->filter(fn ($p) => mb_strlen($p) > 2)->map(fn ($p) => $p[0])->implode('');

        return strtr($tipo->formato_numero, [
            '{TIPO}' => Str::upper($tipo->nombre),
            '{NUMERO}' => sprintf('%03d', $secuencia),
            '{ANIO}' => (string) $anio,
            '{AREA}' => $siglas,
        ]);
    }

    /** @return array{ruta_borrador: string, sha256_borrador: string, nombre_borrador: string} */
    private function guardarBorrador(UploadedFile $archivo): array
    {
        ['ruta' => $ruta, 'sha256' => $sha] = Documento::guardarArchivo($archivo->getContent());

        return ['ruta_borrador' => $ruta, 'sha256_borrador' => $sha, 'nombre_borrador' => $archivo->getClientOriginalName()];
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
