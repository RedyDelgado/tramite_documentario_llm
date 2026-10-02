<?php

namespace App\Services;

use App\Models\Area;
use App\Models\ClasificacionIa;
use App\Models\Configuracion;
use App\Models\Expediente;
use App\Models\TipoTramite;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Clasificación con la IA local (10): propone área y tipo; las reglas y las personas deciden. */
class ClasificacionService
{
    // Texto enviado a la IA: el encabezado y el cuerpo bastan; el resto solo añade ruido y latencia.
    private const MAX_TEXTO = 20_000;

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Clasifica el expediente salvo que su texto no haya cambiado desde la última vez.
     *
     * @throws ConnectionException si el servicio de IA no responde (el job reintenta)
     * @throws RequestException
     */
    public function clasificar(Expediente $expediente): ?ClasificacionIa
    {
        $texto = trim($expediente->asunto."\n".$expediente->textoCompleto(self::MAX_TEXTO));
        $hash = hash('sha256', $texto);
        if ($expediente->clasificaciones()->latest('id')->value('texto_sha256') === $hash) {
            return null;
        }

        // Catálogo vigente en cada petición: un área nueva cuenta de inmediato y una inactiva nunca vuelve (10).
        $respuesta = Http::baseUrl(config('tramite.ai.url'))
            ->withHeaders(['X-AI-Token' => (string) config('tramite.ai.token')])
            ->timeout(120)
            ->post('/classify', [
                'texto' => $texto,
                'areas' => Area::where('activa', true)->get(['id', 'nombre', 'descripcion', 'palabras_clave'])->toArray(),
                'tipos' => TipoTramite::where('activo', true)->get(['id', 'nombre', 'descripcion'])->toArray(),
            ])
            ->throw()
            ->json();

        $clasificacion = ClasificacionIa::create([
            'expediente_id' => $expediente->id,
            'modelo' => $respuesta['modelo'],
            'version' => $respuesta['version'],
            'texto_sha256' => $hash,
            'resultado' => $respuesta,
            'area_id' => $respuesta['area']['id'] ?? null,
            'confianza_area' => $respuesta['area']['confianza'] ?? null,
            'tipo_tramite_id' => $respuesta['tipo']['id'] ?? null,
            'confianza_tipo' => $respuesta['tipo']['confianza'] ?? null,
            'modo' => Configuracion::valor('ia.modo'),
            'created_at' => now(),
        ]);
        $this->auditoria->registrar('ia.clasificado', $expediente, despues: [
            'clasificacion_id' => $clasificacion->id, 'version' => $clasificacion->version, 'modo' => $clasificacion->modo,
            'area_id' => $clasificacion->area_id, 'confianza_area' => $clasificacion->confianza_area,
            'tipo_tramite_id' => $clasificacion->tipo_tramite_id, 'confianza_tipo' => $clasificacion->confianza_tipo,
        ], actor: 'ia');

        return $clasificacion;
    }

    /**
     * Compara la última propuesta con la primera decisión humana (la derivación): es la medida del modo sombra.
     *
     * @return ClasificacionIa|null la clasificación decidida, si había una pendiente
     */
    public function registrarDecision(Expediente $expediente, ?User $user): ?ClasificacionIa
    {
        $clasificacion = $expediente->clasificaciones()->whereNull('decidido_at')->latest('id')->first();
        if (! $clasificacion) {
            return null;
        }

        $clasificacion->forceFill([
            'area_final_id' => $expediente->area_principal_id,
            'tipo_final_id' => $expediente->tipo_tramite_id,
            'acierto_area' => $clasificacion->area_id === null ? null : $clasificacion->area_id === $expediente->area_principal_id,
            'acierto_tipo' => $clasificacion->tipo_tramite_id === null ? null : $clasificacion->tipo_tramite_id === $expediente->tipo_tramite_id,
            'decidido_por' => $user?->id,
            'decidido_at' => now(),
        ])->save();

        return $clasificacion;
    }

    /**
     * Propuesta visible al derivar: solo en modo activo y sobre el umbral, y solo si el área y el tipo siguen vigentes.
     *
     * @return array{area_id: ?int, tipo_tramite_id: ?int, confianza_area: ?float, confianza_tipo: ?float, alta: bool}|null
     */
    public function sugerencia(Expediente $expediente): ?array
    {
        if (Configuracion::valor('ia.modo') !== 'activo') {
            return null;
        }
        $c = $expediente->clasificaciones()->latest('id')->first();
        $umbral = (float) Configuracion::valor('ia.umbral_sugerencia');
        if (! $c) {
            return null;
        }
        $area = $c->confianza_area >= $umbral && Area::whereKey($c->area_id)->where('activa', true)->exists() ? $c->area_id : null;
        $tipo = $c->confianza_tipo >= $umbral && TipoTramite::whereKey($c->tipo_tramite_id)->where('activo', true)->exists() ? $c->tipo_tramite_id : null;
        if (! $area && ! $tipo) {
            return null;
        }

        return [
            'area_id' => $area,
            'tipo_tramite_id' => $tipo,
            'confianza_area' => $area ? $c->confianza_area : null,
            'confianza_tipo' => $tipo ? $c->confianza_tipo : null,
            'alta' => min(array_filter([$area ? $c->confianza_area : null, $tipo ? $c->confianza_tipo : null])) >= (float) Configuracion::valor('ia.umbral_alta'),
        ];
    }
}
