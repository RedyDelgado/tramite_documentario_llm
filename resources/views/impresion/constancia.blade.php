@extends('impresion.layout')

@section('titulo', 'Constancia de recepción')

@section('contenido')
    @php($lima = config('app.timezone'))
    <header class="flex items-start justify-between gap-6 border-b border-border-strong pb-4">
        <div>
            <p class="text-sm text-fg-muted">{{ config('app.name') }}</p>
            <h1 class="text-xl font-semibold">Constancia de recepción</h1>
            <p class="mt-1 text-lg font-semibold">{{ $e->numero_registro }}</p>
            <p class="text-base text-fg-muted">{{ $e->codigo }}</p>
        </div>
        <div class="size-40 shrink-0">{!! $qr !!}</div>
    </header>

    <dl class="mt-6 grid grid-cols-[11rem_1fr] gap-x-4 gap-y-2 text-base">
        <dt class="text-fg-muted">Fecha y hora</dt>
        <dd>{{ $e->fecha_ingreso->setTimezone($lima)->format('d/m/Y H:i') }}</dd>
        <dt class="text-fg-muted">Emisor</dt>
        <dd>{{ $e->emisor?->nombre ?? '—' }}</dd>
        <dt class="text-fg-muted">Documento</dt>
        <dd>{{ $e->numero_documento_original ?? $e->numero_documento ?? '—' }}</dd>
        @if ($e->fecha_documento)
            <dt class="text-fg-muted">Fecha del documento</dt>
            <dd>{{ $e->fecha_documento->format('d/m/Y') }}</dd>
        @endif
        <dt class="text-fg-muted">Asunto</dt>
        <dd>{{ $e->asunto }}</dd>
        <dt class="text-fg-muted">Folios</dt>
        <dd>{{ $e->folios ?? '—' }}</dd>
        <dt class="text-fg-muted">Recibido por</dt>
        <dd>{{ $e->registrador?->name ?? '—' }}</dd>
    </dl>

    <p class="mt-8 text-sm text-fg-muted">
        Con el código {{ $e->codigo }} puede consultar el estado de su trámite. El documento original queda en custodia de la
        institución; su versión escaneada es una copia de consulta.
    </p>

    <div class="mt-16 grid grid-cols-2 gap-12 text-center text-sm">
        <p class="border-t border-fg pt-2">Firma y sello de recepción</p>
        <p class="border-t border-fg pt-2">Firma de quien presenta</p>
    </div>
@endsection
