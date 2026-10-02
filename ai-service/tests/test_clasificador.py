import re

import numpy as np
import pytest
from fastapi.testclient import TestClient

import clasificador
import main

VOCABULARIO = ["matricula", "notas", "convenio", "cooperacion", "presupuesto", "compra", "silabo", "docente", "laboratorio", "equipo"]


def _embebedor_falso(textos):
    """Bolsa de palabras normalizada: suficiente para probar la lógica sin el modelo real."""
    filas = []
    for t in textos:
        palabras = re.findall(r"\w+", t.lower())
        v = np.array([palabras.count(p) for p in VOCABULARIO], dtype=float) + 1e-3
        filas.append(v / np.linalg.norm(v))
    return np.stack(filas)


@pytest.fixture(autouse=True)
def sin_modelo_real(monkeypatch, tmp_path):
    monkeypatch.setattr(clasificador, "embebedor", _embebedor_falso)
    monkeypatch.setattr(clasificador, "DIR_MODELOS", tmp_path)
    clasificador._vector_categoria.cache_clear()
    clasificador._cargar.cache_clear()
    monkeypatch.setenv("AI_SERVICE_TOKEN", "secreto")


AREAS = [
    {"id": 1, "nombre": "Registro académico", "descripcion": "matricula y notas", "palabras_clave": ["matricula"]},
    {"id": 2, "nombre": "Cooperación", "descripcion": "convenio de cooperacion", "palabras_clave": ["convenio"]},
]


def test_clasifica_por_similitud_con_la_descripcion_y_palabras_clave():
    r = clasificador.clasificar("Solicito firmar un convenio de cooperacion", AREAS, [])

    assert r["area"]["id"] == 2
    assert r["area"]["metodo"] == "similitud"
    assert r["area"]["confianza"] > 0.5
    assert [c["id"] for c in r["top_area"]] == [2, 1]
    assert r["version"] == "similitud"


def test_una_area_nueva_funciona_de_inmediato_y_una_ausente_nunca_se_devuelve():
    nueva = {"id": 3, "nombre": "Laboratorios", "descripcion": "equipo de laboratorio", "palabras_clave": ["laboratorio"]}

    assert clasificador.clasificar("Falla del equipo de laboratorio", [*AREAS, nueva], [])["area"]["id"] == 3
    # Desactivada (no viene en el catálogo), no puede salir.
    assert clasificador.clasificar("Falla del equipo de laboratorio", AREAS, [])["area"]["id"] in (1, 2)


def test_el_endpoint_exige_token_y_texto():
    cliente = TestClient(main.app)
    cuerpo = {"texto": "convenio", "areas": AREAS}

    assert cliente.post("/classify", json=cuerpo).status_code == 401
    assert cliente.post("/classify", json={**cuerpo, "texto": "  "}, headers={"X-AI-Token": "secreto"}).status_code == 422
    r = cliente.post("/classify", json=cuerpo, headers={"X-AI-Token": "secreto"})
    assert r.status_code == 200 and r.json()["area"]["id"] == 2
