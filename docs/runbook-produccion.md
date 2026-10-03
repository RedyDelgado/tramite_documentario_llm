# Runbook: instalación en producción y puesta en marcha del piloto

Para instalar el sistema en el servidor del piloto (30 días, una sola área; sección 16 del plan) y actualizarlo después. Los comandos se ejecutan en la carpeta del proyecto, en el servidor.

## Antes de empezar

| Qué | Detalle |
|---|---|
| Servidor | Linux con Docker Engine y el plugin `docker compose` (por ejemplo, Ubuntu 24.04 LTS). Pendiente 3: físico o VM. |
| Recursos | **4 núcleos, 8 GB de RAM y 100 GB de disco** como punto de partida. En reposo el sistema usa ~1,2 GB (el servicio de IA, ~700 MB); el OCR ocupa un núcleo por escaneo mientras dura. El disco crece con los originales y con 30 días de respaldos. |
| Nombre y HTTPS | Un nombre (por ejemplo `tramite.<dominio-institucional>`) que apunte al servidor. Google exige HTTPS para iniciar sesión. Dos caminos: **Let's Encrypt automático**, si los puertos 80 y 443 del servidor son alcanzables desde internet; o **el certificado de la institución**, si el servidor solo es interno. |
| Google | Cliente OAuth para el inicio de sesión ([runbook](runbook-google-login.md)). Para el buzón y los envíos, **autorización por escrito** (pendiente 1) y el [runbook de Gmail](runbook-gmail.md). |
| Decisiones | Área del piloto, catálogo inicial (pendiente 4) y número inicial del correlativo (pendiente 9, por defecto `N°00038`). |

## 1. Instalar

```bash
git clone https://github.com/RedyDelgado/tramite_documentario_llm.git tramite
cd tramite
cp .env.example .env
```

Editar `.env` (nunca va al repositorio):

| Variable | Valor en producción |
|---|---|
| `COMPOSE_FILE` | `docker-compose.yml:docker-compose.prod.yml` (agrega HTTPS y quita el servidor de desarrollo) |
| `DOMINIO` | El nombre del servidor, sin `https://` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | `https://<DOMINIO>` |
| `LOG_LEVEL` | `warning` |
| `MEILI_ENV` | `production` |
| `DB_DUENO_USERNAME` / `DB_USERNAME` | `tramite` / `tramite_app`: el dueño migra y respalda; la aplicación entra con su propio rol y no puede alterar la auditoría |
| `DB_DUENO_PASSWORD`, `DB_PASSWORD`, `REDIS_PASSWORD`, `MEILISEARCH_KEY`, `AI_SERVICE_TOKEN` | Una clave distinta para cada una: `openssl rand -hex 24` |
| `GOOGLE_DOMINIO`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Del runbook de inicio de sesión |
| `SUPERADMIN_EMAIL` | Cuenta institucional de quien administra el sistema |
| `REGISTRO_INICIO_NUMERO` | Siguiente número del registro en papel (pendiente 9) |
| `MAIL_MAILER` | `log` hasta tener el buzón autorizado; después `gmail` (el resumen diario y los avisos salen por la cuenta del buzón central, sin SMTP) |
| `MAIL_FROM_ADDRESS` | La cuenta del buzón central |
| `RESPALDO_CLAVE` | `openssl rand -base64 32`: cifra los respaldos. Guárdala con la copia del `.env`; sin ella, los respaldos no se pueden leer |
| `COMPOSE_PROFILES` / `ANTIVIRUS_HOST` | `clamav` / `clamav`: los adjuntos y las subidas se analizan con ClamAV (~1 GB de RAM) |

Con el certificado de la institución (servidor solo interno): copiar `certificado.pem` y `clave.pem` a `docker/caddy/certs/` y agregar en `docker/caddy/Caddyfile`, dentro del bloque, `tls /certs/certificado.pem /certs/clave.pem`. Los certificados no se suben al repositorio.

Levantar y preparar:

```bash
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan key:generate --force
docker compose run --rm --no-deps vite sh -c "npm ci && npm run build"
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec --user www-data app php artisan db:actualizar --seed
docker compose exec --user www-data app php artisan scout:sync-index-settings
docker compose exec --user www-data app php artisan optimize
```

