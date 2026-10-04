<x-mail::message>
# Hola, {{ $destinatario->name }}

Se te derivó un trámite para atender.

<x-mail::table>
| | |
|:--|:--|
| Expediente | {{ $expediente->numero_registro ?? 'Sin número' }} |
| Asunto | {{ str_replace('|', '/', \Illuminate\Support\Str::limit($expediente->asunto, 120)) }} |
| Remitente | {{ str_replace('|', '/', $expediente->remitente_nombre ?? $expediente->remitente_email ?? '—') }} |
| Área | {{ $movimiento->aArea?->nombre ?? '—' }} |
| Fecha límite | {{ $expediente->fecha_limite?->format('d/m/Y') ?? 'Sin plazo' }} |
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

Atiéndelo desde el sistema: ahí están los documentos, el historial y la respuesta.
</x-mail::message>
