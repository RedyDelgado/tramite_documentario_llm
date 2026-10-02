<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/** Cliente del OCR del servicio de IA (7.3, 10). */
class OcrService
{
    public const MIMES = ['application/pdf', 'image/png', 'image/jpeg', 'image/tiff', 'image/webp'];

    /**
     * @return array{texto: string, paginas: int}
     *
     * @throws ConnectionException si el servicio no responde (el job reintenta)
     * @throws RequestException si el archivo no se puede leer
     */
    public function reconocer(string $rutaAbsoluta, string $mime): array
    {
        return Http::baseUrl(config('tramite.ai.url'))
            ->withHeaders(['X-AI-Token' => (string) config('tramite.ai.token')])
            // Un escaneo largo tarda: ~2 s por página en CPU.
            ->timeout(600)
            ->withBody(file_get_contents($rutaAbsoluta), $mime)
            ->post('/ocr')
            ->throw()
            ->json();
    }
}
