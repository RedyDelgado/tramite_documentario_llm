# PLAN MAESTRO: Sistema de Trámite Documentario con IA local

> Documento para Claude Code. Léelo completo antes de escribir código. Trabaja **fase por fase**, no avances a la siguiente sin cumplir los criterios de aceptación de la actual y sin confirmación del usuario.

---

## 1. Objetivo

Sistema institucional (universidad, filial) que:

1. Descarga automáticamente los correos de **un buzón central** de Google Workspace.
2. Convierte todo ingreso (correo, PDF, documento físico escaneado) en un **expediente único** con número, responsable, plazo y estado.
3. Clasifica documentos y sugiere el área de derivación con **IA 100% local** (nada sale del servidor).
4. Muestra un **panel con KPIs y semáforos** y notifica pendientes por correo/Telegram.
5. Mantiene **auditoría detallada e inmutable** de todo.
6. Genera, numera y envía los **documentos salientes**, siempre con aprobación.

Problemas que resuelve: correos que se traspapelan y son difíciles de recuperar, papel físico que se pierde, y falta de visibilidad del estado de cada trámite.

## 1.1 Fuera de alcance (v1)

- Firma digital integrada (la firma se mantiene fuera del sistema).
- Que el escaneo sustituya al original: la copia digital es solo de consulta.
- Importación masiva por CSV o archivos del catálogo.
- Tema oscuro y aplicación móvil nativa.
- Integración con otros sistemas de la institución (ERP, mesa de partes virtual).
- Lectura de varios buzones: el sistema opera con un único buzón central.
- Respuestas o documentos enviados sin aprobación humana.

## 2. Principios de diseño (no negociables)

- **La IA propone, las reglas y los humanos deciden.** Los semáforos, plazos y permisos son código determinista, nunca ML.
- **El sistema debe funcionar completo sin IA** (fases 1 a 3 y 5). La IA es una pieza enchufable y reemplazable.
- **Originales intocables:** se guardan sin modificar, con hash SHA-256. Nuevas versiones = nuevo registro, nunca sobrescribir.
- **Nada se borra físicamente:** solo borrado lógico/archivado.
- **Todo evento relevante se audita** (ver sección 9).
- **Mínimo privilegio y separación de funciones.**
- **Idempotencia:** reprocesar un correo o documento nunca debe duplicar expedientes.
- **Captura mínima:** ningún dato se escribe dos veces; el usuario confirma lo que el sistema ya sabe (7.3.5).
- Privacidad: datos personales de alumnos y docentes (Ley 29733, Perú).

## 3. Stack (decidido)

| Capa | Tecnología |
|---|---|
| Backend | **Laravel (última estable)**: controladores delgados, Services, Policies, Form Requests y *API Resources* para dar forma a los datos que consume la interfaz |
| Frontend | **React + TypeScript** servido por **Inertia.js** (sin API REST ni SPA aparte: una sola app, sesión y CSRF de Laravel), **Vite**, **Tailwind CSS** y **sistema de componentes propio** (5.2). **Sin Livewire ni Filament** |
| Componentes base | **Radix UI** (primitivos accesibles sin estilo) + `class-variance-authority` para variantes, `useForm` de Inertia con validación en Laravel (formularios), `DataTable` propio con orden y paginación del servidor (tablas), **Recharts** desde la fase 2 (gráficos), `@fluentui/react-icons` (iconos). Todo se viste con los tokens de 5.3; no se usa ninguna librería de UI con estilo propio (MUI, Ant, Chakra, etc.) |
| Base de datos | **PostgreSQL** (idioma de búsqueda `spanish`, JSONB) |
| Colas | **Redis + Laravel Horizon** |
| Búsqueda | **Meilisearch** vía **Laravel Scout** |
| Correo (Google Workspace) | Lectura vía **OAuth2** (IMAP con XOAUTH2 con `webklex/laravel-imap`, o **Gmail API** con `google/apiclient`), detrás de una interfaz `MailboxDriver` intercambiable. Envío por Gmail API/SMTP de Google |
| Roles y permisos | `spatie/laravel-permission` + Policies |
| Autenticación | **Laravel Socialite** (Google, restringido al dominio) sobre usuarios previamente registrados |
| Servicio de IA | **Python + FastAPI** (solo escucha en red interna de Docker) |
| IA local | `multilingual-e5-small` (embeddings), `scikit-learn` (clasificador), regex para datos simples, **Tesseract + ocrmypdf** (OCR español), `pdfplumber` |
| LLM local (fase posterior, opcional) | Ollama con modelo pequeño; **revisar el mejor disponible en ese momento para el hardware** |
| Despliegue | **Docker Compose** sobre Ubuntu |
| QR | `simplesoftwareio/simple-qrcode` (o equivalente) |
| Antivirus | **ClamAV** (contenedor) para analizar adjuntos |

## 4. Arquitectura y contenedores

Servicios en `docker-compose.yml`:

- `nginx`: proxy hacia la app (HTTPS en producción); sirve los *assets* compilados de React.
- `app`: PHP-FPM con Laravel. El frontend se compila con Vite en una etapa de build de la imagen (Node solo en el build, no en producción).
- `vite` (solo desarrollo): servidor de Vite con recarga en caliente para React.
- `horizon`: worker de colas (misma imagen que `app`).
- `scheduler`: `php artisan schedule:work`.
- `postgres`: con volumen persistente.
- `redis`.
- `meilisearch`: con clave maestra y volumen.
- `ai`: FastAPI (Python), **sin puerto publicado al exterior**, solo accesible desde `app`/`horizon`.
- `clamav` (recomendado): análisis de adjuntos antes de procesarlos.
- (Fase 6, opcional) `ollama`.

Volúmenes: `storage/originals` (documentos originales, solo lectura para la app tras escritura), `postgres_data`, `meili_data`. Variables sensibles solo en `.env`, nunca en el repositorio. Incluir `.env.example`.

Flujo general:

```
Buzón Google ─► Job ingesta ─► original + hash ─► Expediente ─► Job clasificar ─► servicio IA
Escaneo/QR ─► OCR (servicio IA) ─┘                                   │
                                             reglas (plazo, semáforo, derivación) ◄─ resultado {tipo, área, confianza, datos}
                                                       │
                                  Panel React (Inertia) + notificaciones + auditoría
```

Si el servicio de IA se cae, los correos se siguen guardando y la clasificación queda en cola para después.

## 5. Roles y permisos (RBAC)

| Rol | Ve | Puede |
|---|---|---|
| **Superadmin** | Configuración y auditoría; **no** el contenido de los trámites (aunque técnicamente pueda) | Usuarios, roles, áreas, tipos, plazos, umbrales, modelos, reglas |
| **Director** | Todo, solo lectura + KPIs | Derivar, reasignar, ver semáforos globales |
| **Administrativo** | Ingreso, bandeja de revisión | Registrar, escanear, derivar, emitir documentos, validar correcciones de IA |
| **Coordinador** | Solo lo de sus áreas (el coordinador de escuela ve todas las áreas de su escuela) | Atender, responder, solicitar cierre |
| **Otros** | Solo lo asignado por expediente | Acceso limitado |

Reglas: quien registra no cierra; quien administra no altera trámites; el cierre y la aprobación de documentos salientes requieren el rol que se configure por tipo (director o coordinador del área). Cada usuario es independiente (login propio, sin cuentas compartidas). **El acceso exige un usuario previamente registrado y activo: pertenecer al dominio de Google no concede acceso.** Con inicio de sesión con Google, la verificación en dos pasos se exige en Workspace; se recomienda no mantener contraseñas locales para superadmin, director ni administrativo (si hay un acceso local de emergencia, con 2FA propio).

## 5.1 Configuración programable (requisito clave: nada de áreas ni plazos en el código)

Todo lo organizacional se administra desde el panel, **sin tocar código ni desplegar**:

