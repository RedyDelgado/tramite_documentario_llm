# Runbook: sugerir respuestas con IA local (fase 6)

En el detalle de un expediente, quien lo atiende ve **Sugerir respuesta**: un modelo de lenguaje que corre en el propio servidor (Ollama) propone el texto del oficio de respuesta. Es solo una sugerencia: se revisa, se completa donde dice `[COMPLETAR]`, se pega en el Word y el documento sigue el flujo normal (borrador → aprobación con número → documento final → envío). Nada sale a internet y nada se envía sin aprobación.

## Activar

1. Levantar el contenedor (no arranca por defecto):
   ```
   docker compose --profile llm up -d ollama
   ```
2. Descargar el modelo una vez (~2 GB):
   ```
   docker compose exec ollama ollama pull qwen2.5:3b
   ```
3. En `.env`: `OLLAMA_URL=http://ollama:11434` (y `LLM_MODELO` si se usa otro modelo) → `docker compose exec app php artisan config:clear`.
4. Abrir un expediente en atención como su responsable: aparece **Sugerir respuesta**.

## Recursos

- En CPU, una respuesta tarda entre 30 y 90 s y usa ~3 GB de RAM mientras responde. Con el VPS de 12 GB alcanza; con 8 GB conviene dejarlo apagado.
- Cada sugerencia queda en la auditoría (`ia.borrador_sugerido`, con el modelo y la longitud; el texto no se guarda).

## Desactivar

Vaciar `OLLAMA_URL` y `docker compose stop ollama`: el botón desaparece.
