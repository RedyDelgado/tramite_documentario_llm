<?php

namespace App\Rules;

use App\Services\AntivirusService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** Rechaza una subida infectada antes de guardarla (11); con el antivirus caído, pide reintentar en vez de dejarla pasar. */
class SinAmenazas implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        try {
            $amenaza = app(AntivirusService::class)->amenaza($value->getContent());
        } catch (RuntimeException $e) {
            Log::warning('Subida sin analizar: el antivirus no respondió.', ['error' => $e->getMessage()]);
            $fail('No se pudo analizar el archivo con el antivirus; intenta de nuevo en unos minutos.');

            return;
        }

        if ($amenaza !== null) {
            Log::warning('Subida rechazada por el antivirus.', ['amenaza' => $amenaza, 'nombre' => $value->getClientOriginalName(), 'usuario' => auth()->id()]);
            $fail("El archivo contiene «{$amenaza}» y no se guardó.");
        }
    }
}