- **Áreas:** crear, editar, desactivar, fusionar y reordenar. Con jerarquía (`parent_id`: escuela → áreas independientes), descripción y palabras clave (que alimentan la clasificación).
- **Responsables por área:** titular y suplente con vigencia (desde/hasta), cobertura por vacaciones.
- **Tipos de trámite:** crear/editar/desactivar con su plazo.
- **Plazos:** configurables **por tipo de trámite**, con **override opcional por área** (ej.: el mismo tipo tiene otro plazo en un área). Configuración de días hábiles vs calendario, **calendario de feriados** editable y umbrales del semáforo (porcentaje restante, días sin movimiento).
- **Reglas de derivación editables:** tipo → área, palabras clave, remitentes o dominios conocidos → área/responsable, con prioridad y activación.
- **Catálogos:** emisores, tipos de documento, instrucciones frecuentes de derivación, ubicaciones físicas, reglas de correo no trámite, y formato y valor inicial de las numeraciones.
- **Umbrales de IA y modo sombra** (ver sección 10).
- **Permisos delegables:** el permiso `configuracion.gestionar` lo tiene el superadmin y puede delegarse a otro rol (ej. administrativo) sin dar acceso al resto.

Reglas de integridad:
- Los cambios de plazo **no alteran expedientes ya ingresados**: cada expediente guarda una *copia del plazo aplicado* (`plazo_dias_aplicado`, `fecha_limite`) al momento de su ingreso. Opción explícita de "recalcular" con auditoría.
- Un área con expedientes abiertos no se elimina: solo se **desactiva** o se **fusiona** reasignando sus expedientes (todo auditado).
- Cada cambio de configuración queda en la auditoría con valor anterior y nuevo.
- Los *seeders* solo crean datos de ejemplo mínimos y un superadmin inicial; el catálogo real se carga **manualmente desde el panel** (formularios del panel). No se implementa importación por CSV ni por archivos.

## 5.2 Frontend React y sistema de componentes reutilizables

Todo lo que ve el usuario (administración, bandejas, dashboard, registro de papel, línea de tiempo, lector de QR) es **React + Inertia**, construido sobre **un único sistema de componentes propio** que implementa al pie de la letra el diseño de 5.3. No existe un segundo camino para pantallas «a medida»: lo especial se compone con las mismas piezas.

**Capas (cada una solo usa la de abajo)**

1. **Tokens** (`resources/css/tokens.css`): única fuente de color, tipografía, espaciado, radios y sombra (5.3).
2. **Primitivos de UI** (`resources/js/components/ui/`): `Button`, `IconButton`, `Input`, `Textarea`, `Select`, `Combobox`, `DatePicker`, `Checkbox`, `Switch`, `Badge`, `Card`, `Tabs`, `Dialog`, `Drawer`, `Popover`, `Tooltip`, `Toast`, `Skeleton`, `EmptyState`, `Spinner`. Basados en Radix, con variantes (`cva`) y estados (hover, foco, deshabilitado, error, carga) definidos **una sola vez**.
3. **Compuestos de formulario y datos** (`components/forms/`, `components/data/`): `FormField` (etiqueta + control + ayuda + error), `FormSection`, `DataTable` (cabecera fija, orden, selección, paginación, fila en hover/seleccionada, estado vacío y de carga), `FilterBar`, `CommandBar` (acciones a la izquierda, filtros y búsqueda a la derecha), `ConfirmDialog`, `FileDropzone`, `KpiCard`, `ChartCard`.
4. **Compuestos de dominio** (`components/domain/`): `SemaforoBadge` (siempre icono + texto), `EstadoBadge`, `ExpedienteDrawer`, `LineaTiempo`, `EmisorCombobox`, `AreaSelect`, `ResponsableSelect`, `EtiquetaQR`, `LectorQR`, `CargaEscaneo`. Un componente de dominio nunca define estilos propios: compone capas 2 y 3.
5. **Plantillas de página** (`components/layouts/`): `AppShell` (barra lateral de marca con la persona arriba, barra de búsqueda translúcida, selección en cápsula), `ListPage` (CommandBar + FilterBar + DataTable + Drawer de detalle), `FormPage`, `DashboardPage`.
6. **Páginas** (`resources/js/pages/`): solo ensamblan plantillas y compuestos y llaman a Inertia. Sin CSS propio, sin HEX, sin lógica de negocio.

**Reglas de reutilización**

- **Buscar antes de crear:** un componente nuevo solo se crea si no se puede obtener componiendo o añadiendo una variante a uno existente. Dos pantallas con la misma estructura usan la misma plantilla.
- **Cada CRUD administrativo** (áreas, tipos de trámite, plazos, feriados, reglas de derivación, usuarios, roles, catálogos) se arma con `ListPage` + `FormPage` + `DataTable` + `FormField`: una definición de columnas, un Form Request y un controlador. Añadir un catálogo nuevo no requiere componentes nuevos.
- **Alta en línea (7.3.5):** `Combobox` acepta `onCreate` y es el único selector con autocompletado del sistema (emisor, área, responsable, instrucción frecuente).
- **Autoguardado de borrador y teclado completo (7.3.5):** un hook `useAutosave` y los atajos y orden de tabulación viven en `FormPage`/`FormField`, no en cada pantalla.
- **Accesibilidad por construcción:** foco visible (anillo de 5.3), `aria-*` y navegación por teclado vienen de los primitivos; el semáforo nunca depende solo del color.
- **Estado del servidor en Inertia** (props, `useForm`, recargas parciales); sin store global salvo UI efímera. Los tipos de las props se generan a partir de los API Resources o se declaran en `resources/js/types/` en un solo lugar.
- **Catálogo de componentes:** página `/ui` (solo entorno local) que muestra cada primitivo y compuesto con todas sus variantes y estados; es la referencia para revisar el diseño contra 5.3.
- **Tests de componentes** (Vitest + Testing Library) para primitivos y compuestos; las páginas se cubren con tests de integración de Laravel (respuesta Inertia) y los flujos críticos con pruebas de navegador.
- **Un solo panel con control por rol:** cada rol ve solo los menús y acciones que le corresponden. Laravel comparte con Inertia la lista de permisos del usuario (`auth.can`) y la navegación se construye a partir de ella.
- **Los permisos mandan, no la interfaz:** todo controlador autoriza con **Policies** (integradas con `spatie/laravel-permission`); ocultar un botón en React no es seguridad. Toda consulta de expedientes y documentos pasa por un *scope* de modelo (`visiblesPara($user)`) que aplica el filtro por áreas; no se escribe a mano en cada controlador.
- **No saltarse la auditoría:** toda acción de la interfaz (editar, derivar, cambiar estado, descargar) pasa por los **Services** del dominio (`AuditoriaService`, `SemaforoService`, etc.) o por observers de modelo, para que ningún cambio quede sin registrar. Los controladores no escriben en la base directamente. Verificar con tests.
- **Superadmin sin acceso al contenido:** los controladores de expedientes y documentos le deniegan acceso por Policy; solo ve configuración y auditoría.
- **Búsqueda:** la búsqueda global (barra superior) llama a un endpoint que consulta Meilisearch vía Scout con el filtro de áreas del usuario obligatorio.
- El panel y Horizon son interfaces distintas: Horizon (monitor de colas) queda restringido al superadmin.

## 5.3 Diseño visual y paleta

**Estilo:** forma, tipografía y capas al estilo de Apple (Human Interface Guidelines, escritorio); colores de Google (Material 3, abajo). Decisión del usuario, en reemplazo del estilo Fluent / Microsoft 365 inicial. Claro y centrado en la tarea: la jerarquía se da con tamaño, peso y espacio, no con bordes.

