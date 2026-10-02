<x-mail::message>
# Hola, {{ $coordinador->name }}

Tienes {{ $expedientes->count() }} {{ $expedientes->count() === 1 ? 'trámite pendiente' : 'trámites pendientes' }} en tus áreas.

<x-mail::table>
| Semáforo | Expediente | Asunto | Fecha límite |
|:--|:--|:--|:--|
@foreach ($expedientes as $e)
| {{ $e->semaforo?->etiqueta() ?? '—' }} | [{{ $e->numero_registro ?? 'Sin número' }}]({{ route('expedientes.show', $e) }}) | {{ str_replace('|', '/', \Illuminate\Support\Str::limit($e->asunto, 80)) }} | {{ $e->fecha_limite?->format('d/m/Y') ?? 'Sin plazo' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="route('expedientes.index')">
Ver todos mis expedientes
</x-mail::button>

Este resumen se envía cada día hábil por la mañana.
</x-mail::message>
