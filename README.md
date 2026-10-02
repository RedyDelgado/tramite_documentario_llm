# Trámite Documentario

Sistema de trámite documentario con IA local. El alcance, las reglas y las fases están en [PLAN_SISTEMA_TRAMITE_DOCUMENTARIO.md](PLAN_SISTEMA_TRAMITE_DOCUMENTARIO.md); las decisiones tomadas al construirlo, en [docs/decisiones.md](docs/decisiones.md).

Stack: Laravel 13 + Inertia 3 + React 19 (TypeScript) + Tailwind 4, PostgreSQL 18, Redis + Horizon, Meilisearch, servicio de IA en FastAPI. Todo en Docker Compose.

## Arranque (desarrollo)

1. `cp .env.example .env` y cambiar las claves `cambiar-*`.
2. `docker compose up -d --build`
3. `docker compose exec app composer install`
4. `docker compose exec app php artisan key:generate`
5. `docker compose exec app php artisan migrate --seed`
6. Abrir <http://localhost:8100> → «Entrar como superadmin (solo desarrollo)».

| URL | Qué es |
|---|---|
| `http://localhost:8100` | Aplicación |
| `http://localhost:8100/ui` | Catálogo de componentes (solo local) |
| `http://localhost:8100/horizon` | Colas (solo superadmin) |
| `http://localhost:5174` | Servidor de Vite (recarga en caliente) |

ClamAV (~1,5 GB de RAM) no arranca por defecto: `docker compose --profile clamav up -d`.

Los comandos de artisan que escriben en `storage/` se corren con `docker compose exec --user www-data app php artisan …`: como root dejan archivos que la web y el respaldo no pueden leer.

## Operación

| Runbook | Para qué |
|---|---|
| [docs/runbook-respaldos.md](docs/runbook-respaldos.md) | Respaldo diario, copia fuera del servidor y restauración |
| [docs/runbook-gmail.md](docs/runbook-gmail.md) | Conectar el buzón central |
| [docs/runbook-google-login.md](docs/runbook-google-login.md) | Inicio de sesión con Google |

## Pruebas

| Comando | Qué prueba |
|---|---|
| `docker compose exec app php artisan test` | Backend (base `tramite_test`, nunca la de trabajo) |
| `docker compose exec app vendor/bin/pint --test` | Estilo PHP |
| `npm run typecheck` | Tipos TypeScript |
| `npm test` | Componentes React y paleta sin HEX sueltos |
| `cd ai-service && pytest` | Servicio de IA |

Si la base `tramite_test` no existe (volumen creado antes del script de inicio):
`docker compose exec postgres createdb -U tramite tramite_test`
