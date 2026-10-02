"""Clasificación por similitud de embeddings y, si existe, por el modelo entrenado (sección 10)."""

import hashlib
import json
import os
from functools import lru_cache
from pathlib import Path

import numpy as np

MODELO_EMBEDDINGS = "intfloat/multilingual-e5-small"
DIR_MODELOS = Path(os.environ.get("AI_DIR_MODELOS", "/srv/models"))
# Temperatura del softmax sobre similitudes: e5 comprime los cosenos en ~0,7-0,9; sin escalar, todo parece empate.
TEMPERATURA = 0.02
TOP_K = 3


class Embebedor:
    """Envoltorio del modelo de embeddings; se carga una vez y solo desde el disco local."""

    def __init__(self) -> None:
        self._modelo = None

    def __call__(self, textos: list[str]) -> np.ndarray:
        if self._modelo is None:
            from sentence_transformers import SentenceTransformer

            self._modelo = SentenceTransformer(MODELO_EMBEDDINGS, device="cpu")
        # e5 espera el prefijo «query: » en ambos lados para comparar textos entre sí.
        return self._modelo.encode([f"query: {t}" for t in textos], normalize_embeddings=True)


embebedor = Embebedor()


def _texto_categoria(c: dict) -> str:
    claves = ", ".join(c.get("palabras_clave") or [])
    return ". ".join(p for p in [c["nombre"], c.get("descripcion") or "", f"Palabras clave: {claves}" if claves else ""] if p)


@lru_cache(maxsize=2048)
def _vector_categoria(texto: str) -> np.ndarray:
    return embebedor([texto])[0]


def _softmax(valores: np.ndarray) -> np.ndarray:
    e = np.exp((valores - valores.max()) / TEMPERATURA)
    return e / e.sum()


def _por_similitud(vector: np.ndarray, categorias: list[dict]) -> np.ndarray:
    matriz = np.stack([_vector_categoria(_texto_categoria(c)) for c in categorias])
    return _softmax(matriz @ vector)


def _por_modelo(vector: np.ndarray, categorias: list[dict], clasificador) -> np.ndarray | None:
    """Probabilidades del modelo entrenado restringidas al catálogo vigente (las inactivas quedan fuera).

    None si el catálogo trae una categoría que el modelo no conoce: hasta reentrenar manda la similitud,
    para que un área nueva funcione de inmediato (sección 10).
    """
    clases = list(clasificador.classes_)
    if any(c["id"] not in clases for c in categorias):
        return None
    proba = clasificador.predict_proba(vector.reshape(1, -1))[0]
    valores = np.array([proba[clases.index(c["id"])] for c in categorias])
    return valores / valores.sum() if valores.sum() > 0 else None


def version_activa() -> str | None:
    archivo = DIR_MODELOS / "ACTIVO"
    return archivo.read_text().strip() if archivo.exists() else None


@lru_cache(maxsize=4)
def _cargar(version: str) -> dict:
    import joblib

    return joblib.load(DIR_MODELOS / version / "clasificador.joblib")


def clasificar(texto: str, areas: list[dict], tipos: list[dict]) -> dict:
    vector = embebedor([texto])[0]
    version = version_activa()
    modelos = _cargar(version) if version else {}

    resultado = {"modelo": MODELO_EMBEDDINGS, "version": version or "similitud", "texto_sha256": hashlib.sha256(texto.encode()).hexdigest()}
    for nombre, categorias in (("area", areas), ("tipo", tipos)):
        if not categorias:
            resultado[nombre] = None
            resultado[f"top_{nombre}"] = []
            continue
        proba = _por_modelo(vector, categorias, modelos[nombre]) if nombre in modelos else None
        metodo = "modelo" if proba is not None else "similitud"
        if proba is None:
            proba = _por_similitud(vector, categorias)
        orden = np.argsort(-proba)[:TOP_K]
        resultado[nombre] = {"id": categorias[orden[0]]["id"], "confianza": round(float(proba[orden[0]]), 4), "metodo": metodo}
        resultado[f"top_{nombre}"] = [{"id": categorias[i]["id"], "confianza": round(float(proba[i]), 4)} for i in orden]
    return resultado


def info_modelo() -> dict:
    version = version_activa()
    meta = DIR_MODELOS / version / "meta.json" if version else None
    datos = json.loads(meta.read_text()) if meta and meta.exists() else {}
    return {"version": version, "entrenado_en": datos.get("entrenado_en"), "ejemplos": datos.get("ejemplos")}
