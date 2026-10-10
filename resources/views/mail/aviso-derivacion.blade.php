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
**Nota:** {{ $movimiento->nota }}
@endif

<x-mail::button :url="route('expedientes.show', $expediente)">
Abrir el expediente
</x-mail::button>

@if ($atencion)
Atiéndelo desde el sistema: ahí están los documentos y el historial; al terminar, márcalo como atendido.
@endif
</x-mail::message>
