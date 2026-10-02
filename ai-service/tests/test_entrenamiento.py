import time

import pytest
from fastapi.testclient import TestClient

import clasificador
import entrenamiento
import main
from tests.test_clasificador import AREAS, sin_modelo_real  # noqa: F401  (fixture autouse)

CABECERA = {"X-AI-Token": "secreto"}


def _ejemplos():
    # Etiquetas «contraintuitivas» a propósito: el modelo entrenado debe imponerse a la similitud.
    return [
        *[{"texto": f"convenio de cooperacion {i}", "area_id": 1, "tipo_id": 10} for i in range(4)],
        *[{"texto": f"matricula y notas {i}", "area_id": 2, "tipo_id": 20} for i in range(4)],
    ]


def test_entrenar_crea_una_version_activa_que_manda_sobre_la_similitud():
    meta = entrenamiento.entrenar(_ejemplos())

    assert meta["ejemplos"] == 8 and meta["clases"]["area"] == [1, 2]
    assert clasificador.version_activa() == meta["version"]
    r = clasificador.clasificar("firmar un convenio de cooperacion", AREAS, [])
    assert r["area"] == {"id": 1, "confianza": r["area"]["confianza"], "metodo": "modelo"}
    assert r["version"] == meta["version"]


def test_un_area_que_el_modelo_no_conoce_vuelve_a_la_similitud():
    entrenamiento.entrenar(_ejemplos())
    nueva = {"id": 3, "nombre": "Laboratorios", "descripcion": "equipo de laboratorio", "palabras_clave": []}

    r = clasificador.clasificar("falla del equipo de laboratorio", [*AREAS, nueva], [])

    assert r["area"]["id"] == 3 and r["area"]["metodo"] == "similitud"


def test_revertir_a_una_version_anterior_o_a_la_similitud():
    primera = entrenamiento.entrenar(_ejemplos())["version"]
    time.sleep(1.1)
    segunda = entrenamiento.entrenar(_ejemplos())["version"]
    cliente = TestClient(main.app)

    assert [v["version"] for v in cliente.get("/models", headers=CABECERA).json()["versiones"]] == [primera, segunda]
    assert cliente.post("/models/activate", json={"version": primera}, headers=CABECERA).json() == {"activa": primera}
    assert cliente.post("/models/activate", json={"version": "v1"}, headers=CABECERA).status_code == 404
    assert cliente.post("/models/activate", json={"version": None}, headers=CABECERA).json() == {"activa": None}
    assert clasificador.clasificar("convenio", AREAS, [])["version"] == "similitud"


def test_sin_suficientes_ejemplos_no_entrena():
    with pytest.raises(entrenamiento.SinDatos):
        entrenamiento.entrenar(_ejemplos()[:5])
    r = TestClient(main.app).post("/train", json={"ejemplos": [{"texto": "x", "area_id": 1}]}, headers=CABECERA)
    assert r.status_code == 422
