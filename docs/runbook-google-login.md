# Runbook: inicio de sesión con Google

Solo ingresan cuentas del dominio configurado que además estén registradas y activas en *Configuración → Usuarios*.

## 1. Cliente OAuth (una vez)

1. En Google Cloud Console, en el proyecto `tramite-documentario` (el mismo del buzón), abrir *APIs y servicios → Pantalla de consentimiento OAuth*: tipo **Interno**, scopes `openid`, `email` y `profile`.
2. *Credenciales → Crear ID de cliente OAuth*: tipo **Aplicación web**, nombre `tramite-login`.
3. URI de redirección autorizada: `<APP_URL>/auth/google/callback` (p. ej. `https://tramite.uni.edu.pe/auth/google/callback`).
4. Copiar el ID y el secreto del cliente. Es un cliente distinto del que lee el buzón.

## 2. Configurar el sistema

1. En `.env` del servidor (nunca en el repositorio):
   ```
   GOOGLE_DOMINIO=uni.edu.pe
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   SUPERADMIN_EMAIL=<cuenta del dominio del administrador>
   ```
2. `docker compose exec app php artisan config:clear`
3. `docker compose exec app php artisan db:seed --class=RolesSeeder --force` (crea el superadmin si no existe).
4. Ingresar con la cuenta de `SUPERADMIN_EMAIL` y registrar al resto en *Configuración → Usuarios*.

## 3. Quitar el acceso a una persona

1. *Configuración → Usuarios* → elegir a la persona → **Desactivar**. Su sesión abierta se corta en la siguiente petición.