**Estructura de pantalla**
- Barra lateral de marca a toda la altura, con el degradado del azul de Google (`--barra-fondo`, de `primary-600` a `primary-800`) y texto blanco: arriba el nombre del sistema y la tarjeta de la persona (avatar de iniciales, nombre y rol; abre el menú de la cuenta); debajo sus opciones. Ítem activo en cápsula blanca al 15 % y hover al 10 %; colapsable (solo iconos en ventanas de menos de 1024 px).
- Barra superior de 56 px translúcida (material con desenfoque) a la derecha de la barra lateral: botón para colapsarla y búsqueda global (campo relleno, sin borde).
- Las bandejas con varias vistas usan un control segmentado (`Pestanas`) sobre la tabla, con el total de cada vista.
- Barra de comandos sobre cada tabla: acciones principales a la izquierda, filtros y búsqueda a la derecha.
- Fondo de página gris claro; tarjetas blancas **sin borde**, con sombra en capas y esquinas de 14 px.
- Tablas como listas: cabecera fija translúcida sin franja de color, filas de 44 px con separadores finos, fila en hover y seleccionada.
- Crear, editar y ver en modales tipo hoja (esquinas de 18 px, fondo desenfocado, entrada suave); detalle en lista agrupada etiqueta | valor.
- Solo tema claro en la v1.

**Tipografía:** fuente del sistema (`-apple-system`, SF Pro en Apple, Segoe UI Variable en Windows, Roboto en Android); los títulos usan la variante Display con interletrado algo cerrado. Sin fuentes externas (CDN): el sistema es local. Base 14 px; escala 12 / 14 / 16 / 20 / 28 px; pesos 400, 500, 600 y 700 (nunca pesos finos). Título de página en negrita de 28 px.

**Forma y espaciado:** cuadrícula de 4 px; radio 8 px en controles, 14 px en tarjetas y 18 px en hojas; sombras en capas (`--sombra`, `--sombra-flotante`); campos de 36 px con borde gris de Google (3,7:1) y, al enfocar, halo azul de 3 px; anillo de foco de 2 px en `primary-600` con separación de 2 px en el resto. Botón secundario relleno de gris (no contorno); deshabilitado, atenuado al 40 %. Etiquetas en cápsula.

**Paleta única (HEX)**

Paleta **al estilo de Gmail (Material 3 de Google)**, por decisión del usuario (antes era índigo `#4A4FB5`). Color de marca: **azul `#0B57D0`**, el de los botones y enlaces de Gmail; contraste con texto blanco 6.4:1 (cumple AA). Solo se toman los colores: no se usan el nombre, el logo ni la tipografía de Google o Gmail. El proyecto sigue siendo una iniciativa personal y no oficial: no usar los colores, escudo ni logo de la UAC como marca del sistema hasta que se oficialice. Se distingue de los cuatro colores de semáforo, que no deben confundirse con la marca.

| Token | HEX | Uso |
|---|---|---|
| `primary-50` | `#ECF3FE` | Fondo de ítem activo, fila seleccionada |
| `primary-100` | `#D3E3FD` | Hover suave, etiquetas informativas (el celeste de selección de Gmail) |
| `primary-200` | `#A8C7FA` | Bordes de elementos activos |
| `primary-300` | `#7CACF8` | Series secundarias de gráficos |
| `primary-400` | `#4C8DF6` | Iconos sobre fondo claro |
| `primary-500` | `#1B6EF3` | Hover de enlaces |
| **`primary-600`** | **`#0B57D0`** | **Color de marca: botón primario, enlaces, foco** |
| `primary-700` | `#0842A0` | Botón primario en hover |
| `primary-800` | `#062E6F` | Botón primario presionado |
| `primary-900` | `#041E49` | Texto de énfasis sobre fondo claro |
| `primary-950` | `#021430` | Reservado |

| Token neutro | HEX | Uso |
|---|---|---|
| `bg-app` | `#F6F8FC` | Fondo de página (el de Gmail) |
| `surface` | `#FFFFFF` | Tarjetas, tablas, barra superior |
| `surface-subtle` | `#F2F6FC` | Cabecera de tabla, zonas secundarias |
| `border` | `#E1E3E1` | Bordes y separadores |
| `border-strong` | `#747775` | Bordes de campos de formulario (4.5:1 sobre blanco) |
| `text-primary` | `#1F1F1F` | Texto principal |
| `text-secondary` | `#444746` | Texto secundario, etiquetas |
| `text-disabled` | `#9AA0A6` | Elementos deshabilitados |

| Token semántico (solo estados) | Color | Fondo suave | Estado |
|---|---|---|---|
| `status-ok` | `#137333` | `#E6F4EA` | 🟢 En plazo |
| `status-warn` | `#F9AB00` | `#FEF7E0` | 🟡 Por vencer (texto sobre este color: `#1F1F1F`) |
| `status-danger` | `#B3261E` | `#FCE8E6` | 🔴 Vencido o sin responsable; errores |
| `status-neutral` | `#5F6368` | `#F1F3F4` | ⚪ Pendiente de clasificar o revisar |

**Reglas de uso**
- No se usa ningún color fuera de esta tabla. Cualquier ajuste se hace aquí primero.
- Los colores semánticos se reservan para semáforos, validaciones y alertas; nunca como decoración.
- El semáforo **nunca depende solo del color**: siempre lleva icono y texto (ej. "Vencido"), por accesibilidad.
- Gráficos de KPIs: escala `primary` y neutros; solo los semáforos usan colores semánticos.
- Si el sistema se oficializa y la institución exige su color, se reemplaza únicamente la escala `primary`, manteniendo contraste AA (≥ 4.5:1) del texto sobre `primary-600`.

**Implementación**
- Una sola fuente de verdad: `resources/css/tokens.css` con variables CSS (`--primary-600`, `--bg-app`, `--status-ok`, etc.).
- Tailwind 4 consume esas variables desde `@theme` en `resources/css/app.css` (no hay `tailwind.config.js`): la escala `primary` y los tokens neutros y semánticos se registran completos con los valores de esta sección (sin tonos autogenerados ni colores por defecto de Tailwind); prohibido escribir HEX sueltos o clases de color fuera de los tokens en componentes y páginas. Un lint/test falla si aparece un HEX fuera de `tokens.css`.
- Los componentes de 5.2 son la única vía para aplicar este diseño: tablas, barra de comandos, hojas, barra lateral, radios, sombras, materiales y anillo de foco se definen en los primitivos y plantillas, no en las páginas.
- Fuentes: pila de sistema sin CDN, sin Google Fonts ni proveedores externos.
- Iconos de trazo redondeado al estilo de SF Symbols con Lucide (`lucide-react`, trazo 1,75), siempre desde `components/ui/iconos.tsx`: un solo set en todo el sistema.

## 6. Modelo de datos (borrador inicial)

Convenciones: PK `id` bigint, `created_at/updated_at`, `deleted_at` (soft delete) en tablas de negocio, tablas, columnas y términos de dominio **en español**, como en este documento; clases y métodos siguen las convenciones del framework.

