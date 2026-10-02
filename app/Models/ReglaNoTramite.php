<?php

namespace App\Models;

use App\Correo\MensajeLeido;
use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Campos: `remitente` (el correo contiene el valor), `dominio` (dominio o subdominio),
 * `asunto` (contiene) y `encabezado` («Nombre» existe, o «Nombre: texto» lo contiene).
 */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Fillable(['nombre', 'campo', 'valor', 'activa'])]
class ReglaNoTramite extends Model
{
    public const CAMPOS = ['remitente' => 'Remitente contiene', 'dominio' => 'Dominio del remitente', 'asunto' => 'Asunto contiene', 'encabezado' => 'Encabezado'];

    protected $table = 'reglas_no_tramite';

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    /** Primera regla activa que clasifica el mensaje como no trámite, si alguna. */
    public static function primeraQueAplica(MensajeLeido $mensaje): ?self
    {
        return self::where('activa', true)->orderBy('id')->get()->first(fn (self $regla) => $regla->aplica($mensaje));
    }

    public function aplica(MensajeLeido $mensaje): bool
    {
        $valor = mb_strtolower(trim($this->valor));

        return match ($this->campo) {
            'remitente' => str_contains($mensaje->deEmail, $valor),
            'dominio' => ($dominio = Str::after($mensaje->deEmail, '@')) === $valor || str_ends_with($dominio, '.'.$valor),
            'asunto' => str_contains(mb_strtolower($mensaje->asunto), $valor),
            'encabezado' => $this->aplicaEncabezado($mensaje, $valor),
            default => false,
        };
    }

    private function aplicaEncabezado(MensajeLeido $mensaje, string $valor): bool
    {
        [$nombre, $contiene] = array_map('trim', explode(':', $valor, 2)) + [1 => ''];
        $encontrado = $mensaje->encabezados[$nombre] ?? null;

        return $encontrado !== null && ($contiene === '' || str_contains(mb_strtolower($encontrado), $contiene));
    }
}
