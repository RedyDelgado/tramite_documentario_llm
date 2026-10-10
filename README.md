# Trámite Documentario

## Propósito

Sistema institucional de trámite documentario para una universidad (filial), con IA que corre en el propio servidor: ningún documento sale de él. Resuelve tres problemas: correos que se traspapelan y cuesta recuperar, papel físico que se pierde y la falta de visibilidad sobre el estado de cada trámite.

Para eso:

1. Descarga los correos del buzón central de Google Workspace.
2. Convierte cada ingreso (correo, PDF o documento físico escaneado) en un **expediente** con número, responsable, plazo y estado.
3. Clasifica los documentos y sugiere a qué área derivarlos. La IA propone; las reglas y las personas deciden.
4. Muestra un panel con KPIs y semáforos y avisa de lo pendiente.
5. Lleva una **auditoría inmutable** de todo lo que pasa.
6. Genera, numera y envía los documentos salientes, siempre con una aprobación. Este módulo está **apagado** en esta etapa (`SALIENTES_ACTIVO=false`): queda en el código para más adelante.

El alcance, las reglas y las fases están en [PLAN_SISTEMA_TRAMITE_DOCUMENTARIO.md](PLAN_SISTEMA_TRAMITE_DOCUMENTARIO.md), y las decisiones tomadas al construirlo, en [docs/decisiones.md](docs/decisiones.md).

## Requisitos previos

| Herramienta | Versión | Para qué |
|---|---|---|
| Docker Engine / Docker Desktop | 24 o superior, con Compose v2 (`docker compose`) | Levanta todos los servicios |
| Git | 2.40 o superior | Clonar el repositorio |
| Node.js y npm | Node 24 (el mismo del contenedor `vite`) | Opcional: `npm test` y `npm run typecheck` desde el host |
| RAM libre | 4 GB (6 GB con ClamAV; 9 GB con ClamAV y Ollama) | El servicio de IA carga modelos de embeddings |

PHP, PostgreSQL y Python no se instalan en el host: corren en los contenedores. En Windows PHP tiene que ir en el contenedor `app` porque el host no tiene `pcntl` ni `pdo_pgsql`.

Versiones que fijan las imágenes y los manifiestos:

| Pieza | Versión |
|---|---|
| PHP | 8.4 (FPM, Alpine) · `composer.json` pide `^8.3` |
| Laravel / Horizon / Inertia (servidor) | 13 / 5 / 3 |
| React / TypeScript / Vite / Tailwind | 19 / 7 / 8 / 4 |
| PostgreSQL / Redis / Meilisearch | 18 / 8 / 1.16 |
| nginx | 1.29 |
| Python (servicio de IA) | 3.13 con FastAPI 0.118, sentence-transformers y Tesseract (español) |

## Instalación

1. Clonar el repositorio y entrar en la carpeta:
   `git clone https://github.com/RedyDelgado/tramite_documentario_llm.git && cd tramite_documentario_llm`
