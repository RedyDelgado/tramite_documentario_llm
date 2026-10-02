FROM python:3.13-slim

# OCR local (sección 10): Tesseract con español y poppler para rasterizar PDF escaneados.
RUN apt-get update \
    && apt-get install -y --no-install-recommends tesseract-ocr tesseract-ocr-spa poppler-utils \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# El modelo de embeddings se descarga al construir; en ejecución el servicio no sale a internet.
ENV HF_HOME=/srv/hf
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('intfloat/multilingual-e5-small')" \
    && chmod -R a+rX /srv/hf
ENV HF_HUB_OFFLINE=1 TRANSFORMERS_OFFLINE=1

COPY . .
# Modelos entrenados (models/vAAAAMMDDHHMM/); el volumen los conserva entre despliegues.
RUN mkdir -p /srv/models && chown nobody /srv/models

USER nobody
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]
