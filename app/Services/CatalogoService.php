<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Escritura de los catálogos de configuración; cada cambio queda auditado con su valor anterior (5.1). */
class CatalogoService
{
    private const SIN_AUDITAR = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @return T
     */
    public function crear(Model $modelo, string $accion): Model
    {
        return DB::transaction(function () use ($modelo, $accion) {
            $modelo->save();
            $this->auditoria->registrar($accion, $modelo, despues: Arr::except($modelo->attributesToArray(), self::SIN_AUDITAR));

            return $modelo;
        });
    }

    /**
     * @template T of Model
     *
     * @param  T  $modelo
     * @param  array<string, mixed>  $datos
     * @return T
     */
    public function actualizar(Model $modelo, array $datos, string $accion): Model
    {
        return DB::transaction(function () use ($modelo, $datos, $accion) {
            $modelo->update($datos);
            $this->auditoria->registrarCambios($accion, $modelo);

            return $modelo;
        });
    }

    /** Borrado lógico: nada se borra físicamente (2). */
    public function quitar(Model $modelo, string $accion): void
    {
        DB::transaction(function () use ($modelo, $accion) {
            $modelo->delete();
            $this->auditoria->registrar($accion, $modelo, antes: Arr::except($modelo->attributesToArray(), self::SIN_AUDITAR));
        });
    }
}