- `users`, roles/permisos (spatie).
- `areas`: nombre, descripción, palabras_clave (jsonb), `parent_id` nullable (jerarquía), orden, activa.
- `area_responsables`: area_id, user_id, tipo (`titular`/`suplente`), vigente_desde/hasta.
- `tipos_tramite`: nombre, descripción, plazo_dias (por defecto), tipo_dias (`habiles`/`calendario`), requiere_aprobacion_cierre, activo.
- `plazos_area`: tipo_tramite_id, area_id, plazo_dias (override por área), vigente_desde/hasta.
- `feriados`: fecha, descripción, alcance (global o por área).
- `reglas_derivacion`: nombre, condición (jsonb: tipo, palabras clave, remitente/dominio), area_destino_id, responsable_id nullable, prioridad, activa.
- `expedientes`: `id` (PK interna; todas las relaciones apuntan a ella), `numero_registro` (clave de negocio, ver 6.2), origen (`correo`/`fisico`/`pdf`), remitente_nombre, remitente_email, asunto, fecha_ingreso, tipo_tramite_id nullable, area_principal_id nullable, responsable_id nullable, plazo_dias_aplicado, fecha_limite nullable, estado, semaforo (calculado/cacheado), confianza_ia nullable, clasificacion_origen (`pendiente`/`ia_auto`/`ia_revisada`/`manual`), archivado_at, y los campos de 6.1 (`anio`/`secuencia` nullables hasta confirmar, `emisor_id`, `tipo_documento_id`, `numero_documento`, `numero_documento_original`, `fecha_documento`, `folios`, `requiere_respuesta`, `grupo_id`, `clasificacion_tramite`, `ubicacion_fisica_id`, `custodio_id`).
- `expediente_areas_copia`: expediente_id, area_id (áreas en copia).
- `documentos`: expediente_id, versión, nombre_original, ruta, mime, tamaño, **sha256**, texto_extraido (para búsqueda), es_adjunto, origen_correo_id nullable.
- `correos`: message_id único, in_reply_to, references, uid_externo (UID IMAP o id de mensaje de Gmail), thread_id nullable, from, to, cc, asunto, fecha, ruta_eml (original crudo), hash, expediente_id.
- `movimientos`: expediente_id, tipo (derivación, cambio de estado, respuesta, comentario), de_user/área, a_user/área, nota, fecha.
- `clasificaciones_ia`: expediente_id, modelo, versión_modelo, texto_entrada_hash, resultado (jsonb), confianza, modo (`sombra`/`activo`), decisión_final, corregida_por, fecha.
- `correcciones_pendientes`: clasificación_id, valor_ia, valor_humano, usuario, estado (`pendiente`/`validada`/`rechazada`), validada_por. Solo las validadas alimentan el reentrenamiento.
- `notificaciones_enviadas`: destinatario, canal, expediente_id/resumen, estado de entrega.
- Catálogos y numeración (6.1, 6.2, 7.3): `emisores`, `tipos_documento`, `instrucciones_frecuentes`, `reglas_no_tramite`, `ubicaciones_fisicas`, `grupos` (series), `secuencias`.
- Documentos salientes (7.3.4): `documentos_salientes` (borrador y documento final subidos) (tipo, área, `anio`, `secuencia`, estado de aprobación, aprobado_por, enviado_at, expediente_origen_id), `envios` (destinatario, estado de entrega, rebote).
- `auditoria`: ver sección 9.
- `configuraciones`: clave/valor (umbrales de confianza, modo sombra, etc.).

### 6.1 Campos del registro actual (Excel «Registro de documentos recibidos») → modelo

| Columna actual | Campo en el sistema |
|---|---|
| N° REG. (`N°00001`) | `expedientes.numero_registro`: clave de negocio (6.2), asignada por el sistema, correlativa por año, no editable |
| Fecha de ingreso / Hora | `fecha_ingreso` (automática, no editable; la hora la fija el servidor) |
| Dependencia que emite | `emisor_id` → catálogo `emisores` (internos y externos, con autocompletado y alta desde el panel) |
| Documento para respuesta SI/NO | `requiere_respuesta` (booleano) |
| N° documento | `tipo_documento` (catálogo: oficio, oficio circular, oficio múltiple, carta circular, informe, solicitud…) + `numero_documento` normalizado |
| Folios | `folios` (entero) |
| Asunto | `asunto` |
| Derivado a | `movimientos` estructurados: destinatarios (uno o varios), instrucción y **plazo como fecha**, no como texto libre |
| Documento de derivación | `movimientos.documento_derivacion` + **cargo de entrega escaneado** (adjunto) |
| Fecha de atención y/o derivación | Fecha del movimiento, automática |
| Atendido SI/NO | `estado` (flujo de estados) con respuesta vinculada; alimenta el semáforo |

Campos nuevos para papel: `ubicacion_fisica` (archivador/caja/estante), `custodio_id`, `original_fisico_conservado` (siempre verdadero), `constancia_recepcion` (PDF con QR), `grupo_id` (serie de documentos relacionados) y `clasificacion_tramite` (`tramite` / `no_tramite` / `por_revisar`).

**Tipo de documento vs tipo de trámite:** el *tipo de documento* es la forma (oficio, oficio circular, carta, informe); el *tipo de trámite* es la naturaleza y lleva el plazo (invitación, requerimiento de información, convocatoria a reunión, solicitud de recursos, comunicación informativa).

**Estados del expediente:** `por_revisar`, `registrado`, `derivado`, `en_atencion`, `atendido`, `cerrado`, `no_tramite`, `historico`, `anulado`. `atendido` no es `cerrado`: el primero lo marca el sistema al enviarse la respuesta vinculada; el segundo requiere aprobación según el tipo.

### 6.2 Identificadores y numeración

- **`id` (bigint): clave primaria interna.** Todas las relaciones (`documentos`, `movimientos`, `correos`, auditoría, índice de búsqueda) apuntan a `id`, nunca al número de registro.
- **`numero_registro`: clave de negocio** (identificador externo). Es el que ven los usuarios, se imprime y viaja en el asunto del correo y en el QR. Se compone de `anio` (smallint) + `secuencia` (int), con **restricción única (`anio`, `secuencia`)**.
- **Formato visible** configurable; por defecto como en el Excel actual: `N°00038`. **Código de enlace** (asunto de correo, QR, URL): `REG-2026-00038`, sin ambigüedad entre años.
- **Lo asigna solo el sistema:** nadie lo escribe ni lo edita.
- **Sin saltos ni duplicados:** tabla `secuencias` (`clave`, `anio`, `ultimo`); la asignación bloquea la fila (`SELECT … FOR UPDATE`) dentro de la misma transacción que confirma el expediente.
- **Se asigna al confirmarse como trámite, no al llegar el correo:** el spam, `no_tramite` e `historico` no consumen número; mientras no lo tienen, se identifican solo por `id`. Un histórico recibe número únicamente si se promueve a trámite activo.
- **Nunca se reutiliza:** un registro erróneo se **anula** (`estado = anulado`, con motivo, auditado) y conserva su número.
- **Reinicia cada año.** El **siguiente número de 2026 es configurable** en la puesta en marcha; por defecto continúa en `N°00038` para no chocar con el registro en papel.
- Los documentos salientes usan una secuencia independiente por tipo, área y año (7.3.4), con el mismo mecanismo.

## 7. Flujos clave

### 7.1 Ingesta de correo
- Job programado cada N minutos lee el buzón central de **Google Workspace** con una cuenta dedicada al sistema.
- Autenticación recomendada: **OAuth2** (cliente OAuth interno del dominio o cuenta de servicio con delegación de dominio, autorizado por el administrador de Workspace). Una contraseña de aplicación solo si el administrador lo permite; no depender de ella. Tokens guardados cifrados.
- Implementar tras la interfaz `MailboxDriver` (driver IMAP/XOAUTH2 y driver Gmail API) para poder cambiar sin tocar el resto. Con Gmail API se puede usar `threadId` como pista adicional de hilos.
- Marcar lo procesado con una **etiqueta de Gmail** (ej. `tramite/procesado`) en lugar de mover o borrar correos.
- Scopes mínimos: lectura, etiquetas y envío (p. ej. `gmail.modify` y `gmail.send`); sin acceso a otras cuentas ni a Drive.
- Guarda el `.eml` crudo, adjuntos y hash SHA-256 de cada uno.
- Deduplicación por `Message-ID` (y UID o id de Gmail como respaldo).
- **Enlace de hilos:** por `In-Reply-To`/`References` **y** por código `[REG-AAAA-NNNNN]` en el asunto. Las respuestas se anexan al expediente existente.
- **Remitente real:** si el correo es un reenvío, intentar extraer el remitente original del cuerpo; si no se puede, marcar para revisión del administrativo.
- No borrar ni mover correos del buzón sin configuración explícita.

