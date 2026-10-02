# Decisiones

Registro breve de decisiones tomadas al implementar el plan. La más reciente arriba.

## 2026-10-03 — Fase 2

- **Ingreso con Google: tres barreras.** La cuenta debe traer `hd` igual a `GOOGLE_DOMINIO` (Google lo firma solo para cuentas administradas por ese Workspace; el sufijo del correo no basta), el correo verificado, y existir como usuario activo. Sin `GOOGLE_DOMINIO` el ingreso con Google queda apagado (404), no abierto. Cada intento fallido se audita con su motivo (`fuera_del_dominio`, `no_registrado`, `inactivo`).
- **Un rol por usuario.** Separación de funciones (sección 5): quien registra no cierra. Si un caso real necesita dos roles, se cambia el `Select` por casillas.
- **`usuarios.gestionar` aparte de `configuracion.gestionar`.** Quien asigna roles podría darse cualquier privilegio; delegar la configuración (5.1) no debe incluirlo.
- **Nadie cambia su propio rol ni se desactiva.** Así siempre queda al menos un superadmin activo (el que hace el cambio) sin tener que contarlos.
- **Desactivar corta la sesión abierta** (middleware `UsuarioActivo`): las sesiones viven en Redis y no se pueden buscar por usuario para borrarlas.
- **Plazos**: el del área manda sobre el del tipo; un tipo sin plazo no vence. Días hábiles excluyen sábados, domingos y feriados (globales o del área); el día de ingreso no cuenta, y un plazo que vence en día inhábil pasa al siguiente hábil (TUO Ley 27444). El día de ingreso es el de Lima.
- **El plazo se copia al asignar el tipo** (`ExpedienteService::asignarTipo`): `plazo_dias_aplicado` y `fecha_limite` no cambian aunque luego cambie la configuración. La pantalla que asigna el tipo llega con la derivación (#10).
- **Plazos por área sin vigencia desde/hasta**: la copia en el expediente ya protege lo ingresado; la vigencia solo serviría para programar cambios futuros.
- **Quitar un plazo por área o un feriado es borrado lógico**, con índice único parcial para poder volver a crearlo.
- **Una sola `ConfiguracionPolicy`** (`#[UsePolicy]`) y un `CatalogoService` para todos los catálogos de configuración; reemplazan a `AreaPolicy`.
- **Un titular por área a la vez; suplentes sin límite** (cubren vacaciones solapadas). Quitar a un responsable es cerrar su vigencia, no borrarlo: el historial dice quién veía qué y cuándo.
- **Fusionar un área** pasa sus expedientes y áreas dependientes al destino y la desactiva; sus responsables no se trasladan (el destino conserva los suyos). No se fusiona en una dependiente (ciclo) ni en un área inactiva. Los expedientes movidos se reindexan porque `visible_para` cambia.
- **Pantalla de roles y permisos**: no se hizo. Los roles son los de la sección 5; delegar `configuracion.gestionar` a otro rol espera el pendiente 8.

## 2026-10-02 — Fase 1

- **Un solo campo `estado`** (incluye `por_revisar`, `no_tramite`, `historico`); no se creó `clasificacion_tramite` aparte porque duplicaba la misma información.
- **Numeración con `UPDATE secuencias … RETURNING`** dentro de la transacción que confirma: bloquea la fila igual que `SELECT … FOR UPDATE` y un rollback devuelve el número. Probado con 8 procesos reales (`pcntl_fork`); el test falla si se quita el bloqueo.
- **Todo correo nuevo entra como `por_revisar`** (o `no_tramite` por regla, o `historico` si es anterior a `CORREO_INICIO_OPERACION`). El número se asigna al confirmarlo como trámite.
- **Driver de buzón en carpeta** (`CORREO_DRIVER=directorio`) para desarrollo y tests; marca lo procesado con `.procesado` sin mover el `.eml`. El de Gmail va detrás de la misma interfaz.
- **MIME con `zbateson/mail-mime-parser`; texto de PDF con `pdftotext`** (poppler en la imagen). La extracción no depende del servicio de IA (principio 2).
- **Permisos dentro de Meilisearch**: cada documento lleva `visible_para` (`area:N`, `usuario:N`) y la búsqueda filtra con `whereIn`; el scope `visiblesPara` en la base es la segunda barrera. Un test contra Meilisearch real verifica el total que informa el índice, no solo las filas.
- **Fechas `timestamptz`**: la sesión de PostgreSQL usa `America/Lima` y los modelos con fechas externas (`Correo`, `Expediente`) escriben con desfase (`#[DateFormat('Y-m-d H:i:sP')]`). Sin esto, un correo de las 08:30 −05:00 se guardaba como 08:30 UTC.
- **Consultar un expediente queda auditado** (`expediente.consultado`), pero no se muestra en la línea de tiempo para no llenarla.
- **Pendiente para el despliegue**: rol de base de datos de la aplicación con solo `INSERT`/`SELECT` sobre `auditoria` (hoy la protegen el trigger y la cadena de hashes) y análisis de adjuntos con ClamAV (perfil `clamav`, apagado).

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
