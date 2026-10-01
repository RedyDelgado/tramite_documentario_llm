# Decisiones

Registro breve de decisiones tomadas al implementar el plan. La más reciente arriba.

## 2026-10-01 — Fase 0

- **Inertia en vez de API REST + SPA separada.** Una sola app: sesión, CSRF, Policies y (fase 2) Socialite de Laravel sin CORS ni Sanctum.
- **Sin React Hook Form, Zod ni TanStack Table.** `useForm` de Inertia y la validación de Laravel (Form Requests) cubren los formularios; `DataTable` propio con orden y paginación del servidor cubre las tablas. Se añaden si un caso real los necesita (p. ej. validación en cliente de un formulario largo).
- **Tailwind 4: la configuración vive en `resources/css/app.css` (`@theme`)**, no en `tailwind.config.js`. Se anulan paleta, tipografía, radios y sombras por defecto; solo existen los tokens de 5.3.
- **Sin `tailwind-merge`.** Las variaciones se expresan como variantes `cva` del componente.
- **Recharts y Combobox/DatePicker** llegan con su primer uso (KPIs de la fase 2, emisores de la fase 1/3).
- **Puertos 8100 (app) y 5174 (Vite), solo en `127.0.0.1`.** El 8090 y el 5173 los usa `academico_web` en la misma PC.
- **ClamAV tras el perfil `clamav`.** Consume ~1,5 GB y la VM de WSL es compartida; se activa cuando la fase 1 analice adjuntos.
- **Meilisearch fijado en `v1.16`.** Cambiar de versión exige migrar el índice (dump); se sube a propósito, no con `latest`.
- **Acceso local de desarrollo** (`POST /dev/entrar`, `/ui`): rutas registradas solo con `APP_ENV=local`; un test verifica que no existen fuera de local.
- **Horizon**: el paquete lo abre a todos en local; aquí exige el rol superadmin también en local.
- **`users.password` nulo**: el acceso será con Google (fase 2).
- **Tests contra PostgreSQL** (`tramite_test`, forzado en `phpunit.xml`) porque el esquema usa `jsonb`; nunca SQLite.
- **Zona horaria `America/Lima`** en `config/app.php`.
- **Rendimiento en Docker sobre Windows** (bind mount): `docker/php/local.ini` con `opcache.revalidate_freq = 30` bajó cada request de 12–14 s a < 1 s (mismo criterio que academico_web); los cambios de PHP tardan hasta 30 s en verse. Vite sondea solo el frontend cada 1 s (de 940 MB a ~80 MB de RAM). La primera carga tras reiniciar `app` o `vite` es lenta (opcache y dependencias en frío).
- **nginx resuelve `app` por el DNS de Docker en cada request**: recrear el contenedor `app` ya no deja a nginx en 502.
- **`horizon` y `scheduler` corren como `www-data`**, igual que php-fpm, para no dejar carpetas de `storage/` que php-fpm no pueda escribir.
- **Traducciones en `lang/es/validation.php`** (completo); sin `lang/en`. Un test fija el mensaje en español.