### 7.2 Respuestas
- Las respuestas deben salir **desde el sistema** o **con copia (CCO) al buzón central**, con el código de registro en el asunto, para que el hilo se enlace y el semáforo se actualice solo.
- Respuestas: se redactan y envían desde el sistema solo con aprobación humana auditada (fase 5); los borradores con IA llegan en la fase 6.

### 7.3 Documentos físicos
- El administrativo escanea y confirma el formulario prellenado (7.3.5); al confirmar, el sistema asigna el número y genera la **etiqueta con QR** (código de registro) para pegar en el original.
- OCR en español → texto → mismo flujo que un PDF.
- Escanear el QR en el panel abre el expediente (seguimiento del papel).

### 7.3.1 Control del papel (el original nunca se destruye)
- El escaneo es una **copia de consulta, sin valor sustitutorio**: el original físico se conserva siempre. La interfaz lo indica ("Copia digital") y el sistema no ofrece marcar el original como descartable.
- Cada original tiene `ubicacion_fisica` y `custodio`; mover un original registra un movimiento auditado.
- Al recibir se emite una **constancia de recepción** imprimible (número de registro, fecha, hora, folios, QR) para entregar a quien presenta el documento.
- Al derivar al área se imprime el **cargo de entrega**; el cargo firmado se escanea y se adjunta al movimiento.
- Documentos relacionados (ej. varios oficios circulares del mismo emisor y asunto el mismo día) se agrupan en una **serie** (`grupo_id`) para atenderlos como una unidad sin perder cada registro individual.

### 7.3.2 Correo que no es trámite
- Estado `no_tramite` para spam, boletines, notificaciones automáticas y phishing. **Nunca se borra**: queda archivado, auditado y recuperable.
- Reglas editables desde el panel (sección 5.1): remitentes y dominios conocidos, `noreply`, cabecera `List-Unsubscribe`, palabras clave. La IA solo sugiere.
- Lo dudoso entra como `por_revisar` en la bandeja del administrativo; ningún `no_tramite` genera semáforo ni notificación.
- Los correos con enlaces o adjuntos ejecutables sospechosos se marcan y **no se abren automáticamente** (escaneo con ClamAV, sección 11).

### 7.3.3 Histórico mínimo (año en curso)
- Al conectar el buzón se importa solo desde `BACKFILL_DESDE=2026-01-01` (configurable), por lotes en cola, con ritmo limitado y de forma idempotente.
- Lo importado entra con estado **`historico`**: sin semáforo, sin notificaciones y sin reglas de plazo, para no llenar el panel de rojos ni enviar avisos por trámites antiguos.
- Comando `correo:estadisticas --desde=2026-01-01` que **solo cuenta** (correos por día y por remitente) sin guardar nada. Sirve para dimensionar el servidor antes de importar.

### 7.3.4 Documentos salientes y envío automático
- Los documentos emitidos (oficios, cartas, informes, respuestas) **se redactan en Word fuera del sistema** (decisión del usuario) y el sistema los **revisa, numera y envía**.
- **Numeración correlativa automática** por tipo, área y año, con formato configurable (ej. `OFICIO N.º 012-2026-<ÁREA>`), sin saltos ni duplicados (secuencia con bloqueo transaccional).
- Se sube el **borrador** (Word o PDF) para la revisión; aprobado, recibe su número, quien lo redactó lo pone en el Word y sube el **documento final**, que es el que se envía como adjunto. El sistema no tiene plantillas ni genera el PDF.
- Flujo: borrador → revisión → aprobación (número) → documento final → envío. **Ningún documento sale sin aprobación explícita y auditada.** El envío automático significa que, subido el documento final, el sistema lo despacha sin intervención manual.
- Envío por Gmail API (cuenta del buzón central), con el **código de registro en el asunto** y **copia oculta al buzón central**, para enlazar la respuesta y cerrar el ciclo.
- Circulares a varios destinatarios: un correo individual por destinatario (no una lista visible), en cola con ritmo controlado para respetar los límites de envío de Google.
- Registro de entrega: envío, rebote y acuse cuando exista; un documento emitido que exige respuesta del destinatario genera su propio plazo y semáforo.
- La firma se mantiene fuera del sistema (impresa y firmada, o firma digital externa): el documento final puede ser el Word o el PDF firmado. La firma digital integrada queda fuera de alcance en la v1.

### 7.3.5 Captura mínima: el usuario confirma, no digita

Regla: **un dato se escribe una sola vez en todo su ciclo.** Lo que ya existe en el sistema (correo de origen, catálogos, expediente, historial del emisor) se hereda y no se vuelve a pedir.

1. **Campos automáticos, no editables:** número de registro, fecha y hora, usuario que recibe, hash y estado inicial. Folios = número de páginas del PDF escaneado; solo se corrigen con motivo.
2. **Correo:** remitente, asunto, fecha, adjuntos, emisor, tipo y N° de documento llegan prellenados desde el correo y su adjunto. El emisor se reconoce por dominio y remitente.
3. **Papel:** escaneo → OCR → extracción por reglas (N° de documento, tipo, emisor, fecha, plazo mencionado) → formulario prellenado, con marca visual en los campos de baja confianza. El usuario revisa y confirma.
4. **Catálogos en lugar de texto libre:** emisor, tipo de documento, área y responsable se eligen con autocompletado; si no existen, se crean en línea sin salir del formulario. Se detectan emisores duplicados y se propone fusionarlos.
5. **Valores sugeridos por historial:** según emisor y tipo, el sistema propone área, destinatarios, `requiere_respuesta` y plazo (por frecuencia histórica y reglas; la IA se suma en la fase 4). Se aceptan con un clic.
6. **Derivación sin teclear:** destinatarios del catálogo, instrucciones frecuentes en lista («para conocimiento», «atender», «presentar información») y plazo calculado según 5.1 o extraído del documento. Una serie se deriva **una sola vez para todo el lote**.
7. **«Atendido» se marca solo:** al emitir y enviar un documento vinculado como respuesta, o al cerrar con aprobación. Nadie lo marca a mano.
8. **Normalización del N° de documento:** mayúsculas, sin tildes, `No` / `N.º` / `N°` unificados a `N°`, espacios y puntuación uniformes. Se guarda también el texto original.
9. **Formulario mínimo:** solo quedan editables los campos que no se pudieron inferir; el resto aparece resuelto. Autoguardado de borrador y manejo completo por teclado.

**Anti-duplicados** (antes de guardar, dentro de la misma transacción):
- **Clave de unicidad de negocio:** (`emisor_id`, `tipo_documento_id`, `numero_documento_normalizado`, año del documento), mediante índice único parcial que ignora los expedientes `anulado`. Si existe, se **bloquea el alta** y se muestra «Ya registrado como N°00012» con enlace. El usuario puede anexar el archivo como copia o versión del mismo expediente.
- **Hash SHA-256 idéntico** a un archivo ya almacenado: aviso de duplicado exacto.
- **Mismo emisor, asunto y fecha:** aviso de posible duplicado (no bloquea).
- **Mismo correo** (`Message-ID`): nunca duplica (7.1).
- **Reenvío de un trámite existente:** se enlaza al expediente, no se crea otro.

Indicadores de éxito (sección 8): tiempo de registro por documento y porcentaje de campos prellenados aceptados sin cambio. Se miden en el piloto, sin cifra comprometida de antemano.

### 7.4 Búsqueda
- Scout + Meilisearch indexa: asunto, remitente, texto extraído, código, área, estado, fechas.
- Búsqueda tolerante a errores de tipeo, con filtros por área, estado, semáforo, fecha, tipo.
- Respetar permisos: un coordinador solo obtiene resultados de sus áreas (filtro obligatorio en cada consulta).

