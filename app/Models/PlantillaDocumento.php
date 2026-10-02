<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Plantilla de documento saliente, editable desde el panel (7.3.4); las variables {{...}} se llenan con el expediente. */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('plantillas_documento')]
#[Fillable(['nombre', 'tipo_documento_id', 'asunto', 'cuerpo', 'activa'])]
class PlantillaDocumento extends Model
{
    public const VARIABLES = [
        '{{expediente.codigo}}' => 'Código REG-AAAA-NNNNN',
        '{{expediente.numero}}' => 'N° de registro',
        '{{expediente.asunto}}' => 'Asunto del documento recibido',
        '{{expediente.documento}}' => 'N° del documento recibido',
        '{{expediente.fecha}}' => 'Fecha de ingreso',
        '{{remitente}}' => 'Emisor o remitente del documento recibido',
        '{{fecha}}' => 'Fecha de hoy',
        '{{area}}' => 'Área que emite',
    ];

    protected $attributes = ['activa' => true];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
