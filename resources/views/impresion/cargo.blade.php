@extends('impresion.layout')

@section('titulo', 'Cargo de entrega')

@section('contenido')
    @php($lima = config('app.timezone'))
    <header class="flex items-start justify-between gap-6 border-b border-border-strong pb-4">
        <div>
            <p class="text-sm text-fg-muted">{{ config('app.name') }}</p>
            <h1 class="text-xl font-semibold">Cargo de entrega</h1>
            <p class="mt-1 text-lg font-semibold">{{ $e->numero_registro }}</p>
            <p class="text-base text-fg-muted">{{ $e->codigo }}</p>
        </div>
        <div class="size-32 shrink-0">{!! $qr !!}</div>
    </header>

    <dl class="mt-6 grid grid-cols-[11rem_1fr] gap-x-4 gap-y-2 text-base">
        <dt class="text-fg-muted">Asunto</dt>
        <dd>{{ $e->asunto }}</dd>
        <dt class="text-fg-muted">Documento</dt>
        <dd>{{ $e->numero_documento_original ?? $e->numero_documento ?? '—' }}{{ $e->folios ? " · {$e->folios} folios" : '' }}</dd>
        <dt class="text-fg-muted">Derivado a</dt>
        <dd>{{ $movimiento->aArea?->nombre }}{{ $movimiento->aUser ? " ({$movimiento->aUser->name})" : '' }}</dd>
        <dt class="text-fg-muted">Instrucción</dt>
        <dd>{{ $movimiento->instruccion ?? '—' }}</dd>
        <dt class="text-fg-muted">Fecha límite</dt>
        <dd>{{ $movimiento->fecha_limite?->format('d/m/Y') ?? 'Sin plazo' }}</dd>
        @if ($movimiento->nota)
            <dt class="text-fg-muted">Nota</dt>
            <dd>{{ $movimiento->nota }}</dd>
        @endif
        <dt class="text-fg-muted">Derivado el</dt>
        <dd>{{ $movimiento->created_at->setTimezone($lima)->format('d/m/Y H:i') }} por {{ $movimiento->user?->name }}</dd>
    </dl>

    <div class="mt-16 grid grid-cols-2 gap-12 text-center text-sm">
        <p class="border-t border-fg pt-2">Entregado por</p>
        <p class="border-t border-fg pt-2">Recibido por (nombre, firma, fecha y hora)</p>
    </div>
@endsection