## 8. Semáforos y KPIs (reglas, sin IA)

Semáforos (cálculo programado + recálculo en cada movimiento):

- 🟢 En plazo y con responsable.
- 🟡 Queda menos del 30% del plazo, o sin movimiento por más de X días (configurable).
- 🔴 Plazo vencido o sin responsable asignado.
- ⚪ Pendiente de clasificar o en revisión manual.

Los plazos salen de la configuración programable (sección 5.1): `plazos_area` si existe override, si no `tipos_tramite.plazo_dias`, calculados en días hábiles o calendario según corresponda y respetando la tabla `feriados`. Los porcentajes y días del semáforo también son configurables.

Reglas adicionales:
- `historico`, `no_tramite` y `anulado` no tienen semáforo.
- Con `requiere_respuesta = falso` y sin plazo explícito (comunicaciones «para conocimiento») no hay vencimiento ni 🔴: el trámite pasa a `atendido` al registrarse la toma de conocimiento del destinatario.
- Si el documento fija una fecha (reunión, evento, entrega de información), esa fecha es el plazo.

KPIs del panel: expedientes por estado y semáforo; % atendidos dentro del plazo; tiempo promedio de atención por tipo/área/coordinador; expedientes sin movimiento > X días; carga por responsable; precisión de la IA (aciertos vs correcciones); tiempo de registro por documento y % de campos prellenados aceptados sin cambio.

Resumen diario por correo a cada coordinador (pendientes de sus áreas + semáforo + enlace al expediente), porque su entorno de trabajo es el correo.

## 9. Auditoría (detallada e inmutable)

Tabla `auditoria` **append-only**:

- Campos: id, fecha_hora (hora del servidor), usuario_id (o `sistema`/`ia`), acción, entidad, entidad_id, valor_anterior (jsonb), valor_nuevo (jsonb), ip, user_agent, **hash_anterior, hash_registro** (cadena SHA-256: cada registro incluye el hash del anterior).
- Inmutabilidad real en PostgreSQL: el rol de la aplicación solo tiene `INSERT` y `SELECT` sobre esta tabla, más un **trigger que bloquea UPDATE/DELETE/TRUNCATE**.
- Comando `php artisan auditoria:verificar` que recorre la cadena y reporta si alguna fila fue alterada. Programarlo periódicamente.

Qué se audita: ingreso de correos y documentos (con hash); cambios de tipo, responsable, plazo, estado, derivaciones; **accesos de lectura/descarga/impresión** de documentos; notificaciones enviadas y su entrega; inicios de sesión, fallos, cambios de roles/permisos; cambios de configuración (umbrales, modo sombra, plazos); cada clasificación de IA (modelo, versión, entrada, resultado, confianza, si fue automática o corregida y por quién); **asignación y anulación de números de registro; movimientos físicos de originales e impresión de constancias y cargos; creación, fusión y desactivación de emisores y áreas; promoción de un histórico a trámite activo; aprobación, emisión y envío de documentos salientes**.

Nota: `spatie/laravel-activitylog` puede usarse como apoyo, pero **no** garantiza inmutabilidad; la garantía viene de permisos de BD + trigger + cadena de hashes.

## 10. Servicio de IA (FastAPI)

Endpoints internos (sin autenticación pública; red interna Docker + token compartido en cabecera):

- `POST /ocr` → archivo → `{texto, paginas, confianza_ocr}` (ocrmypdf/Tesseract, idioma `spa`).
- `POST /classify` → `{texto}` → `{es_tramite, tipo, area, confianza_tramite, confianza_tipo, confianza_area, top_k}`.
- `POST /extract` → `{texto}` → `{tipo_documento, numero_documento, fecha, plazo_mencionado, emisor, asunto}` (regex primero; los folios salen de `paginas` en `/ocr`).
- `GET /health` y `GET /model-info` (versión del modelo y fecha de entrenamiento).
- (Fase 6) `POST /draft` → borrador de respuesta con LLM local.

Estrategia de clasificación:

1. **Arranque sin datos:** similitud de embeddings entre el documento y la descripción/palabras clave de cada área y tipo.
2. **Con datos:** con ~30-50 ejemplos por categoría, entrenar regresión logística sobre embeddings.
3. Reentrenamiento **solo con correcciones validadas**, versionado de modelos (`models/vAAAAMMDD/`), con posibilidad de volver a la versión anterior.
4. Modelo **único compartido** (las categorías son institucionales).
5. **Catálogo dinámico:** áreas y tipos se leen de la base de datos, no están fijos en el modelo. Una nueva área funciona de inmediato por similitud con su descripción y palabras clave; si se crea, fusiona o desactiva un área, el servicio refresca su catálogo (`POST /catalog/reload` o catálogo enviado en cada petición) y se marca que el clasificador entrenado necesita reentrenarse. Nunca devolver un área inactiva.

Comportamiento en Laravel (todo configurable en `configuraciones`):

- **Modo sombra** (inicial, 1-2 semanas): la IA propone, no ejecuta; se registra y se compara con la decisión humana.
- Umbrales iniciales sugeridos: ≥ 0.90 acción automática de bajo riesgo; 0.60-0.90 sugerencia al administrativo; < 0.60 bandeja de revisión manual. **Ajustar con los datos del modo sombra.**
- Acciones automáticas permitidas: etiquetar, asignar área, registrar. **Prohibido automatizar:** responder correos, cerrar trámites, derivaciones con efecto legal.
- Si un trámite puede tocar varias áreas: responsable principal + copia a otras.

## 11. Seguridad y privacidad

- HTTPS, cookies seguras, CSRF, rate limiting en login, política de contraseñas, 2FA para roles críticos.
- Credenciales y tokens OAuth de Google en variables de entorno/secrets o cifrados en BD con `APP_KEY`; scopes mínimos (7.1); nunca en el repositorio.
- Archivos originales fuera del directorio público; descarga solo vía controlador con verificación de permiso y registro en auditoría.
- Validar tipos MIME y tamaño de adjuntos; **escanear con ClamAV** (contenedor opcional recomendado) antes de procesar.
- Meilisearch y Redis sin exposición externa; claves fuertes.
- Backups cifrados diarios (PostgreSQL + originales) y **prueba de restauración periódica documentada**.
- Política de retención definida con la institución; cumplimiento Ley 29733 (consentimiento/finalidad, derechos ARCO, registro del banco de datos si aplica).
- Mensajes de error sin filtrar datos sensibles; logs sin contenido de documentos.

## 12. Estructura de proyecto sugerida

```
tramite/
├─ docker-compose.yml
├─ .env.example
├─ docker/            (Dockerfiles: php, nginx, ai)
├─ app/               (Laravel: Models, Policies, Jobs, Services, Http/Controllers, Http/Requests, Http/Resources)
├─ resources/
│  ├─ css/tokens.css  (única fuente de la paleta, 5.3)
│  └─ js/             (React: components/{ui,forms,data,domain,layouts}, pages, hooks, lib, types)
├─ ai-service/        (FastAPI: main.py, models/, training/, tests/)
├─ docs/              (PLAN.md, decisiones, runbooks de backup/restauración)
└─ tests/
```

Convenciones: Services para lógica de negocio (`IngestaCorreoService`, `SemaforoService`, `ClasificacionService`, `AuditoriaService`); Jobs idempotentes con reintentos y *backoff*; Policies para todo acceso a expedientes; Form Requests para validación; React solo para UI, sin lógica de negocio dentro de componentes ni de controladores (delegar a Services); la validación se hace en Laravel y los errores llegan al formulario por Inertia, nunca solo en el cliente.

## 12.1 Documentación del código

Regla: **precisa y mínima**. Se documenta lo que el código no puede decir por sí mismo.

