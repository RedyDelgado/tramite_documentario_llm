@extends('impresion.layout')

@section('titulo', 'Etiqueta QR')

@section('contenido')
    {{-- Etiqueta para pegar en el original: el QR abre el expediente (7.3). --}}
    <div class="inline-flex items-center gap-3 rounded-control border border-fg p-3">
        <div class="size-28 shrink-0">{!! $qr !!}</div>
        <div class="text-sm">
            <p class="text-lg font-semibold">{{ $e->numero_registro }}</p>
            <p>{{ $e->codigo }}</p>
            <p>{{ $e->fecha_ingreso->setTimezone(config('app.timezone'))->format('d/m/Y H:i') }}</p>
            <p>{{ $e->folios ? "{$e->folios} folios" : '' }}</p>
        </div>
    </div>
@endsection
