<x-mail::message>
# Hola, {{ $destinatario->name }}

@if ($atencion)
Se derivó un trámite a **{{ $atencion->area->nombre }}** para atender.
@else
Te llega en copia, **para conocimiento**: no tienes que atenderlo.
@endif

<x-mail::table>
| | |
|:--|:--|
| Expediente | {{ $expediente->numero_registro ?? 'Sin número' }} |
| Asunto | {{ str_replace('|', '/', \Illuminate\Support\Str::limit($expediente->asunto, 120)) }} |
| Remitente | {{ str_replace('|', '/', $expediente->remitente_nombre ?? $expediente->remitente_email ?? '—') }} |
| Áreas que lo atienden | {{ str_replace('|', '/', implode(', ', $movimiento->areas ?? [$movimiento->aArea?->nombre ?? '—'])) }} |
@if ($atencion)
| Fecha límite | {{ $atencion->fecha_limite?->format('d/m/Y') ?? 'Sin plazo' }} |
@endif
</x-mail::table>

@if ($movimiento->instruccion)
**Instrucción:** {{ $movimiento->instruccion }}
@endif

@if ($movimiento->nota)
**Indicaciones:** {{ $movimiento->nota }}
@endif

@if ($atencion)
**Para responder, contesta este mismo correo** con lo que hizo tu área y adjunta lo que corresponda: la respuesta se anexa sola al trámite. Los documentos del trámite van adjuntos.
@endif

Código del trámite: **{{ $expediente->codigo ?? 'sin código' }}** (no lo borres al responder; con él la respuesta llega a su trámite).

Si tienes acceso al sistema, también puedes [abrir el expediente]({{ route('expedientes.show', $expediente) }}).
</x-mail::message>
