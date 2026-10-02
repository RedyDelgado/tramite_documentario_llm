"""OCR local en español (sección 10): nada sale del servidor."""

import subprocess
import tempfile
from pathlib import Path

# Resolución del rasterizado de PDF: 300 ppp es el punto óptimo de Tesseract para texto de oficio.
PPP = 300
TIEMPO_POR_PAGINA = 120

IMAGENES = {"image/png": ".png", "image/jpeg": ".jpg", "image/tiff": ".tif", "image/webp": ".webp"}


class TipoNoSoportado(ValueError):
    pass


def _tesseract(imagen: Path) -> str:
    resultado = subprocess.run(
        ["tesseract", str(imagen), "stdout", "-l", "spa", "--psm", "1"],
        capture_output=True, text=True, timeout=TIEMPO_POR_PAGINA, check=True,
    )
    return resultado.stdout


def reconocer(contenido: bytes, mime: str) -> tuple[str, int]:
    """Devuelve el texto reconocido y el número de páginas."""
    with tempfile.TemporaryDirectory() as tmp:
        dir_ = Path(tmp)
        if mime == "application/pdf":
            pdf = dir_ / "doc.pdf"
            pdf.write_bytes(contenido)
            subprocess.run(
                ["pdftoppm", "-r", str(PPP), "-gray", "-png", str(pdf), str(dir_ / "p")],
                capture_output=True, timeout=TIEMPO_POR_PAGINA * 4, check=True,
            )
            paginas = sorted(dir_.glob("p-*.png"))
        elif mime in IMAGENES:
            imagen = dir_ / f"img{IMAGENES[mime]}"
            imagen.write_bytes(contenido)
            paginas = [imagen]
        else:
            raise TipoNoSoportado(mime)

        textos = [_tesseract(p).strip() for p in paginas]
        # Salto de página como separador: la extracción por reglas lo usa para ubicar el encabezado.
        return "\n\f\n".join(textos), len(paginas)
