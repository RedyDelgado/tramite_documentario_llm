<?php

namespace App\Correo;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;

/**
 * Buzón de desarrollo y pruebas: lee los .eml de una carpeta. Lo procesado se marca con un
 * archivo `.procesado` al lado; el .eml no se mueve ni se borra, igual que en Gmail.
 * No filtra por fecha: en una carpeta de prueba se procesa todo.
 */
class DirectorioMailboxDriver implements MailboxDriver
{
    public function __construct(private readonly string $directorio) {}

    public function pendientes(CarbonInterface $desde, int $limite): iterable
    {
        $enviados = 0;
        foreach ($this->archivos() as $archivo) {
            if ($enviados >= $limite) {
                return;
            }
            if (! File::exists($archivo.'.procesado')) {
                $enviados++;
                yield new MensajeCrudo(basename($archivo), File::get($archivo));
            }
        }
    }

    public function marcarProcesado(MensajeCrudo $mensaje): void
    {
        File::put($this->directorio.DIRECTORY_SEPARATOR.$mensaje->uid.'.procesado', now()->toIso8601String());
    }

    public function resumen(CarbonInterface $desde): iterable
    {
        $lector = new LectorEml;
        foreach ($this->archivos() as $archivo) {
            $mensaje = $lector->leer(File::get($archivo));
            if ($mensaje->fecha && $mensaje->fecha->greaterThanOrEqualTo($desde)) {
                yield ['fecha' => $mensaje->fecha, 'remitente' => $mensaje->deEmail];
            }
        }
    }

    /** @return list<string> */
    private function archivos(): array
    {
        if (! File::isDirectory($this->directorio)) {
            return [];
        }
        $archivos = File::glob($this->directorio.DIRECTORY_SEPARATOR.'*.eml');
        sort($archivos);

        return $archivos;
    }
}
