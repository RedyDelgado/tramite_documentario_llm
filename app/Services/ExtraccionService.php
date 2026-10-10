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
    // El número son tramos unidos por guiones o barras: lo que sigue tras un espacio suele ser ruido del sello en el OCR.
    private const ENCABEZADO = '/^\s*([A-ZÁÉÍÓÚÑa-záéíóúñ ]{4,40}?)\s*(?:N\s*[°º.o?]+|NRO\.?|No\.?|Nº)\s*[:.]?\s*([0-9][0-9A-Za-zÁÉÍÓÚÑ.]*(?:\s*[-–—\/]\s*[0-9A-Za-zÁÉÍÓÚÑ.]+)*)/mu';

    // A quién va dirigido, que no es quien lo emite; sigue abajo (el cargo) hasta otra etiqueta o una línea en blanco.
    private const DESTINATARIO = '/^\s*(?:A|PARA|SEÑOR(?:A|ES)?)\s*:.*(?:\R(?!\s*(?:[\p{Lu} ]{2,25}:|$)).*)*/mu';

    // Nombres que el documento escribe distinto que el catálogo.
    private const SINONIMOS = ['memorandum' => 'memorando'];

    // Quién lo envía: «DE: Mgtr. …», «REMITE: …». Lo que sigue a una coma suele ser el cargo.
    private const REMITENTE = '/^\s*(?:DE|DEL|REMITE|REMITENTE)\s*:\s*(.+)$/mui';

    /**
     * @return array{tipo_documento_id: ?int, numero_documento_original: ?string, fecha_documento: ?string, asunto: ?string, emisor_id: ?int, emisor_sugerido: ?string, institucion_id: ?int, institucion_sugerida: ?string}
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
                // El OCR suele leer «N°» como «N?» y arrastra el punto final de la línea.
                $numero = rtrim(trim(preg_replace(['/\s+/', '/\bN\s*\?\s*/u'], [' ', 'N° '], $m[0])), ' .');
                break;
            }
        }
        // Sin número, el documento puede traer la sigla de su tipo: el FUT dice «FORMULARIO ÚNICO DE TRÁMITE (FUT)».
        $tipo ??= $this->tipoPorSigla($inicio);

        $asunto = $this->asunto($inicio);

        // Con línea «DE:», quien firma es esa persona (del catálogo o para crearla con un clic) y su institución la que
        // nombre esa línea o el encabezado. Sin ella, emite lo que el encabezado nombre (p. ej. la municipalidad).
        $linea = preg_match(self::REMITENTE, $inicio, $r) ? trim($r[1]) : null;
        $remitente = $linea ? Str::limit(trim(Str::before($linea, ',')), 150, '') : null;
        $sinDestinatario = preg_replace(self::DESTINATARIO, '', $inicio);
        if ($remitente) {
            $emisor = $this->emisor($remitente, 'persona');
            $institucion = $this->emisor($linea, 'institucion') ?? $this->emisor($sinDestinatario, 'institucion') ?? ($emisor ? Emisor::find($emisor)?->institucion_id : null);
        } else {
            $emisor = $this->emisor($sinDestinatario);
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
            // Sin institución del catálogo, la del membrete («UNIVERSIDAD NACIONAL INTERCULTURAL DE QUILLABAMBA») para crearla.
            'institucion_sugerida' => $institucion || ($emisor && Emisor::find($emisor)?->clase === 'institucion') ? null : $this->membrete($inicio),
        ];
    }

    /** Mayúsculas, sin tildes, «No / N.º / Nº / Nro.» → «N°», guiones y espacios uniformes (7.3.5, punto 8). */
    public static function normalizarNumero(string $numero): string
    {
        $n = Str::of($numero)->replace(['–', '—'], '-')->upper();
        $n = $n->replaceMatches('/\b(?:N\s*[°º.O?]+|NRO\.?|NO\.?)\s*(?=\d)/u', 'N° ');
        $n = Str::of(Str::ascii(str_replace('N°', 'N#', $n->toString())))->replace('N#', 'N°');

        return $n->replaceMatches('/\s*([-\/])\s*/', '$1')->squish()->toString();
    }

    /** «ASUNTO:» en oficios y cartas; «SOLICITO:» en las solicitudes y el FUT. */
    private function asunto(string $texto): ?string
    {
        if (! preg_match('/^\s*ASUNTO\s*:?\s*(.+)((?:\R.*){0,6})/mui', $texto, $a) && ! preg_match('/^\s*SOLICIT[OA]\s*:\s*(.+)((?:\R.*){0,6})/mui', $texto, $a)) {
            return null;
        }
        $asunto = trim($a[1]);
        // Un asunto largo sigue en las líneas de abajo: en mayúsculas mientras sigan en mayúsculas (el OCR deja líneas en
        // blanco en medio) y no empiece otra etiqueta («REFERENCIA:»); en minúsculas, solo si la frase continúa sin corte.
        $mayusculas = mb_strtoupper($asunto) === $asunto;
        $corte = false;
        foreach (array_slice(preg_split('/\R/u', $a[2]), 1) as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                $corte = true;

                continue;
            }
            $sigue = $mayusculas
                ? mb_strtoupper($linea) === $linea && ! preg_match('/^[\p{Lu} .]{2,25}:/u', $linea)
                : ! $corte && preg_match('/^\p{Ll}/u', $linea);
            if (str_ends_with($asunto, '.') || ! $sigue) {
                break;
            }
            $asunto .= ' '.$linea;
        }

        return Str::limit(rtrim($asunto, ' .'), 500, '');
    }

    /** Primera línea del encabezado que nombra una institución (universidad, municipalidad, hospital…). */
    private function membrete(string $texto): ?string
    {
        $lineas = array_slice(preg_split('/\R/u', $texto), 0, 8);
        foreach ($lineas as $i => $linea) {
            $linea = trim(preg_replace('/\s+/', ' ', $linea), " \t.,;:“”\"'");
            if (mb_strlen($linea) >= 8 && mb_strlen($linea) <= 120 && Emisor::adivinarClase($linea) === 'institucion') {
                // ponytail: «UNIVERSIDAD NACIONAL», «GOBIERNO REGIONAL» no cierran el nombre: sigue abajo, tras el ruido del logo. Otros cortes, a mano.
                if (preg_match('/\b(NACIONAL|REGIONAL|PROVINCIAL|DISTRITAL)$/u', $linea) && preg_match('/((?:\p{Lu}+ +)*\p{Lu}{2,})\s*$/u', $lineas[$i + 1] ?? '', $resto)) {
                    $linea .= ' '.$resto[1];
                }

                return Str::title(mb_strtolower($linea));
            }
        }

        return null;
    }

    /** Tipo del catálogo al principio o al final del encabezado: «Informe académico» es un Informe, «Oficio múltiple» un Oficio múltiple. */
    public function tipoDocumento(string $texto): ?int
    {
        $buscado = strtr(Emisor::normalizar($texto), self::SINONIMOS);
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
        // La del documento va tras el lugar o la etiqueta («Quillabamba, 22 de…», «FECHA: 22 de…»); el asunto puede citar otras.
        if (preg_match("/[,:]\s*(\d{1,2})\s+de\s+({$meses})\s+(?:de|del)\s+(\d{4})/iu", $texto, $m)
            || preg_match("/(\d{1,2})\s+de\s+({$meses})\s+(?:de|del)\s+(\d{4})/iu", $texto, $m)) {
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
