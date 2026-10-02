import shutil

import pytest
from fastapi.testclient import TestClient

import main

cliente = TestClient(main.app)


def test_health_sin_token():
    assert cliente.get("/health").json() == {"estado": "ok"}


def test_model_info_exige_token(monkeypatch):
    monkeypatch.setenv("AI_SERVICE_TOKEN", "secreto")
    assert cliente.get("/model-info").status_code == 401
    assert cliente.get("/model-info", headers={"X-AI-Token": "otro"}).status_code == 401
    assert cliente.get("/model-info", headers={"X-AI-Token": "secreto"}).status_code == 200


def test_model_info_cerrado_si_no_hay_token_configurado(monkeypatch):
    monkeypatch.delenv("AI_SERVICE_TOKEN", raising=False)
    assert cliente.get("/model-info", headers={"X-AI-Token": ""}).status_code == 401


def test_ocr_exige_token(monkeypatch):
    monkeypatch.setenv("AI_SERVICE_TOKEN", "secreto")
    assert cliente.post("/ocr", content=b"x", headers={"Content-Type": "image/png"}).status_code == 401


def test_ocr_rechaza_tipos_no_soportados(monkeypatch):
    monkeypatch.setenv("AI_SERVICE_TOKEN", "secreto")
    r = cliente.post("/ocr", content=b"x", headers={"X-AI-Token": "secreto", "Content-Type": "application/zip"})
    assert r.status_code == 415


def _pdf_con_texto(texto: str) -> bytes:
    """PDF mínimo de una página con el texto en Helvetica grande: suficiente para Tesseract."""
    flujo = f"BT /F1 36 Tf 72 700 Td ({texto}) Tj ET".encode()
    objetos = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>",
        b"<< /Length " + str(len(flujo)).encode() + b" >>\nstream\n" + flujo + b"\nendstream",
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ]
    salida, offsets = b"%PDF-1.4\n", []
    for i, obj in enumerate(objetos, 1):
        offsets.append(len(salida))
        salida += f"{i} 0 obj\n".encode() + obj + b"\nendobj\n"
    xref = len(salida)
    salida += f"xref\n0 {len(objetos) + 1}\n0000000000 65535 f \n".encode()
    salida += b"".join(f"{o:010d} 00000 n \n".encode() for o in offsets)
    salida += f"trailer\n<< /Size {len(objetos) + 1} /Root 1 0 R >>\nstartxref\n{xref}\n%%EOF".encode()
    return salida


@pytest.mark.skipif(shutil.which("tesseract") is None, reason="Tesseract solo está en el contenedor")
def test_ocr_lee_un_pdf_escaneado(monkeypatch):
    monkeypatch.setenv("AI_SERVICE_TOKEN", "secreto")
    r = cliente.post("/ocr", content=_pdf_con_texto("OFICIO MULTIPLE 045"),
                     headers={"X-AI-Token": "secreto", "Content-Type": "application/pdf"})
    assert r.status_code == 200
    assert r.json()["paginas"] == 1
    assert "OFICIO MULTIPLE 045" in r.json()["texto"]
