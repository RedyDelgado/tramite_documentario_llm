"""Reentrenamiento versionado (sección 10): regresión logística sobre embeddings, una carpeta por versión."""

import json
import shutil
from datetime import datetime, timezone

import numpy as np

import clasificador

# Por campo hacen falta al menos dos categorías y unos pocos ejemplos de cada una.
MIN_POR_CATEGORIA = 3


class SinDatos(ValueError):
    pass


def entrenar(ejemplos: list[dict]) -> dict:
    """Entrena con ejemplos validados por personas y deja la nueva versión activa."""
    from sklearn.linear_model import LogisticRegression
    import joblib

    textos = [e["texto"] for e in ejemplos]
    vectores = clasificador.embebedor(textos)
    modelos, clases = {}, {}
    for campo in ("area", "tipo"):
        indices = [i for i, e in enumerate(ejemplos) if e.get(f"{campo}_id") is not None]
        etiquetas = np.array([ejemplos[i][f"{campo}_id"] for i in indices])
        valores, cuentas = np.unique(etiquetas, return_counts=True) if len(etiquetas) else (np.array([]), np.array([]))
        validas = set(valores[cuentas >= MIN_POR_CATEGORIA].tolist())
        indices = [i for i in indices if ejemplos[i][f"{campo}_id"] in validas]
        if len(validas) < 2:
            continue
        modelo = LogisticRegression(max_iter=1000, class_weight="balanced")
        modelo.fit(vectores[indices], [ejemplos[i][f"{campo}_id"] for i in indices])
        modelos[campo] = modelo
        clases[campo] = sorted(int(c) for c in validas)

    if not modelos:
        raise SinDatos(f"Hacen falta al menos 2 categorías con {MIN_POR_CATEGORIA} ejemplos validados cada una.")

    version = datetime.now(timezone.utc).strftime("v%Y%m%d%H%M%S")
    destino = clasificador.DIR_MODELOS / version
    destino.mkdir(parents=True)
    joblib.dump(modelos, destino / "clasificador.joblib")
    meta = {
        "version": version,
        "entrenado_en": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "ejemplos": len(ejemplos),
        "clases": clases,
        "modelo_embeddings": clasificador.MODELO_EMBEDDINGS,
    }
    (destino / "meta.json").write_text(json.dumps(meta, ensure_ascii=False, indent=2))
    activar(version)
    return meta


def versiones() -> list[dict]:
    if not clasificador.DIR_MODELOS.exists():
        return []
    return [json.loads(m.read_text()) for m in sorted(clasificador.DIR_MODELOS.glob("v*/meta.json"))]


def activar(version: str | None) -> None:
    """Cambia la versión activa; None vuelve a la similitud sin entrenar."""
    archivo = clasificador.DIR_MODELOS / "ACTIVO"
    if version is None:
        archivo.unlink(missing_ok=True)
    else:
        if not (clasificador.DIR_MODELOS / version / "clasificador.joblib").exists():
            raise FileNotFoundError(version)
        # Escritura atómica: una clasificación en curso nunca lee un archivo a medias.
        temporal = archivo.with_suffix(".tmp")
        temporal.write_text(version)
        shutil.move(temporal, archivo)
    clasificador._cargar.cache_clear()