`db:actualizar --seed` migra con el rol dueño, crea el rol de la aplicación (que en la auditoría solo puede insertar y leer) y carga los roles, las reglas de correo no trámite y el superadmin; en producción no crea datos de ejemplo. Guarda una copia del `.env` fuera del servidor ([runbook de respaldos](runbook-respaldos.md#guardar-el-env-aparte)).

Comprobar:

- `https://<DOMINIO>/up` responde «Application up».
- El superadmin entra con Google; una cuenta del dominio que no está registrada no entra.
- `https://<DOMINIO>/ui` y el botón «Entrar como…» **no existen** (solo en desarrollo).
- `docker compose ps`: todos los servicios `Up`, sin `vite` y con `clamav` (tarda unos minutos en quedar `healthy` mientras carga las firmas).

## 2. Preparar el piloto (superadmin)

Desde el panel, en este orden:

1. **Áreas**: el área piloto, la dirección y mesa de partes. Con siglas, porque se usan en la numeración de los documentos emitidos.
2. **Usuarios**: solo quienes participan: director, administrativo de mesa de partes y coordinador del área piloto. Después, **Responsables**: el coordinador como titular del área.
3. **Tipos de trámite**, **Plazos por área** y **Feriados** del año.
4. **Tipos de documento** (quién aprueba la salida), **Plantillas**, **Emisores** frecuentes, **Instrucciones** y **Ubicaciones físicas**.
5. **Inteligencia artificial**: dejarla en **modo sombra** durante el piloto (es el valor inicial). Propone sin actuar, y así se mide su acierto con las decisiones reales.
6. **Trámites que ya estaban en curso** (no se importa el registro en papel): a medida que se muevan, el administrativo los registra en **Registrar papel** marcando «Ya estaba en el registro en papel», con su N° del cuaderno (del 1 al 37 en 2026) y su fecha real de ingreso. Conservan su número y su plazo corre desde esa fecha; lo nuevo se numera desde el N°00038.

## 3. Conectar el buzón (solo con la autorización escrita)

1. Configurar Gmail según el [runbook](runbook-gmail.md), con `CORREO_DRIVER=gmail` y `SALIENTES_DRIVER=gmail`.
2. `CORREO_INICIO_OPERACION` = fecha de inicio del piloto: lo anterior entra como histórico, sin semáforo ni avisos.
3. Medir el volumen real (pendiente 2) **antes** de activar la ingesta:
   ```bash
   docker compose exec --user www-data app php artisan correo:estadisticas
   ```
4. `CORREO_ACTIVO=true`, `MAIL_MAILER=gmail` y aplicar los cambios:
   ```bash
   docker compose exec --user www-data app php artisan optimize
   docker compose restart horizon scheduler
   ```

## 4. Durante el piloto

- **Cada mañana**: debe existir el respaldo de la madrugada y haberse copiado fuera del servidor ([runbook](runbook-respaldos.md)).
- **Cada semana**: revisar Horizon (`/horizon`, solo superadmin), en especial los trabajos fallidos, y **Notificaciones**: un resumen diario rebotado o fallido es un coordinador que no recibe sus pendientes (dirección mal escrita o cuenta dada de baja).
- **Al cierre** (sección 16, punto 7): adopción («Respondidos desde el sistema»), tiempo de atención y semáforos en **Inicio**; acierto y correcciones de la IA en **Inteligencia artificial**. Entra como director para verlos completos: cada rol ve solo lo suyo.

## Actualizar a una versión nueva

```bash
docker compose exec --user www-data app php artisan respaldo:crear
git pull
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose run --rm --no-deps vite sh -c "npm ci && npm run build"
docker compose exec --user www-data app php artisan db:actualizar
docker compose exec --user www-data app php artisan optimize
docker compose exec --user www-data app php artisan horizon:terminate
docker compose restart scheduler
```

`horizon:terminate` deja terminar el trabajo en curso y Docker vuelve a levantarlo con el código nuevo. Si algo sale mal, se vuelve al respaldo tomado al principio ([restaurar](runbook-respaldos.md#restaurar)).

**Cada cambio en `.env` exige `php artisan optimize`**: en producción la configuración queda en caché y no se vuelve a leer el `.env`.
