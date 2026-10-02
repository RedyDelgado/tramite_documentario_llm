<?php

namespace App\Services;

use App\Models\Emisor;
use App\Models\TipoDocumento;
use Illuminate\Support\Str;

/** Extracción por reglas de los campos de un documento (7.3.5): propone, la persona confirma. Sin IA. */
class ExtraccionService
{
    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'setiembre' => 9, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    // Encabezado típico: «OFICIO MÚLTIPLE N° 045-2026-UNIQ/DGA». El tipo es lo anterior a N°/No/Nro.
    private const ENCABEZADO = '/^\s*([A-ZÁÉÍÓÚÑa-záéíóúñ ]{4,40}?)\s*(?:N\s*[°º.o]+|NRO\.?|No\.?|Nº)\s*[:.]?\s*([0-9][0-9A-Za-zÁÉÍÓÚÑ\-–—\/. ]{1,80})$/mu';

    /**
     * @return array{tipo_documento_id: ?int, numero_documento_original: ?string, fecha_documento: ?string, asunto: ?string, emisor_id: ?int}
     */
    public function extraer(string $texto): array
    {
        // El encabezado está en la primera página; el resto del documento confunde las reglas.
        $inicio = Str::before($texto, "\f");

        $tipo = $numero = null;
        if (preg_match(self::ENCABEZADO, $inicio, $m)) {
            $tipo = $this->tipoDocumento($m[1]);
            $numero = trim(preg_replace('/\s+/', ' ', $m[0]));
        }

        return [
            'tipo_documento_id' => $tipo,
            'numero_documento_original' => $numero,
            'fecha_documento' => $this->fecha($inicio),
            'asunto' => preg_match('/^\s*ASUNTO\s*:?\s*(.+)$/mui', $inicio, $a) ? Str::limit(trim($a[1]), 500, '') : null,
            'emisor_id' => $this->emisor($inicio),
        ];
    }

    /** Mayúsculas, sin tildes, «No / N.º / Nº / Nro.» → «N°», guiones y espacios uniformes (7.3.5, punto 8). */
    public static function normalizarNumero(string $numero): string
    {
        $n = Str::of($numero)->replace(['–', '—'], '-')->upper();
        $n = $n->replaceMatches('/\b(?:N\s*[°º.O]+|NRO\.?|NO\.?)\s*(?=\d)/u', 'N° ');
        $n = Str::of(Str::ascii(str_replace('N°', 'N#', $n->toString())))->replace('N#', 'N°');

        return $n->replaceMatches('/\s*([-\/])\s*/', '$1')->squish()->toString();
    }

    private function tipoDocumento(string $texto): ?int
    {
        $buscado = Emisor::normalizar($texto);

        // El nombre más largo que coincide gana: «oficio multiple» antes que «oficio».
        return TipoDocumento::where('activo', true)->get(['id', 'nombre'])
            ->filter(fn (TipoDocumento $t) => str_ends_with($buscado, Emisor::normalizar($t->nombre)) || $buscado === Emisor::normalizar($t->nombre))
            ->sortByDesc(fn (TipoDocumento $t) => mb_strlen($t->nombre))
            ->first()?->id;
    }

    private function fecha(string $texto): ?string
    {
        $meses = implode('|', array_keys(self::MESES));
        if (preg_match("/(\d{1,2})\s+de\s+({$meses})\s+(?:de|del)\s+(\d{4})/iu", $texto, $m)) {
            [$dia, $mes, $anio] = [(int) $m[1], self::MESES[mb_strtolower($m[2])], (int) $m[3]];
        } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', $texto, $m)) {
            [$dia, $mes, $anio] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    /** Emisor vigente cuyo nombre aparece en el texto; el más largo, para no confundir «Dirección» con «Dirección General». */
    private function emisor(string $texto): ?int
    {
        $normalizado = ' '.Emisor::normalizar($texto).' ';

        return Emisor::vigentes()->get(['id', 'nombre_normalizado'])
            ->filter(fn (Emisor $e) => str_contains($normalizado, " {$e->nombre_normalizado} "))
            ->sortByDesc(fn (Emisor $e) => mb_strlen($e->nombre_normalizado))
            ->first()?->id;
    }
}
