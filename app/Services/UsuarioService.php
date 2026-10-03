<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Única vía de escritura de usuarios y su rol; cada cambio queda auditado (9). */
class UsuarioService
{
    private const CONFIGURACION = 'configuracion.gestionar';

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** @param array{name: string, email: string, rol: string, activo: bool, administra_configuracion?: bool} $datos */
    public function crear(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $usuario = User::create(collect($datos)->only(['name', 'email', 'activo'])->all());
            $usuario->assignRole($datos['rol']);
            $this->auditoria->registrar('usuario.creado', $usuario, despues: $datos);
            $this->delegarConfiguracion($usuario, (bool) ($datos['administra_configuracion'] ?? false));

            return $usuario;
        });
    }

    /**
     * @param  array{name: string, email: string, rol: string, activo: bool, administra_configuracion?: bool}  $datos
     *
     * @throws ReglaDeNegocio
     */
    public function actualizar(User $usuario, array $datos): User
    {
        $rolAnterior = $usuario->getRoleNames()->first();

        if ($usuario->is(Auth::user()) && ($datos['rol'] !== $rolAnterior || ! $datos['activo'])) {
            throw new ReglaDeNegocio('No puedes cambiar tu propio rol ni desactivarte; pídeselo a otro administrador.');
        }

        return DB::transaction(function () use ($usuario, $datos, $rolAnterior) {
            $usuario->update(collect($datos)->only(['name', 'email', 'activo'])->all());
            $this->auditoria->registrarCambios('usuario.actualizado', $usuario);

            if ($datos['rol'] !== $rolAnterior) {
                $usuario->syncRoles([$datos['rol']]);
                $this->auditoria->registrar('usuario.rol_cambiado', $usuario, antes: ['rol' => $rolAnterior], despues: ['rol' => $datos['rol']]);
            }
            if (array_key_exists('administra_configuracion', $datos)) {
                $this->delegarConfiguracion($usuario, (bool) $datos['administra_configuracion']);
            }

            return $usuario;
        });
    }

    /** Permiso directo, aparte del rol: el rol sigue diciendo qué trámites ve; esto, si administra el catálogo. */
    private function delegarConfiguracion(User $usuario, bool $administra): void
    {
        if ($usuario->hasDirectPermission(self::CONFIGURACION) === $administra) {
            return;
        }

        $administra ? $usuario->givePermissionTo(self::CONFIGURACION) : $usuario->revokePermissionTo(self::CONFIGURACION);
        $this->auditoria->registrar('usuario.permiso_cambiado', $usuario,
            antes: [self::CONFIGURACION => ! $administra], despues: [self::CONFIGURACION => $administra]);
    }

    /**
     * Desactivar en lugar de borrar: conserva su historial y corta su sesión abierta.
     *
     * @throws ReglaDeNegocio
     */
    public function cambiarEstado(User $usuario, bool $activo): User
    {
        if (! $activo && $usuario->is(Auth::user())) {
            throw new ReglaDeNegocio('No puedes desactivarte; pídeselo a otro administrador.');
        }

        return DB::transaction(function () use ($usuario, $activo) {
            $usuario->update(['activo' => $activo]);
            $this->auditoria->registrarCambios($activo ? 'usuario.activado' : 'usuario.desactivado', $usuario);

            return $usuario;
        });
    }
}
