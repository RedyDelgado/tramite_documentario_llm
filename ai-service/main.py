import os
import secrets
import subprocess
from contextlib import asynccontextmanager


from fastapi import Depends, FastAPI, Header, HTTPException, Request
from pydantic import BaseModel

import clasificador
import ocr

@asynccontextmanager
async def ciclo_de_vida(_: FastAPI):
    # Carga el modelo de embeddings al arrancar: la primera clasificación no espera ~25 s.
    if os.environ.get("AI_PRECARGAR", "1") == "1":
        clasificador.embebedor(["precarga"])
    yield


app = FastAPI(title="Servicio de IA - Trámite Documentario", docs_url=None, redoc_url=None, lifespan=ciclo_de_vida)


def verificar_token(x_ai_token: str = Header(default="")) -> None:
    """Exige el token compartido con Laravel; la red interna no basta como control."""
    esperado = os.environ.get("AI_SERVICE_TOKEN", "")
    if not esperado or not secrets.compare_digest(x_ai_token, esperado):
        raise HTTPException(status_code=401, detail="Token inválido")


class Salud(BaseModel):
    estado: str


class InfoModelo(BaseModel):
    version: str | None
    entrenado_en: str | None
    ejemplos: int | None = None


@app.get("/health")
def health() -> Salud:
    """Responde sin token para los healthchecks de Docker."""
    return Salud(estado="ok")


@app.get("/model-info", dependencies=[Depends(verificar_token)])
def model_info() -> InfoModelo:
    """Versión del clasificador entrenado activo; sin versión, se clasifica por similitud."""
    return InfoModelo(**clasificador.info_modelo())


# Un escaneo de 80 folios a 300 ppp en PDF ronda los 20 MB.
MAX_BYTES = 40 * 1024 * 1024


class ResultadoOcr(BaseModel):
    texto: str
    paginas: int


@app.post("/ocr", dependencies=[Depends(verificar_token)])
async def reconocer_texto(request: Request, content_type: str = Header(default="")) -> ResultadoOcr:
    """OCR de un PDF escaneado o una imagen enviada como cuerpo crudo."""
    contenido = await request.body()
    if not contenido:
        raise HTTPException(status_code=422, detail="Cuerpo vacío")
    if len(contenido) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="Archivo demasiado grande")
    try:
        texto, paginas = ocr.reconocer(contenido, content_type.split(";")[0].strip().lower())
    except ocr.TipoNoSoportado:
        raise HTTPException(status_code=415, detail="Tipo no soportado")
    except (subprocess.CalledProcessError, subprocess.TimeoutExpired):
        raise HTTPException(status_code=422, detail="No se pudo leer el archivo")
    return ResultadoOcr(texto=texto, paginas=paginas)


class Categoria(BaseModel):
    id: int
    nombre: str
    descripcion: str | None = None
    palabras_clave: list[str] = []


class PeticionClasificar(BaseModel):
    texto: str
    # Catálogo vigente en cada petición: un área nueva funciona de inmediato y una inactiva nunca se devuelve.
    areas: list[Categoria]
    tipos: list[Categoria] = []


@app.post("/classify", dependencies=[Depends(verificar_token)])
def clasificar(peticion: PeticionClasificar) -> dict:
    if not peticion.texto.strip():
        raise HTTPException(status_code=422, detail="Texto vacío")
    return clasificador.clasificar(
        peticion.texto[:20_000],
        [a.model_dump() for a in peticion.areas],
        [t.model_dump() for t in peticion.tipos],
    )
