<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Emisor;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;

/** Fusión de emisores duplicados (7.3.5); el alta y la edición van por CatalogoService. */
class EmisorService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Pasa al destino los expedientes del duplicado y lo deja fusionado e inactivo.
     *
     * @return int expedientes reasignados
     *
     * @throws ReglaDeNegocio
     */
    public function fusionar(Emisor $duplicado, Emisor $destino): int
    {
        if ($duplicado->is($destino) || $duplicado->fusionado_en_id || $destino->fusionado_en_id || ! $destino->activo) {
            throw new ReglaDeNegocio('Solo se fusiona un emisor en otro distinto, activo y no fusionado.');
        }

        return DB::transaction(function () use ($duplicado, $destino) {
            $ids = Expediente::where('emisor_id', $duplicado->id)->lockForUpdate()->pluck('id')->all();
            Expediente::whereKey($ids)->update(['emisor_id' => $destino->id]);
            $duplicado->update(['activo' => false]);
            $duplicado->forceFill(['fusionado_en_id' => $destino->id])->save();

            $this->auditoria->registrar('emisor.fusionado', $duplicado,
                antes: ['activo' => true],
                despues: ['activo' => false, 'fusionado_en_id' => $destino->id, 'expedientes' => $ids],
            );

            return count($ids);
        });
    }
}