- Comentarios en español, una línea, en tiempo presente. Dicen **qué garantiza** o **por qué** existe algo no evidente; nunca repiten lo que hace la línea.
- Nombres claros y tipos nativos (PHP: tipos de parámetros y retorno; Python: type hints) en lugar de comentarios explicativos.
- PHPDoc solo en métodos públicos de Services y Jobs: una línea de resumen; `@throws` si lanza excepciones de dominio; `@param`/`@return` solo cuando el tipo nativo no basta (ej. forma de un array).
- Python: docstring de una línea en funciones públicas y endpoints; contrato de entrada y salida en los modelos Pydantic, no en prosa.
- TypeScript/React: el contrato de un componente va en el tipo de sus props, no en prosa; comentario de una línea solo en componentes de `components/` cuando su uso no sea evidente (p. ej. por qué `Combobox` recibe `onCreate`).
- Prohibido: código comentado, comentarios de narración ("aquí hacemos..."), historial de cambios en comentarios (para eso está git), `TODO` sin referencia a tarea.
- Reglas de negocio (plazos, semáforos, umbrales): una línea con la regla y su fuente de configuración.
- Docker, `.env.example` y configuración: comentar solo valores no evidentes.
- `docs/`: README con comandos de arranque, y un runbook por procedimiento (backup, restauración, reentrenamiento), en pasos numerados y sin explicaciones adicionales.

Ejemplo correcto:

```php
/** Calcula el semáforo según plazo restante y días sin movimiento. */
public function calcular(Expediente $e): Semaforo
```

```php
// Idempotente: el Message-ID único evita duplicar el expediente.
```

Ejemplos incorrectos: `// suma uno al contador`, `// esta función recibe un expediente y, usando varias reglas, determina...`.

## 13. Fases, entregables y criterios de aceptación

### Fase 0: Base del proyecto
Entregables: repositorio, Docker Compose con todos los servicios levantando, Laravel + Inertia + React + TypeScript + Vite + Tailwind, PostgreSQL, Redis, Horizon, Meilisearch, `.env.example`, CI mínimo (tests + lint + `tsc` + Vitest), `tokens.css` con la paleta de la sección 5.3 enlazada a Tailwind, **sistema de componentes base de 5.2 (primitivos, `DataTable`, `FormField`, `AppShell`, `ListPage`, `SemaforoBadge`) con la página de catálogo `/ui`**, y un CRUD de ejemplo (áreas) armado solo con esas piezas para validar la reutilización.
Aceptación: `docker compose up` deja todo funcionando; `/horizon` accesible solo a superadmin; test de humo pasa; el tema usa únicamente colores de la sección 5.3 y un lint/test falla si hay HEX o colores fuera de `tokens.css`; la página `/ui` muestra todos los componentes base con sus variantes y estados y coincide con 5.3 (barra de 48 px, ítem activo de 3 px, tablas densas, foco de 2 px, semáforo con icono y texto); el CRUD de áreas se construyó solo componiendo piezas de 5.2, sin CSS propio en la página.

### Fase 1: Ingesta de correos + expedientes + búsqueda
Entregables: lectura del buzón de Google con OAuth2 tras `MailboxDriver`, `.eml` y adjuntos con hash, expedientes automáticos, **filtro de correo no trámite (7.3.2)**, **importación histórica desde `BACKFILL_DESDE` con estado `historico` (7.3.3)**, comando `correo:estadisticas`, **numeración transaccional del registro (6.2)**, enlace de hilos, extracción de texto de PDFs, indexación en Meilisearch, pantalla de búsqueda y detalle de expediente, auditoría base (tabla inmutable + cadena de hashes).
Aceptación: **solo se conecta el buzón real con autorización por escrito (pendiente 1)**; lo histórico no genera semáforo ni notificaciones; dos altas concurrentes reciben números consecutivos, sin saltos ni repetidos (test); un correo `no_tramite` o `historico` no consume número; reprocesar el mismo correo no duplica; una respuesta con código en el asunto se anexa al expediente correcto; buscar un correo antiguo por remitente/asunto/texto funciona en < 1 s; `auditoria:verificar` detecta una alteración manual de prueba.

### Fase 2: Panel, roles, áreas, plazos y semáforos (sin IA)
Entregables: usuarios y roles, **inicio de sesión con Google restringido al dominio**, **módulo de configuración programable completo, construido con las plantillas y componentes React de 5.2 (`ListPage`, `FormPage`, `DataTable`, `FormField`), sin componentes nuevos por cada catálogo (sección 5.1 y 5.2): CRUD de áreas con jerarquía, responsables titular/suplente, tipos de trámite, plazos por tipo y por área, feriados, reglas de derivación y umbrales**, asignación/derivación manual, movimientos, semáforos, KPIs, bandeja de revisión del administrativo, resumen diario por correo con enlaces directos al expediente, catálogos de emisores, tipos de documento, instrucciones frecuentes y reglas de correo no trámite, bandeja `por_revisar`, aprobación configurable por tipo.
Aceptación: crear un área o cambiar un plazo desde el panel surte efecto sin desplegar y sin alterar expedientes ya ingresados (test); cada rol ve solo lo que debe (tests de Policies por rol y por área); el semáforo cambia correctamente al vencer plazos (tests con fechas simuladas); `historico`, `no_tramite` y `anulado` no muestran semáforo y un trámite sin respuesta requerida ni plazo no pasa a rojo (tests); un usuario del dominio no registrado no puede ingresar (test); el superadmin no accede al contenido de trámites.

### Fase 3: Documentos físicos (QR + OCR)
Entregables: registro de documento físico con los campos de 6.1 (número correlativo anual, emisor, tipo, folios, requiere respuesta), escaneo/subida, **constancia de recepción y cargo de entrega imprimibles**, **ubicación y custodio del original**, **series de documentos relacionados**, etiqueta QR imprimible, OCR vía servicio de IA, **extracción por reglas de los campos del documento, formulario prellenado y detección de duplicados (7.3.5)**, búsqueda por texto OCR, lectura de QR para abrir expediente.
Aceptación: un documento escaneado queda buscable por su contenido y rastreable por QR; el original conserva su hash; el sistema nunca marca un original físico como descartable; la constancia de recepción se imprime con QR y datos correctos; registrar de nuevo un documento ya ingresado se bloquea y muestra el N° existente (test); el formulario de un PDF de prueba queda prellenado con los campos extraídos.

### Fase 4: IA de clasificación (modo sombra → activo)
Entregables: servicio FastAPI (`/classify`, `/extract`), clasificación por similitud de embeddings, registro en `clasificaciones_ia`, modo sombra, panel de precisión, flujo de correcciones validadas, umbrales configurables, script de reentrenamiento versionado.
Aceptación: en modo sombra no se ejecuta ninguna acción automática; se puede medir % de acierto por categoría; revertir a una versión anterior del modelo es un comando; si `ai` está caído, el sistema sigue ingresando correos.

### Fase 5: Documentos salientes y envío automático
Entregables: borrador y documento final subidos (Word o PDF), numeración correlativa automática por tipo/área/año, flujo borrador → revisión → aprobación → emisión → envío por Gmail API con código de registro y CCO al buzón central, envío individual a múltiples destinatarios en cola, registro de entrega y rebotes, plazo y semáforo para documentos emitidos que exigen respuesta (7.3.4).
Aceptación: ningún documento sale sin aprobación auditada; la numeración no tiene saltos ni duplicados bajo envíos concurrentes (test); la respuesta del destinatario se anexa al expediente correcto; un rebote queda registrado y visible.

### Fase 6: Recomendaciones y borradores con IA (opcional)
Entregables: recomendaciones de derivación basadas en reglas + IA, borradores de respuesta con LLM local (Ollama), siempre con aprobación humana.
Aceptación: ninguna respuesta sale sin aprobación explícita auditada.
Hecho (2026-10-11): las recomendaciones de derivación son las reglas y la clasificación de la fase 4; el borrador es un texto sugerido por un modelo local (Ollama, opcional con `--profile llm`) que se copia al Word (docs/runbook-ia-borradores.md).

