# Runbook: conectar el buzón central de Gmail

Se conecta desde el sistema: **Administración → Buzón central** (solo el superadmin). No hace falta copiar tokens al `.env`.

## 1. Google Cloud (una vez, con el mismo cliente OAuth del inicio de sesión)

1. En Google Cloud Console, abrir el proyecto del cliente OAuth que ya usa el inicio de sesión (`GOOGLE_CLIENT_ID`).
2. *APIs y servicios → Biblioteca*: habilitar **Gmail API**.
3. *Pantalla de consentimiento OAuth → Permisos (scopes)*: agregar `https://www.googleapis.com/auth/gmail.modify` (leer, etiquetar lo procesado y enviar).
   - Con Google Workspace, tipo **Interno**: no requiere verificación de Google.
   - Con una cuenta @gmail.com, tipo **Externo** en modo *Prueba*: agregar la cuenta del buzón en *Usuarios de prueba*. En ese modo Google vence el acceso a los 7 días; para que no venza hay que publicar la app (Google pide verificación para este permiso).
4. *Credenciales → el ID de cliente → URI de redirección autorizados*: agregar `<APP_URL>/buzon/google/callback` (en local, `http://localhost:8100/buzon/google/callback`). La pantalla Buzón central muestra la URI exacta.

## 2. Conectar y descargar

1. Entrar como superadmin → **Buzón central** → **Conectar con Google** → elegir la **cuenta del buzón central** → marcar el permiso de Gmail → *Continuar*. El acceso queda cifrado en la base (con `APP_KEY`), nunca en la auditoría.
2. **Descargar ahora**: lee un lote (`CORREO_LOTE`, 50 por defecto) en la cola; cada correo nuevo aparece en Expedientes como «Por revisar» y en Gmail queda con la etiqueta `tramite/procesado`. Horizon debe estar corriendo.
3. Revisar los primeros expedientes; si están bien, **Encender descarga automática** (cada minuto; requiere el programador, `schedule:work`).
4. La pantalla muestra la última lectura: cuántos entraron, cuántos fallaron y, si Google rechazó el acceso, el motivo (por ejemplo `invalid_grant`: volver a conectar).

`CORREO_BACKFILL_DESDE` fija desde qué fecha se lee; lo anterior a `CORREO_INICIO_OPERACION` entra como histórico. Para dimensionar sin guardar nada: `docker compose exec app php artisan correo:estadisticas`.

### Alternativa sin panel (`.env`)

Si no se conecta desde el panel, vale lo del `.env`: `CORREO_DRIVER=gmail`, `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN` (obtenido con <https://developers.google.com/oauthplayground> y el permiso de arriba) y `CORREO_ACTIVO=true`, luego `php artisan config:clear`. Lo conectado en el panel manda sobre el `.env`.

## 3. Enviar los documentos aprobados

El scope `gmail.modify` ya permite enviar (`users.messages.send`): no hace falta otra autorización.

1. En `.env`: `SALIENTES_DRIVER=gmail` y `SALIENTES_BUZON_CENTRAL=<correo del buzón central>` (remitente y copia oculta).
2. `SALIENTES_POR_MINUTO` (20 por defecto) limita el ritmo para no chocar con los límites de envío de Google.
3. `docker compose exec app php artisan config:clear`. Horizon debe estar corriendo: cada correo sale en cola.
4. Probar con un documento a una sola dirección propia y revisar en Gmail (*Enviados*) el asunto `[REG-…]` y la copia oculta.

## 4. Revocar el acceso

1. <https://myaccount.google.com/permissions> con la cuenta del buzón → quitar el acceso del cliente, o eliminar el ID de cliente en Google Cloud.
2. En **Buzón central → Desconectar** (o, si se usó el `.env`, `CORREO_ACTIVO=false` y borrar `GMAIL_REFRESH_TOKEN`).
