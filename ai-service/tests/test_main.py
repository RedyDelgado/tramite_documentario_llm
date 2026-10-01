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
