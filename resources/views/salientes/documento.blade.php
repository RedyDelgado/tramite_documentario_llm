<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        {{-- dompdf no usa Tailwind: estilos mínimos en línea, en tinta negra. --}}
        <style>
            @page { margin: 2.5cm 2.5cm 2.5cm 3cm; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; line-height: 1.5; color: black; }
            p { margin: 0 0 10pt; white-space: pre-line; }
            .titulo { text-align: center; font-weight: bold; font-size: 13pt; margin: 18pt 0; }
            .derecha { text-align: right; }
            .negrita { font-weight: bold; }
        </style>
    </head>
    <body>
        @foreach ($parrafos as [$texto, $estilo])
            <p class="{{ $estilo }}">{{ $texto }}</p>
        @endforeach
    </body>
</html>
