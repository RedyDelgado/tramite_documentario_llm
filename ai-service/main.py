import os
import secrets

from fastapi import Depends, FastAPI, Header, HTTPException
from pydantic import BaseModel

app = FastAPI(title="Servicio de IA - Trámite Documentario", docs_url=None, redoc_url=None)


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


@app.get("/health")
def health() -> Salud:
    """Responde sin token para los healthchecks de Docker."""
    return Salud(estado="ok")


@app.get("/model-info", dependencies=[Depends(verificar_token)])
def model_info() -> InfoModelo:
    """Versión del modelo activo; vacío hasta la fase 4."""
    return InfoModelo(version=None, entrenado_en=None)
