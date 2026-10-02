<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('titulo') · {{ $e->numero_registro }}</title>
        @vite('resources/css/app.css')
    </head>
    {{-- Documento para papel: sin el AppShell, una columna y en tinta. --}}
    <body class="bg-surface text-fg">
        <main class="mx-auto max-w-2xl p-8">
            <div class="mb-6 flex justify-end gap-2 print:hidden">
                <button type="button" onclick="window.print()" class="h-8 cursor-pointer rounded-control bg-primary-600 px-3 text-base font-semibold text-on-primary">Imprimir</button>
                <button type="button" onclick="window.close()" class="h-8 cursor-pointer rounded-control border border-border-strong px-3 text-base">Cerrar</button>
            </div>
            @yield('contenido')
        </main>
    </body>
</html>