## 14. Pruebas

- Pest/PHPUnit: Policies por rol, semáforos, deduplicación de correos, cadena de auditoría, jobs idempotentes.
- Pytest en `ai-service`: contratos de endpoints, clasificación con un conjunto pequeño de referencia.
- Vitest + Testing Library para primitivos y compuestos de 5.2 (estados, teclado, accesibilidad básica, `SemaforoBadge` siempre con icono y texto); `tsc` sin errores en CI.
- Correos de prueba (`.eml` de ejemplo, incluidos reenvíos, hilos y adjuntos escaneados) en `tests/fixtures`.
- Numeración: concurrencia sin saltos ni repetidos, sin consumo por `no_tramite`/`historico`, anulación sin reutilización.
- Anti-duplicados: clave de unicidad de negocio, hash idéntico y mismo `Message-ID`.
- Semáforo: exclusiones de la sección 8.
- Medir antes de optimizar; no añadir complejidad sin una necesidad demostrada.

## 14.1 Hallazgos del registro 2026 (Excel analizado)

El archivo trae 218 filas numeradas, **solo 37 con datos** (N°00001 a N°00037, del 12/ene al 24/ago/2026). Representa únicamente el papel; el volumen de correo es desconocido.

- **Volumen de papel:** 32 de los 37 registros son del 3 al 24 de agosto (16 días hábiles), **≈ 2 por día hábil**, con un pico de 9 en un día (17/ago). Enero a junio solo tienen 5: parece falta de registro, no baja demanda, así que no usar esos meses para dimensionar.
- **Picos por lotes:** en el día pico, 8 de los 9 documentos son oficios circulares del mismo emisor y tema (encuesta de sostenibilidad). Justifica la **serie** (7.3.1).
- **Origen:** 27 de 37 (73%) vienen de dependencias internas; 10 son externos (municipalidad, salud, colegios). Hay 23 emisores distintos.
- **Tipo:** 34 oficios (simples, circulares y múltiples), 2 informes y 1 carta circular.
- **Respuesta requerida:** 26 de 37 (70%). De esos, **18 no tienen marcado «atendido»**; solo 11 de 37 figuran atendidos. En los 22 registros con fecha de atención o derivación, la mediana es de 2 días y el máximo de 42 días desde el ingreso. El registro llega al 24/ago, así que parte puede estar atendida sin marcar; aun así, es el problema que el semáforo debe hacer visible.
- **Derivación en texto libre:** instrucciones y plazos escritos a mano («presentar la información a más tardar el 28 de agosto»). Hay que estructurarlos: destinatarios, instrucción y fecha límite.
- **Calidad de datos:** formatos de N° de documento inconsistentes («Múltiple/Multiple», «N°/No/Cir.»), la hora no es fiable (registros a las 06:00 y 22:00, 7 sin hora), la columna de fecha de atención contiene «NO», y hay filas fuera de orden cronológico. Por eso fecha y hora automáticas, catálogos y normalización.
- **Carga de OCR:** 728 folios en total (promedio ≈ 20, máximo 80). Es trivial para un OCR local en CPU.
- **Categorías de asunto observadas** (punto de partida para el catálogo editable y los ejemplos de la IA): invitación a evento, convocatoria a reunión, requerimiento de información con plazo, solicitud de recursos o docentes, comunicación informativa, informe.

## 15. Decisiones tomadas y pendientes

**Ya decidido:**
- **React + Inertia con sistema de componentes propio, sin Livewire ni Filament** (5.2); áreas, tipos y plazos programables desde el panel (5.1); correo sobre Google Workspace.
- **Iniciativa personal del dueño y mantenedor del sistema**, no un proyecto oficial. Identidad visual propia (5.3), sin colores ni logo de la UAC.
- Los coordinadores usarán el sistema con su correo institucional: **inicio de sesión con Google** restringido al dominio (Laravel Socialite), más resumen diario por correo con enlaces directos.
- Piloto de **30 días con una sola área** antes de extenderlo.
- Histórico mínimo: solo del año en curso (7.3.3).
- El escaneo no reemplaza al original (7.3.1).
- Los documentos salientes se numeran y envían desde el sistema, siempre con aprobación (7.3.4).
- El catálogo se carga manualmente desde el panel (sin CSV).
- El número de registro es una clave de negocio asignada por el sistema; la clave interna es `id` (6.2).
- Captura mínima y anti-duplicados (7.3.5).

**Pendientes (resolver con el usuario en la fase indicada):**

1. **Autorización del acceso al buzón (precondición de la fase 1):** aunque el proyecto sea personal, leer el buzón institucional exige que el administrador de Google Workspace autorice el acceso OAuth (lectura, etiquetas y envío) y que la dirección acepte el tratamiento de datos (Ley 29733). La Fase 0 y el desarrollo avanzan con un buzón de prueba propio; **no conectar el buzón real sin esa autorización por escrito.**
2. **Volumen real de correo:** el Excel solo cubre papel. Ejecutar `correo:estadisticas` al tener acceso (fase 1).
3. **Hardware del servidor** (RAM, GPU) y sistema (¿Ubuntu físico o VM?) (fase 0 y fase 6).
4. **Catálogo inicial** de áreas, tipos y plazos: se carga manualmente desde el panel antes del piloto (fase 2).
5. **Sensibilidad de los documentos** (datos de alumnos, disciplinarios, laborales) para ajustar cifrado y accesos (fase 2).
6. **Política de retención** y respaldos (fase 2 en adelante).
7. Cómo se hará cumplir que las **respuestas salgan desde el sistema o con CCO al buzón central** (fase 2 y 5).
8. **Quién administra la configuración** además del superadmin (permiso delegable).
9. **Número inicial del correlativo 2026** en la puesta en marcha (por defecto `N°00038`, continuando el registro en papel) y formato de numeración de documentos salientes (fase 1 y 5). **Decidido (2026-10-08):** el administrador fija desde el panel (Configuración → Numeración) con qué número continúa el registro y cada correlativo de documentos emitidos; el formato de estos es el de su tipo de documento.
10. **Migración única del Excel 2026** (37 filas): importarlas, o conservar el archivo como referencia adjunta. Se decide aparte; no hay importación por CSV del catálogo. **Decidido (2026-10-08): no se importa.** El sistema empieza vacío y los trámites que siguen en curso se registran a medida que avanzan, con su N° del registro en papel y su fecha de ingreso real.
11. **Titularidad y condiciones de uso** del código frente a la institución (conviene dejarlo por escrito antes de oficializar).
12. **Continuidad:** UPS y respaldo fuera del servidor; documentación en `docs/` suficiente para que otra persona pueda operar el sistema.

## 16. Instrucciones de trabajo para Claude Code

1. Empieza por la **Fase 0**. Antes de codificar, propone el plan de archivos de esa fase y espera confirmación.
2. Al terminar cada fase, ejecuta las pruebas, resume lo hecho contra los criterios de aceptación y **espera aprobación** antes de seguir.
3. No inventes decisiones de la sección 15: si te bloquean, pregunta o deja una configuración con valores por defecto claros y documentados.
4. Mantén `docs/` actualizado (decisiones, comandos, runbook de backup/restauración).
5. Nunca incluyas credenciales ni datos reales en el repositorio; usa fixtures anonimizados.
6. Prioriza simplicidad y mantenibilidad: pocas piezas, bien probadas.
7. Antes de extender a toda la institución, ejecuta un **piloto de 30 días con una sola área**; mide adopción (porcentaje de trámites atendidos desde el sistema), tiempo de atención y correcciones de la IA.
8. Aplica la paleta y el estilo de la sección 5.3 y las convenciones de documentación de la sección 12.1 en todo el código.