2. Copiar la configuración: `cp .env.example .env`. Luego cambiar todas las claves `cambiar-*` (sección [Configuración](#configuración)).
3. Construir y levantar los contenedores: `docker compose up -d --build`
4. Instalar las dependencias de PHP: `docker compose exec app composer install`
5. Generar la clave de la aplicación: `docker compose exec app php artisan key:generate`
6. Crear la base y los datos iniciales: `docker compose exec --user www-data app php artisan db:actualizar --seed`
   Este comando reemplaza a `migrate`: migra como dueño de la base y deja al rol de la aplicación sin poder alterar la auditoría.
7. (Opcional) Instalar las dependencias de Node en el host para las pruebas: `npm install`

Si la base de pruebas `tramite_test` no existe (pasa con un volumen creado antes del script de inicio):
`docker compose exec postgres createdb -U tramite tramite_test`

## Configuración

Toda la configuración está en `.env`. [.env.example](.env.example) es la plantilla. `.env` nunca se sube al repositorio.

### Aplicación

| Variable | Qué hace |
|---|---|
| `APP_NAME` | Nombre que aparece en la interfaz y en los correos |
| `APP_ENV` / `APP_DEBUG` | `local` y `true` en desarrollo; `production` y `false` en el servidor |
| `APP_KEY` | Clave de cifrado de Laravel; la genera `key:generate` |
| `APP_PORT` / `APP_URL` | Puerto de nginx en `127.0.0.1` (8100) y la URL con la que se generan los enlaces |
| `APP_TIMEZONE` | `America/Lima`: todas las fechas y plazos se calculan en esta zona |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` | Idioma de la interfaz (`es`) y de los datos de prueba (`es_PE`) |
| `BCRYPT_ROUNDS`, `LOG_*`, `APP_MAINTENANCE_DRIVER` | Valores estándar de Laravel |

### Base de datos

| Variable | Qué hace |
|---|---|
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE` | PostgreSQL dentro de Docker (`postgres:5432`, base `tramite`) |
| `DB_USERNAME` / `DB_PASSWORD` | Rol con el que trabaja la aplicación. Conviene que sea uno propio, por ejemplo `tramite_app` |
| `DB_DUENO_USERNAME` / `DB_DUENO_PASSWORD` | Superusuario dueño de las tablas: migra y respalda. Si se dejan vacíos, se usa el rol de arriba, que entonces sí podría alterar la auditoría |

### Sesión, colas, caché y búsqueda

| Variable | Qué hace |
|---|---|
| `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT` | Sesiones cifradas en Redis; duran 120 minutos |
| `QUEUE_CONNECTION`, `CACHE_STORE` | Colas (que procesa Horizon) y caché en Redis |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | Conexión a Redis; la clave es obligatoria |
| `SCOUT_DRIVER`, `SCOUT_QUEUE` | La búsqueda usa Meilisearch e indexa en cola, así ingresar un documento no tiene que esperar el índice |
| `MEILISEARCH_HOST`, `MEILISEARCH_KEY` | Conexión a Meilisearch. La clave maestra necesita al menos 16 bytes |
| `INERTIA_SSR_ENABLED` | `false`: la página se renderiza en el navegador |
| `VITE_PORT`, `VITE_APP_NAME` | Puerto del servidor de Vite (solo en desarrollo) y nombre que ve el frontend |

### IA local

| Variable | Qué hace |
|---|---|
| `AI_SERVICE_URL` / `AI_SERVICE_TOKEN` | Servicio FastAPI (OCR, extracción y clasificación). Solo se llega a él desde la red interna de Docker y con este token |
| `OLLAMA_URL` / `LLM_MODELO` | Modelo de lenguaje local que sugiere borradores (fase 6) y completa lo que las reglas no leen al registrar en papel. Vacío = desactivado ([runbook](docs/runbook-ia-borradores.md)) |
| `LLM_HILOS` | Hilos de CPU del modelo: los núcleos físicos que tenga Docker. Más hilos que núcleos lo hacen más lento |

### Correo entrante (buzón central)

| Variable | Qué hace |
|---|---|
| `CORREO_ACTIVO` | Con `true`, el job programado descarga correos |
| `CORREO_DRIVER` | `directorio` lee `.eml` de una carpeta (desarrollo); `gmail` lee el buzón real |
| `CORREO_BACKFILL_DESDE` | Desde qué fecha se importa la primera vez |
| `CORREO_INICIO_OPERACION` | Lo recibido antes de esta fecha entra como histórico: sin semáforo ni avisos |
| `CORREO_LOTE` | Cuántos correos se procesan por pasada |
| `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN` | Credenciales OAuth del buzón; cómo obtenerlas en el [runbook de Gmail](docs/runbook-gmail.md) |
| `GMAIL_ETIQUETA` | Etiqueta que se pone en Gmail a lo ya procesado |

### Correo saliente y documentos emitidos

| Variable | Qué hace |
|---|---|
| `SALIENTES_ACTIVO` | `false` (por defecto) apaga «Documentos emitidos»: nadie lo ve ni lo usa |
| `SALIENTES_DRIVER` | `gmail` envía desde el buzón central; `mailer` usa `MAIL_MAILER` |
| `SALIENTES_POR_MINUTO` | Límite de envíos por minuto |
| `SALIENTES_BUZON_CENTRAL` | Dirección del buzón central, que va en copia oculta de cada envío |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Correo del sistema (avisos y resúmenes). En producción, `gmail`: sale de la cuenta principal del buzón y las respuestas de las áreas vuelven a él y se anexan al trámite por su código. `log` en desarrollo solo lo escribe en el log; `MAIL_FROM_ADDRESS` se usa solo si no hay cuenta conectada |

### Registro, seguridad y respaldos

| Variable | Qué hace |
|---|---|
| `REGISTRO_INICIO_ANIO` / `REGISTRO_INICIO_NUMERO` | Primer número del registro al ponerlo en marcha, para continuar la numeración del papel. Después se fija en Configuración → Numeración |
| `ANTIVIRUS_HOST` | `clamav` para analizar adjuntos y subidas. Vacío = no se analizan |
| `RESPALDO_DIRECTORIO`, `RESPALDO_DIAS` | Dónde se guardan los respaldos diarios y cuántos días se conservan |
| `RESPALDO_CLAVE` | Cifra los respaldos. Si se pierde, no se pueden restaurar ([runbook](docs/runbook-respaldos.md)) |
| `GOOGLE_DOMINIO`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Inicio de sesión con Google. Solo entran cuentas del dominio que también estén registradas y activas ([runbook](docs/runbook-google-login.md)) |
| `SUPERADMIN_NAME` / `SUPERADMIN_EMAIL` | Superadmin que crea el seeder; su correo tiene que ser del dominio |

En producción también se definen `COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml` y `DOMINIO` ([runbook de producción](docs/runbook-produccion.md)).

## Ejecución

1. Levantar los servicios: `docker compose up -d`
2. Abrir <http://localhost:8100> y usar «Entrar como superadmin (solo desarrollo)».
3. (Opcional) Antivirus: `docker compose --profile clamav up -d` y `ANTIVIRUS_HOST=clamav` (usa ~1,5 GB de RAM).
4. (Opcional) Borradores con IA: `docker compose --profile llm up -d`, `OLLAMA_URL=http://ollama:11434` y `docker compose exec ollama ollama pull qwen2.5:3b`.
5. Para apagar todo: `docker compose down` (los datos quedan en los volúmenes).

| URL | Qué es |
|---|---|
| `http://localhost:8100` | Aplicación |
| `http://localhost:8100/ui` | Catálogo de componentes (solo local) |
| `http://localhost:8100/horizon` | Colas (solo superadmin) |
| `http://localhost:5174` | Servidor de Vite (recarga en caliente) |

Los comandos de artisan que escriben en `storage/` se corren con `docker compose exec --user www-data app php artisan …`. Si se corren como root, dejan archivos que ni la web ni el respaldo pueden leer.

### Pruebas

| Comando | Qué prueba |
|---|---|
| `docker compose exec app php artisan test` | Backend (base `tramite_test`, nunca la de trabajo) |
| `docker compose exec app vendor/bin/pint --test` | Estilo PHP |
| `npm run typecheck` | Tipos TypeScript |
| `npm test` | Componentes React y paleta sin HEX sueltos |
| `cd ai-service && pytest` | Servicio de IA |

### Operación

| Runbook | Para qué |
|---|---|
| [docs/runbook-produccion.md](docs/runbook-produccion.md) | Instalar en el servidor, preparar el piloto y actualizar |
| [docs/runbook-respaldos.md](docs/runbook-respaldos.md) | Respaldo diario, copia fuera del servidor y restauración |
| [docs/runbook-gmail.md](docs/runbook-gmail.md) | Conectar el buzón central |
| [docs/runbook-google-login.md](docs/runbook-google-login.md) | Inicio de sesión con Google |
| [docs/runbook-ia-borradores.md](docs/runbook-ia-borradores.md) | Borradores de respuesta con el modelo local |

## Estructura de carpetas

```
app/
  Console/       comandos de artisan (db:actualizar, auditoria:verificar, correo:…)
  Correo/        lectura del buzón (drivers directorio y gmail)
  Enums/         estados y tipos fijos
  Http/          controladores, requests y middleware: solo leen y delegan
  Jobs/          trabajos en cola (ingesta, clasificación, envíos)
  Models/        modelos Eloquent
  Policies/      permisos por rol y por área
  Services/      toda la escritura pasa por aquí, que es donde se engancha la auditoría
ai-service/      servicio FastAPI: OCR, extracción y clasificación por embeddings (+ tests)
config/          configuración de Laravel
database/        migraciones, seeders y factories
docker/          Dockerfiles y configuración de nginx, php, postgres, ai y caddy (producción)
docs/            decisiones de implementación y runbooks
resources/
  css/tokens.css colores del sistema (la única fuente de colores)
  js/
    components/  ui (primitivos), forms, data, layouts, domain
    pages/       una carpeta por módulo (expedientes, registro, buzon, salientes…)
    hooks/ lib/ types/
routes/          web.php y console.php (tareas programadas)
tests/           pruebas de backend (Feature) y fixtures
docker-compose.yml       servicios de desarrollo
docker-compose.prod.yml  ajustes para producción
```

## Arquitectura general

Monolito Laravel con frontend React servido por Inertia (sin API aparte), más un servicio de IA independiente. Todo corre en Docker Compose:

| Contenedor | Rol |
|---|---|
| `nginx` | Proxy hacia la app y archivos estáticos (Caddy con HTTPS en producción) |
| `app` | PHP-FPM con Laravel + Inertia |
| `horizon` | Procesa las colas (misma imagen que `app`) |
| `scheduler` | Tareas programadas: descarga de correo, resúmenes, respaldos |
| `vite` | Recarga en caliente de React (solo en desarrollo) |
| `postgres` | Datos y auditoría |
| `redis` | Sesiones, caché y colas |
| `meilisearch` | Búsqueda de texto completo |
| `ai` | FastAPI sin puerto publicado: OCR con Tesseract y clasificación con sentence-transformers |
| `clamav` / `ollama` | Opcionales (perfiles `clamav` y `llm`) |

Flujo:

```
Buzón Google ─► Job ingesta ─► original + hash ─► Expediente ─► Job clasificar ─► servicio IA
Escaneo/QR ─► OCR (servicio IA) ─┘                                   │
                                   reglas (plazo, semáforo, derivación) ◄─ {tipo, área, confianza, datos}
                                                       │
                                  Panel React (Inertia) + notificaciones + auditoría
```

Principios que guían el código:

- **La IA propone; las reglas y las personas deciden.** Semáforos, plazos y permisos son código determinista. Si el servicio `ai` se cae, los documentos se siguen ingresando y la clasificación espera en cola.
- **Originales intocables**, guardados con su hash SHA-256. Nada se borra físicamente.
- **Escritura solo por `app/Services`** y permisos en `app/Policies`; ocultar algo en React no es seguridad.
- **Auditoría inmutable** con cadena de hashes, que se comprueba con `auditoria:verificar`.
- **Interfaz armada solo con el sistema de componentes** de `resources/js/components` y los colores de `tokens.css`.

## Autores e incremento actual

**Autor:** Redy Delgado.

**Incremento actual:** MVP completo (fases 0 a 6) y preparación del piloto.

| Fase | Estado |
|---|---|
| 0 — Base del proyecto | Aprobada |
| 1 — Ingesta de correos, expedientes y búsqueda | En revisión |
| 2 — Panel, roles, plazos y semáforos | En revisión |
| 3 — Documentos físicos (QR + OCR) | En revisión |
| 4 — IA de clasificación (sombra → activo) | En revisión |
| 5 — Documentos salientes y envío automático | En revisión |
| 6 — Recomendaciones y borradores con IA | Hecha (opcional) |
| Piloto — 30 días en un área | En revisión |

Último trabajo: registro de emisores separado de su institución y lectura de informes y de quién los firma en el registro físico. El seguimiento está en el [tablero del proyecto](https://github.com/users/RedyDelgado/projects/3).
