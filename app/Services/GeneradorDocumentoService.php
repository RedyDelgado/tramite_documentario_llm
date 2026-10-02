<?php

namespace App\Services;

use App\Models\DocumentoSaliente;
use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

/** PDF y Word de un documento saliente (7.3.4) a partir de los mismos párrafos: ambos formatos dicen lo mismo. */
class GeneradorDocumentoService
{
    /**
     * Párrafos del documento: [texto, estilo] con estilo `titulo`, `negrita`, `normal` o `derecha`.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function parrafos(DocumentoSaliente $s): array
    {
        $s->loadMissing(['area', 'expediente']);
        $fecha = ($s->aprobado_at ?? now())->setTimezone(config('app.timezone'))->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
        $destinatarios = collect($s->destinatarios)->map(fn ($d) => $d['nombre'] ?: $d['email'])->implode('; ');

        return array_values(array_filter([
            [$s->area->nombre, 'derecha'],
            [$s->numero ?? 'BORRADOR (sin número hasta su aprobación)', 'titulo'],
            [$fecha, 'derecha'],
            ["Para: {$destinatarios}", 'normal'],
            ["Asunto: {$s->asunto}", 'negrita'],
            $s->expediente?->codigo ? ['Referencia: '.trim(($s->expediente->numero_documento_original ?? '').' ('.$s->expediente->codigo.')'), 'normal'] : null,
            ...array_map(fn (string $p) => [$p, 'normal'], preg_split('/\R{2,}/', trim($s->cuerpo)) ?: []),
            ['Atentamente,', 'normal'],
            [$s->area->nombre, 'negrita'],
        ]));
    }

    public function pdf(DocumentoSaliente $s): string
    {
        $opciones = new Options;
        // Sin recursos remotos: el PDF se arma solo con lo que hay en el servidor.
        $opciones->setIsRemoteEnabled(false);
        $opciones->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('salientes.documento', ['parrafos' => $this->parrafos($s)])->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    /** DOCX mínimo (Office Open XML): un ZIP con el documento, sus relaciones y los tipos de contenido. */
    public function docx(DocumentoSaliente $s): string
    {
        $cuerpo = implode('', array_map(function (array $p) {
            [$texto, $estilo] = $p;
            $alineacion = match ($estilo) {
                'titulo' => '<w:jc w:val="center"/>',
                'derecha' => '<w:jc w:val="right"/>',
                default => '',
            };
            $formato = match ($estilo) {
                'titulo' => '<w:b/><w:sz w:val="28"/>',
                'negrita' => '<w:b/>',
                default => '',
            };
            $runs = implode('<w:br/>', array_map(fn ($linea) => '<w:t xml:space="preserve">'.htmlspecialchars($linea, ENT_XML1).'</w:t>', explode("\n", $texto)));

            return "<w:p><w:pPr>{$alineacion}<w:spacing w:after=\"200\"/></w:pPr><w:r><w:rPr>{$formato}</w:rPr>{$runs}</w:r></w:p>";
        }, $this->parrafos($s)));

        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($archivo, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$cuerpo
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1418" w:right="1418" w:bottom="1418" w:left="1701"/></w:sectPr>'
            .'</w:body></w:document>');
        $zip->close();
        $contenido = file_get_contents($archivo);
        unlink($archivo);

        return $contenido;
    }
}
