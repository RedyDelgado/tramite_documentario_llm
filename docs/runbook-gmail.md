# Runbook: conectar el buzón central de Gmail

Requisito previo: autorización por escrito del administrador de Google Workspace y de la dirección (pendiente 1 del plan). Hasta tenerla, usar un buzón de prueba propio.

## 1. Cliente OAuth (una vez)

1. En Google Cloud Console, con la cuenta de Workspace, crear un proyecto `tramite-documentario`.
2. *APIs y servicios → Biblioteca*: habilitar **Gmail API**.
3. *Pantalla de consentimiento OAuth*: tipo **Interno**; agregar el scope `https://www.googleapis.com/auth/gmail.modify`.
4. *Credenciales → Crear ID de cliente OAuth*: tipo **Aplicación web**; URI de redirección autorizada `https://developers.google.com/oauthplayground`.
5. Copiar el ID y el secreto del cliente.

## 2. Refresh token de la cuenta del buzón (una vez)

1. Abrir <https://developers.google.com/oauthplayground> en una ventana con la sesión de la **cuenta del buzón central**.
2. Engranaje → marcar *Use your own OAuth credentials* → pegar ID y secreto.
3. En *Step 1* escribir `https://www.googleapis.com/auth/gmail.modify` → *Authorize APIs* → aceptar.
4. *Step 2* → *Exchange authorization code for tokens* → copiar el **Refresh token**.
5. En el engranaje, quitar las credenciales propias del Playground.

## 3. Configurar el sistema

1. En `.env` del servidor (nunca en el repositorio):
   ```
   CORREO_DRIVER=gmail
   GMAIL_CLIENT_ID=...
   GMAIL_CLIENT_SECRET=...
   GMAIL_REFRESH_TOKEN=...
   CORREO_BACKFILL_DESDE=2026-01-01
   CORREO_INICIO_OPERACION=<fecha de puesta en marcha>
   CORREO_ACTIVO=false
   ```
2. `docker compose exec app php artisan config:clear`
3. Dimensionar sin guardar nada: `docker compose exec app php artisan correo:estadisticas`
4. Probar un lote chico: `docker compose exec -u www-data app php artisan correo:importar --limite=5`
5. Revisar en el panel que los 5 expedientes estén bien y que en Gmail tengan la etiqueta `tramite/procesado`.
6. Activar el job programado: `CORREO_ACTIVO=true` → `docker compose exec app php artisan config:clear`.

## 4. Revocar el acceso

1. <https://myaccount.google.com/permissions> con la cuenta del buzón → quitar el acceso del cliente, o eliminar el ID de cliente en Google Cloud.
2. Poner `CORREO_ACTIVO=false` y borrar `GMAIL_REFRESH_TOKEN` del `.env`.
