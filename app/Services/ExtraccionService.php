<?php

namespace App\Services;

use App\Models\Emisor;
use App\Models\TipoDocumento;
use Illuminate\Support\Str;

/** Extracción por reglas de los campos de un documento (7.3.5): propone, la persona confirma. La IA local completa lo que falte (RegistroFisicoService). */
class ExtraccionService
{
    private const MESES = [
        'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
        'agosto' => 8, 'setiembre' => 9, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12,
    ];

    // Encabezado típico: «OFICIO MÚLTIPLE N° 045-2026-UNIQ/DGA». El tipo es lo anterior a N°/No/Nro.
    private const ENCABEZADO = '/^\s*([A-ZÁÉÍÓÚÑa-záéíóúñ ]{4,40}?)\s*(?:N\s*[°º.o]+|NRO\.?|No\.?|Nº)\s*[:.]?\s*([0-9][0-9A-Za-zÁÉÍÓÚÑ\-–—\/. ]{1,80})$/mu';

    // Quién lo envía: «DE: Mgtr. …», «REMITE: …». Lo que sigue a una coma suele ser el cargo.
    private const REMITENTE = '/^\s*(?:DE|DEL|REMITE|REMITENTE)\s*:\s*(.+)$/mui';

    /**
     * @return array{tipo_documento_id: ?int, numero_documento_original: ?string, fecha_documento: ?string, asunto: ?string, emisor_id: ?int, emisor_sugerido: ?string, institucion_id: ?int}
     */
    public function extraer(string $texto): array
    {
        // El encabezado está en la primera página; el resto del documento confunde las reglas.
        $inicio = Str::before($texto, "\f");

        // El encabezado es la primera línea «TIPO N° …» cuyo tipo está en el catálogo: «Adjunto voucher de pago N° 0045871» no lo es.
        $tipo = $numero = null;
        preg_match_all(self::ENCABEZADO, $inicio, $encabezados, PREG_SET_ORDER);
        foreach ($encabezados as $m) {
            if ($tipo = $this->tipoDocumento($m[1])) {
                $numero = trim(preg_replace('/\s+/', ' ', $m[0]));
                break;
            }
        }
        // Sin número, el documento puede traer la sigla de su tipo: el FUT dice «FORMULARIO ÚNICO DE TRÁMITE (FUT)».
        $tipo ??= $this->tipoPorSigla($inicio);

        // «ASUNTO:» en oficios y cartas; «SOLICITO:» en las solicitudes y el FUT.
        $asunto = preg_match('/^\s*ASUNTO\s*:?\s*(.+)$/mui', $inicio, $a) || preg_match('/^\s*SOLICIT[OA]\s*:\s*(.+)$/mui', $inicio, $a)
            ? Str::limit(trim($a[1]), 500, '') : null;

        // Con línea «DE:», quien firma es esa persona (del catálogo o para crearla con un clic) y su institución la que
        // nombre esa línea o el encabezado. Sin ella, emite lo que el encabezado nombre (p. ej. la municipalidad).
        $linea = preg_match(self::REMITENTE, $inicio, $r) ? trim($r[1]) : null;
        $remitente = $linea ? Str::limit(trim(Str::before($linea, ',')), 150, '') : null;
        if ($remitente) {
            $emisor = $this->emisor($remitente, 'persona');
            $institucion = $this->emisor($linea, 'institucion') ?? $this->emisor($inicio, 'institucion') ?? ($emisor ? Emisor::find($emisor)?->institucion_id : null);
        } else {
            $emisor = $this->emisor($inicio);
            $institucion = $emisor && Emisor::find($emisor)?->clase === 'persona' ? Emisor::find($emisor)->institucion_id : null;
        }

        return [
            'tipo_documento_id' => $tipo,
            'numero_documento_original' => $numero,
            'fecha_documento' => $this->fecha($inicio),
            'asunto' => $asunto,
            'emisor_id' => $emisor,
            'emisor_sugerido' => $emisor ? null : $remitente,
            'institucion_id' => $institucion,
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

    /** Tipo del catálogo al principio o al final del encabezado: «Informe académico» es un Informe, «Oficio múltiple» un Oficio múltiple. */
    public function tipoDocumento(string $texto): ?int
    {
        $buscado = Emisor::normalizar($texto);
        $palabra = fn (string $tipo) => preg_match('/(^|\s)'.preg_quote($tipo, '/').'(\s|$)/u', $buscado) === 1;

        // El nombre más largo que coincide gana: «oficio multiple» antes que «oficio».
        return TipoDocumento::where('activo', true)->get(['id', 'nombre'])
            ->filter(fn (TipoDocumento $t) => ($n = Emisor::normalizar($t->nombre)) !== '' && (str_starts_with($buscado, $n) || str_ends_with($buscado, $n)) && $palabra($n))
            ->sortByDesc(fn (TipoDocumento $t) => mb_strlen($t->nombre))
            ->first()?->id;
    }

    /** Tipo del catálogo cuya sigla entre paréntesis («Solicitud (FUT)») aparece como palabra en el texto. */
    private function tipoPorSigla(string $texto): ?int
    {
        return TipoDocumento::where('activo', true)->get(['id', 'nombre'])
            ->first(fn (TipoDocumento $t) => preg_match('/\(([A-ZÁÉÍÓÚÑ]{2,10})\)/u', $t->nombre, $s) && preg_match('/\b'.$s[1].'\b/u', $texto))?->id;
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
    public function emisor(string $texto, ?string $clase = null): ?int
    {
        $normalizado = ' '.Emisor::normalizar($texto).' ';

        return Emisor::vigentes()->when($clase, fn ($q) => $q->where('clase', $clase))->get(['id', 'nombre_normalizado'])
            ->filter(fn (Emisor $e) => str_contains($normalizado, " {$e->nombre_normalizado} "))
            ->sortByDesc(fn (Emisor $e) => mb_strlen($e->nombre_normalizado))
            ->first()?->id;
    }
}
