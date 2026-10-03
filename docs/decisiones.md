# Decisiones

Registro breve de decisiones tomadas al implementar el plan. La más reciente arriba.

## 2026-10-08 — Numeración desde el panel (pendiente 9)

- **Decisión del usuario: el administrador fija con qué número continúa cada correlativo.** Pantalla Configuración → Numeración (permiso `configuracion.gestionar`): el registro de documentos recibidos y un correlativo por tipo y área de documentos emitidos, por año (el actual y el siguiente). Muestra el último usado, el siguiente y cómo saldrá (`N°00120`, `OFICIO N.º 045-2026-DGA`).
- **Nunca hacia atrás**: el siguiente debe ser mayor que el número más alto ya puesto en la serie, incluidos los trámites en curso que conservaron el suyo. Se comprueba con la fila de `secuencias` bloqueada, así que nadie numera entre la comprobación y el ajuste. Saltar hacia adelante sí se permite y deja el hueco (lo decide el administrador, y queda en la auditoría como `numeracion.ajustada` con antes y después).
- **`secuencias.inicio`**: el primer número que emitió o emitirá el sistema en el año. Mientras no haya emitido ninguno, el ajuste lo mueve; así fija también hasta dónde llega el registro en papel para los trámites en curso (del 1 al inicio − 1). Después de emitir, un salto ya no lo cambia. `REGISTRO_INICIO_NUMERO` y `secuencias_inicio` quedan como valor por defecto si nadie ajusta.
- Lo que se mostraba antes («el valor inicial sigue en `config/tramite.php`; no hizo falta editarlo desde el panel», fase 5) queda reemplazado.

## 2026-10-08 — Sin importación: trámites en curso del registro en papel (pendiente 10)

- **Decisión del usuario: no se importa el Excel 2026.** El sistema empieza vacío; los trámites que siguen en curso se registran cuando se mueven.
- **Conservan su número**: al registrar papel se marca «Ya estaba en el registro en papel» con su N° del cuaderno y su fecha real de ingreso. Toman ese número (`anio` + `secuencia`) sin pasar por el correlativo, porque los números anteriores al primero del sistema (`REGISTRO_INICIO_NUMERO`, 38 en 2026) son justamente los del papel y no chocan. El índice único `(anio, secuencia)` impide repetir uno y la validación solo admite del 1 al 37.
- **La fecha de ingreso real** (desde el 1 de enero hasta hoy) es la que usa el plazo al derivar, así que un trámite atrasado aparece en rojo, como corresponde. La hora se desconoce y queda a las 00:00.
- Si el año no viene de un registro en papel (`secuencias_inicio` sin valor), la opción no aparece.

## 2026-10-08 — Delegar la configuración (pendiente 8)

- **Un interruptor en la ficha del usuario, no una pantalla de roles y permisos**: «Administra la configuración» da el permiso `configuracion.gestionar` como permiso directo (Spatie), aparte del rol. El rol sigue diciendo qué trámites ve; el permiso, si administra el catálogo (áreas, responsables, plazos, feriados, catálogos, plantillas y notificaciones). Con cinco roles fijos (5), una matriz editable sería más superficie que necesidad.
- **Gestionar usuarios sigue siendo solo del superadmin** (`usuarios.gestionar`): quien asigna permisos podría darse cualquiera.
- Dar o quitar el permiso queda en la auditoría (`usuario.permiso_cambiado`). Al superadmin el interruptor se le muestra encendido y bloqueado: lo tiene por su rol.

## 2026-10-08 — Estado de entrega del resumen diario

- **`notificaciones_enviadas`** (modelo de datos, sección 6): cada resumen diario se registra al encolarse con un Message-ID propio (`resumen-<uuid>@<dominio>`, vía `Headers` del Mailable). Queda **enviado** al dispararse `MessageSent` (el correo salió del transporte), **fallido** si la cola agota los intentos (`failed()` del Mailable) y **rebotado** si un DSN cita ese Message-ID (`EntregaService`, el mismo mecanismo de los documentos emitidos, ahora con `idsReferidos()` compartido).
- **Pantalla «Notificaciones»** (Sistema, permiso `configuracion.gestionar`): fecha, destinatario, tipo, cuántos expedientes, estado y motivo. Sin el contenido de los trámites, así que el superadmin puede verla (5).
- **Límite conocido**: si Gmail reescribiera el Message-ID al enviar por la API, un rebote no se enlazaría y la notificación quedaría «enviado». Los envíos de documentos leen el Message-ID definitivo; para el resumen no se hizo, porque Gmail conserva el que trae el mensaje. Se agrega si aparece el caso.

## 2026-10-07 — Respaldos cifrados e incrementales

- **Cifrado con libsodium** (`secretstream xchacha20poly1305`, viene con PHP): autenticado y por trozos de 1 MB, así que no carga archivos grandes en memoria, y un archivo alterado, recortado o con otra clave falla al descifrar. Se eligió frente a `openssl enc`, que no autentica. La clave es `RESPALDO_CLAVE` (32 bytes en base64), aparte de `APP_KEY`: si un día se rota `APP_KEY`, los respaldos siguen legibles. Sin clave no se cifra (desarrollo, CI).
- **Originales incrementales**: un almacén común `respaldos/archivos/` con un archivo por SHA-256, y en cada carpeta un manifiesto `originales.txt` (formato `sha256sum`, ruta incluida). Los originales ya se guardaban por hash y no cambian, así que copiar una vez por contenido es exacto. La retención borra del almacén lo que ningún manifiesto vigente usa. El nombre en el almacén es el SHA-256 del contenido en claro: no revela el documento, y no depende de la clave, así que rotarla no rompe la poda.
- **Restaurar verifica todo antes de tocar la base**: sumas de la carpeta, descifrado de la base y descifrado más SHA-256 de cada original. Cada original se descifra dos veces (comprobar y escribir), algo aceptable porque restaurar es raro (`ponytail`). No borra originales posteriores al respaldo: el original nunca se destruye.
- **Formato anterior** (`originales.tar.gz`): no se restaura con el código nuevo. Solo existían respaldos de desarrollo, que la retención borrará.

## 2026-10-07 — Rol de base de datos de la aplicación

- **La aplicación ya no entra como superusuario** (pendiente de despliegue de la fase 1): un superusuario se salta cualquier permiso y puede desactivar el trigger de la auditoría. Hay dos conexiones: `pgsql` (la aplicación, p. ej. `tramite_app`) y `pgsql_dueno` (`DB_DUENO_*`, el superusuario del contenedor), que migra y respalda.
- **`php artisan db:actualizar`** reemplaza a `migrate`: migra con el dueño y luego crea o actualiza el rol de la aplicación con su clave del `.env`. Le da `SELECT/INSERT/UPDATE/DELETE` en todas las tablas y le quita `UPDATE/DELETE/TRUNCATE` en `auditoria`. Como no es dueño, tampoco puede `ALTER TABLE ... DISABLE TRIGGER`. Es idempotente y se corre después de cada migración, para que las tablas nuevas queden con permisos. No se usó `ALTER DEFAULT PRIVILEGES`, que habría vuelto a dar `UPDATE` a una `auditoria` recreada.
- **Sin `DB_DUENO_*` todo sigue como antes** (un solo rol): CI y cualquier instalación previa funcionan sin cambios.
- **Respaldos con el dueño**; la restauración usa `--no-privileges` (en un servidor nuevo el rol aún no existe) y vuelve a aplicar los permisos.
- **Los tests van con el dueño** (`DB_CONNECTION=pgsql_dueno` en `phpunit.xml`, porque migran); `RolBaseDatosTest` prueba el rol restringido con otra conexión y un rol de prueba que borra al terminar.
- **Local**: ya usa `tramite_app` (clave nueva en `.env`) y `tramite` como dueño.

## 2026-10-07 — Antivirus (ClamAV)

- **Se analiza lo que llega de fuera, antes de procesarlo** (11): adjuntos de correo y subidas (escaneo de papel, cargo firmado, PDF firmado). Lo que genera el sistema (PDF de documentos emitidos) no se analiza: así aprobar un documento no depende del antivirus.
- **clamd por TCP con INSTREAM** (`AntivirusService`, ~40 líneas sin librería). Sin `ANTIVIRUS_HOST` no analiza, y los tests lo fijan vacío en `phpunit.xml`. Un test habla con el clamd real usando la firma EICAR (se salta si el contenedor no está).
- **Adjunto infectado → cuarentena, no se descarta**: se guarda en `cuarentena/` (el original no se destruye), con `documentos.amenaza`, sin extraer texto ni OCR, sin descarga. Tampoco se descarga el `.eml` que lo trae. Queda en la auditoría (`documento.en_cuarentena`) y el detalle lo muestra con una etiqueta roja.
- **Subida infectada → se rechaza** con el nombre de la amenaza (regla `SinAmenazas`), y no se guarda nada.
- **Antivirus caído: no pasa nada sin analizar.** El correo falla y se reintenta en la próxima pasada (cada minuto); a la subida se le pide reintentar en unos minutos.
- **`StreamMaxLength` en 50 MB** (por defecto 25 MB): por encima del máximo de subida (40 MB), para que un archivo grande no quede rechazado para siempre.
- **En local está encendido** (`COMPOSE_PROFILES=clamav` y `ANTIVIRUS_HOST=clamav` en `.env`): ~1 GB de RAM, que entra en la VM de 3,8 GB.

## 2026-10-07 — Tendencia mensual

- **Recharts llega con la primera serie de tiempo** (como se dejó en la fase 2): registrados y atendidos por mes, últimos 12 meses con el actual, en Inicio. Cada trámite cuenta en su mes según la hora de Lima (`to_char(... AT TIME ZONE 'America/Lima')`); los meses vacíos van en cero para que el eje no salte.
- **`GraficoMensual`** (componentes de datos): barras agrupadas en `primary-600` y `primary-300` (5.3: los gráficos usan la escala primary; lo semántico es solo para semáforos), colores por variable CSS y no por HEX. Lleva un resumen en texto (`figcaption` oculto) para lectores de pantalla.
- Sobre lo que el usuario atiende (sin copias), igual que el resto del panel.

## 2026-10-07 — Áreas en copia

- **`expediente_areas_copia`** (modelo de datos, sección 6): al derivar o reasignar se eligen áreas en copia. Si vienen en la petición, reemplazan a las anteriores; el área responsable nunca queda además en copia.
- **Ver sí, atender no**: los coordinadores de un área en copia ven el expediente, su historial y sus documentos emitidos (`visiblesPara` y el token `area:N` del índice), pero no lo toman, no comentan ni cierran: la Policy de atención sigue mirando solo el área responsable.
- **No suman pendientes**: el panel y el resumen diario usan `visiblesPara($user, copias: false)`. Lo que un coordinador tiene en copia no es trabajo suyo.
- **Historial con nombres**: el movimiento guarda los nombres de las áreas en copia tal como estaban al derivar (`movimientos.areas_copia`), y la línea de tiempo dice «Copia a …».
- **Fusionar un área** pasa sus copias al destino, salvo donde el destino ya es el responsable.
- **Sin aviso propio a las áreas en copia** (ni correo ni resumen): lo ven al entrar o al buscar. Se agrega si en el uso se echa en falta.

## 2026-10-07 — Piloto: adopción

- **Adopción = respondidos desde el sistema** (16.7): de los expedientes atendidos o cerrados en el año que exigían respuesta, los que tienen una respuesta (`es_respuesta`) **enviada** desde el sistema. Cerrar a mano, o con la respuesta aún en borrador, cuenta como respondido por fuera. Lo «solo para conocimiento» no entra. Es una tarjeta más en Inicio, calculada sobre lo que el usuario puede ver, como el resto del panel. El tiempo de atención y las correcciones de la IA ya estaban en Inicio y en `/ia`.

## 2026-10-07 — Piloto: producción

- **Override `docker-compose.prod.yml`** activado con `COMPOSE_FILE` en el `.env`: los comandos siguen siendo `docker compose …` en ambos entornos. Apaga `vite` (perfil `dev`; los assets salen de `npm run build`) y agrega **Caddy** como único servicio expuesto (80/443), con Let's Encrypt automático o el certificado de la institución. nginx sigue escuchando solo en `127.0.0.1`.
- **Proxies de confianza: solo redes privadas** (`10/8`, `172.16/12`, `192.168/16`, `127.0.0.1`). Así la auditoría guarda la IP real del usuario detrás de Caddy, y desde internet nadie puede suplantarla con `X-Forwarded-For`. La cookie de sesión es `secure` por defecto con `APP_ENV=production`.
- **`MAIL_MAILER=gmail`** (`GmailTransport`): el resumen diario y los avisos salen por la API de Gmail con el mismo token del buzón; no hace falta SMTP ni otra credencial. Symfony no escribe `Bcc` en el mensaje crudo, así que este transporte no sirve para copias ocultas; ningún correo del sistema las usa. Los documentos emitidos siguen por `SalidaCorreo`: su «copia al buzón central» queda en los Enviados de esa misma cuenta.
- **Tiempos de los jobs**: Horizon corta a los 60 s, pero el OCR permite 600 s en su llamada HTTP (un escaneo de 80 folios ronda 3 min). `OcrDocumento` (660 s) y `ClasificarExpediente` (180 s) tienen su propio `timeout`, y `retry_after` pasó de 90 a 720 s: con 90, un job largo se entregaba a un segundo worker mientras el primero seguía. Un test fija la relación.
- **3 workers en producción** (antes 10): cada OCR ocupa un núcleo del servicio de IA y el piloto recibe ≈ 2 documentos por día (14.1).
- **`respaldo:restaurar` aplica también `scout:sync-index-settings`**: en un servidor nuevo el índice no existe, y sin sus filtros la búsqueda por permisos falla.

## 2026-10-07 — Piloto: respaldo y restauración

- **`respaldo:crear` a diario (02:30) y `respaldo:restaurar`** (`RespaldoService`), en el mismo contenedor que el resto: no depende de que el servidor sea Ubuntu físico o VM (pendiente 3). Cada respaldo es una carpeta con `base.dump` (`pg_dump -Fc`; la imagen PHP trae `postgresql18-client`, misma versión que el servidor), `originales.tar.gz`, `modelos.tar.gz` (el volumen `modelos_ia` se monta en los contenedores PHP) y `SHA256SUMS`.
- **Restaurar verifica antes de tocar nada** y restaura la base en una sola transacción; luego los archivos, el índice de Meilisearch (flush + import) y un registro `respaldo.restaurado` en la auditoría. No se respaldan Meilisearch (se rehace) ni Redis; el `.env` se guarda aparte.
- **Retención de 30 días** (`RESPALDO_DIAS`) hasta definir el pendiente 6. La copia fuera del servidor queda en el runbook, con destino por decidir (pendiente 12).
- **Aviso por correo al superadmin si el respaldo falla** (`emailOutputOnFailure`).
- **`colas:reencolar`** (`ColaService`): si Redis se pierde, la base dice qué falta y se vuelve a encolar: envíos pendientes de documentos aprobados (y con firma si la esperaban), escaneos sin texto y expedientes registrados sin clasificación. `EnviarDocumento` pasó a ser único por envío, como ya lo eran el OCR y la clasificación, así que reencolar lo que sigue en la cola no envía dos veces. La restauración lo ejecuta sola. Un OCR que la IA rechazó (archivo dañado) se reintenta una vez por ejecución: es barato y no se marca aparte.

## 2026-10-06 — Interfaz (decisión del usuario)

- **Paleta al estilo de Gmail** (Material 3 de Google) en lugar del índigo de 5.3: azul `#0B57D0`, fondos `#F6F8FC`/`#F2F6FC`, celeste de selección `#D3E3FD` y los grises, verde, amarillo y rojo de Google. Mismos nombres de tokens, así que solo cambió `tokens.css` (y la tabla de 5.3). Todos los pares texto/fondo cumplen AA; el borde de los campos pasó de `#C8C8C8` (1,6:1) a `#747775` (4,5:1), que además cumple el 3:1 de componentes. Se toman solo los colores: sin nombre, logo ni tipografía de Google (la pila de fuentes sigue siendo la del sistema, sin CDN).
- **Crear, editar y ver en un modal** sobre la lista, en lugar de páginas aparte con `FormPage` (cambia lo que fijaba 5.2).
- **Confirmación antes de toda acción que cambia algo** (`ConfirmDialog` con una frase de lo que pasará); no en abrir, filtrar, buscar, descargar ni cancelar.
- **Cómo se implementó**: las rutas de alta, edición y detalle (`/x/create`, `/x/{id}/edit`, `/expedientes/{id}`, `/salientes/{id}`, `/registro/nuevo`) siguen existiendo y devuelven la misma lista con el modal encima (`index()->with('formulario'|'detalle'|'registro', …)`). Así un enlace o el QR siguen abriendo el registro, atrás y recargar funcionan, y un error de validación vuelve con el modal abierto. Abrir y cerrar conservan los filtros de la lista (`lib/modal.ts`).
- **Componentes**: `FormDialog` reemplaza a `FormPage` (borrado); `Dialog` tiene tamaños `md`, `lg` y `xl` (formularios largos y detalles); el panel lateral `Drawer` pasó a `DetalleDialog` (modal); `BotonConfirmado` junta el botón y su confirmación. Las subidas que actúan (cargo firmado, PDF firmado) confirman tras elegir el archivo.

## 2026-10-06 — Fase 5

- **Rebotes y respuestas en la ingesta** (`EntregaService`): un aviso DSN (mailer-daemon/postmaster o `multipart/report`) marca `rebotado` el envío cuyo Message-ID cita, con el `Diagnostic-Code`, y no se anexa al expediente (queda como no trámite). Una respuesta se enlaza por In-Reply-To/References con el envío o, si el cliente perdió el hilo, por el código del asunto y el remitente que recibió el envío; detiene el plazo del documento (`respondido_at`).
- **Envío detrás de `SalidaCorreo`** (como `MailboxDriver`): `gmail` envía el mensaje crudo por la API desde el buzón central con el mismo refresh token (el scope `gmail.modify` permite enviar) y lee el `Message-ID` definitivo, por si Gmail lo reescribe; `mailer` usa el mailer de Laravel (log en desarrollo).
- **Aprobado, sale solo**: la aprobación crea un envío por destinatario y los encola; con «esperar el PDF firmado», sale al adjuntarlo, y ese PDF reemplaza al generado como versión final. El asunto lleva `[REG-AAAA-NNNNN]` y va con copia oculta al buzón central (7.2).
- **Ritmo** con `RateLimited('envios')` (`SALIENTES_POR_MINUTO`): como el limitador reencola sin fallar, el job se reintenta por tiempo (24 h), no por número de intentos; agotado, el envío queda `fallido` con el motivo.
- **Enviada la respuesta, el expediente queda atendido** (movimiento `respuesta`), sin que nadie lo marque (7.3.5, punto 7). El plazo de respuesta que exige un documento se cuenta en días hábiles desde el envío (`PlazoService::sumarDiasHabiles`, mismo cálculo que los plazos de trámite).
- **Flujo de lo emitido**: borrador → en revisión → aprobado → enviado. La aprobación es la única que numera: en una transacción toma el correlativo (`secuencias`, clave `saliente:{tipo}:{área}`, por año), genera el PDF final y lo guarda por hash; desde ahí el documento no se edita. Devolver vuelve a borrador con la observación, sin consumir número.
- **Aprueba el rol del tipo de documento** (`tipos_documento.aprueba_salida`): director, o coordinador del área que emite; nunca quien lo redactó. Redactan registro, dirección y coordinación (esta solo desde sus áreas).
- **Formato de numeración por tipo de documento** (`{TIPO} N.º {NUMERO}-{ANIO}-{AREA}` por defecto) con las siglas del área (nuevo campo; sin siglas se usan las iniciales). El valor inicial de cada correlativo sigue en `config/tramite.php` (`secuencias_inicio`); no hizo falta editarlo desde el panel.
- **PDF con dompdf y Word propio**: PHPWord exige la extensión `gd`, que la imagen no trae; un DOCX de texto es un ZIP con tres XML y se arma con `ZipArchive`. Ambos salen de la misma lista de párrafos (`GeneradorDocumentoService::parrafos`), así que no pueden decir cosas distintas.
- **Plantillas** con variables `{{expediente.codigo}}`, `{{remitente}}`, `{{fecha}}`… que se llenan al redactar; cambiar una plantilla no toca lo ya redactado.

## 2026-10-05 — Fase 4

- **Correcciones validadas por otra persona** (`ia.validar`: director y administrativo): cuando la derivación contradice a la IA queda una corrección; quien la hizo no puede validarla. El panel `/ia` muestra el % de acierto por área, por tipo y por versión, con solo el número de registro (el superadmin no ve contenido).
- **Reentrenamiento** (`php artisan ia:reentrenar`): regresión logística sobre embeddings con las etiquetas confirmadas por personas (aciertos de la IA que la derivación confirmó y correcciones validadas; nunca pendientes ni rechazadas), un ejemplo por expediente. Hace falta que al menos dos categorías tengan 3 ejemplos; el plan sugiere 30-50 por categoría antes de confiar en el modelo, cosa que el panel por versión permite comprobar.
- **Versionado**: cada entrenamiento crea `models/vAAAAMMDDHHMMSS/` (modelo y `meta.json`) en el volumen `modelos_ia` y queda activo; `php artisan ia:modelo` lista las versiones y `php artisan ia:modelo <versión>` o `--similitud` revierte sin reentrenar.
- **Categoría nueva o desactivada sin reentrenar**: si el catálogo trae un área o tipo que el modelo no conoce, ese campo vuelve a la similitud hasta el siguiente reentrenamiento; las inactivas nunca vuelven porque no viajan en el catálogo.
- **Imagen de IA de ~3 GB** (torch CPU, e5-small, Tesseract): el modelo se descarga al construir (`HF_HUB_OFFLINE=1` en ejecución) y se precarga al arrancar; la primera clasificación ya no espera ~25 s.
- **La IA clasifica en cola** (`ClasificarExpediente`) al registrar un trámite y al terminar el OCR de un escaneo; si su texto no cambió, no vuelve a clasificar. Si la IA está caída, el job reintenta durante ~6 h; el ingreso y el registro nunca la esperan (principio 2).
- **El catálogo viaja en cada petición** (áreas y tipos activos con descripción y palabras clave): no hace falta `POST /catalog/reload` y un área inactiva no puede volver como propuesta.
- **Modo sombra** (`ia.modo` en `configuraciones`, por defecto `sombra`): se registra la propuesta y se compara con la primera derivación, pero no se muestra para no sesgar la decisión que la mide.
- **Modo activo = solo propuesta**: sobre `ia.umbral_sugerencia` el diálogo de derivación llega prellenado y dice la confianza; la regla de derivación manda sobre la IA. No hay acciones automáticas (etiquetar, asignar área o registrar sin persona): se habilitan cuando el modo sombra muestre la precisión necesaria; ninguna puede responder, cerrar ni derivar con efecto legal.
- **`/extract` no se movió al servicio de IA**: la extracción por reglas ya vive en Laravel (`ExtraccionService`, fase 3) y es determinista; duplicarla en Python no aporta.

## 2026-10-04 — Fase 3

- **Series** (`grupos`): se agrupan desde el detalle; el sistema propone los documentos del mismo emisor (o remitente), mismo asunto y mismo día, como los oficios circulares del día pico (14.1). Derivar con «toda la serie» deriva en una transacción cada expediente derivable con los mismos datos: cada uno conserva su número, su movimiento y su auditoría.
- **`resources/css/app.css` es entrada de Vite** además de `app.tsx`: las vistas imprimibles lo cargan solo, sin arrancar React.
- **QR con `bacon/bacon-qr-code`** (PHP puro, SVG): codifica el enlace `/qr/REG-AAAA-NNNNN`. La cámara de un teléfono lo abre como enlace y un lector USB lo escribe en la búsqueda de expedientes, que abre directo el expediente. No hace falta un lector QR propio en la página; los permisos los sigue aplicando la vista del expediente.
- **Constancia, etiqueta y cargo son vistas Blade imprimibles** con los mismos tokens de Tailwind (tinta sobre blanco), fuera del AppShell; cada impresión queda auditada (9).
- **El original no tiene acción de descarte**: solo ubicación y custodio, y moverlo es un movimiento auditado. Quien registra el papel queda como su primer custodio.
- **El cargo firmado es un documento del expediente ligado a su derivación** (`documentos.movimiento_id`). Custodian e imprimen quien registra o quien deriva.
- **Registro de papel en dos pasos**: subir el escaneo (se guarda por hash, se extrae el texto y, si no tiene, se hace OCR síncrono para prellenar) y confirmar el formulario. El escaneo pendiente vive un día en caché con su ruta, tipo, páginas y texto; el registro solo acepta un hash que esté ahí. Un escaneo abandonado queda en el almacén de originales (por hash, sin duplicar).
- **Extracción por reglas** (`ExtraccionService`): encabezado «TIPO N° número» de la primera página, fecha «12 de agosto de 2026» o dd/mm/aaaa, línea «ASUNTO:» y emisor vigente cuyo nombre aparece en el texto (el más largo). Solo propone; sin IA.
- **En papel, emisor, tipo, N° y fecha del documento son obligatorios**: sin ellos no hay clave anti-duplicados. El N° se guarda normalizado (mayúsculas, sin tildes, «N°», guiones sin espacios) y tal como venía.
- **Duplicados**: misma clave de negocio (emisor, tipo, N° normalizado, año del documento) bloquea siempre, con el N° existente y enlace; un índice único parcial (que ignora los anulados) es la última barrera ante dos registros simultáneos. Mismo archivo (SHA-256) o mismo emisor, asunto y fecha avisan y se registran al confirmar.
- **Folios = páginas del escaneo**; corregirlos exige motivo, que se guarda.
- **El registro de papel reutiliza `ExpedienteService::confirmar`**: mismo número, mismo bloqueo y misma auditoría que un correo confirmado.
- **OCR con Tesseract (`spa`) en el servicio de IA**, rasterizando PDF a 300 ppp con `pdftoppm`. El archivo viaja como cuerpo crudo (sin `python-multipart`). Todo local (sección 10).
- **El OCR se encola desde `Documento::created`**: un PDF sin capa de texto o una imagen pasan por `OcrDocumento`, venga del correo o del registro de papel. El job es idempotente, reintenta durante ~6 h si el servicio de IA está caído y abandona sin reintentar si el servicio no puede leer el archivo. El ingreso nunca espera al OCR (principio 2).
- **`texto_por_ocr`** marca el texto reconocido (puede tener errores); el texto entra al índice al terminar el OCR.
- **Los tests bloquean HTTP no simulado** (`Http::preventStrayRequests` en `TestCase`): ningún test depende del servicio de IA ni de Google. El OCR real se prueba en el contenedor de IA (`pytest`), con un PDF generado en el test.

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
- **Reglas de derivación**: el tipo de trámite va en columna (con FK) y palabras clave y remitentes en `condicion` jsonb. Se cumplen todas las condiciones presentes (al menos una); dentro de una lista basta una coincidencia. Palabras clave sobre el asunto; remitente como correo exacto o dominio con sus subdominios (`minedu.gob.pe` no coincide con `falsominedu.gob.pe`). Gana la primera activa por prioridad (menor número). Solo sugieren: la derivación (#10) la decide una persona (principio de la sección 1).
- **Umbrales del semáforo en `configuraciones`** (clave/valor jsonb), con valores por defecto en código (30 % y 5 días) mientras no se guarden; se leen en cada cálculo. Solo se audita la clave que cambió. Los días sin movimiento son calendario: miden inactividad, no plazo legal.
- **Emisores duplicados en dos niveles.** Mismo nombre normalizado (sin tildes, mayúsculas ni puntuación) se bloquea con índice único parcial. Nombre parecido (`pg_trgm`, similitud ≥ 0,5) no se bloquea: el alta en línea avisa y se confirma creándolo otra vez, y la lista de emisores propone los pares para fusionarlos. Fusionar reasigna los expedientes y deja el duplicado inactivo con `fusionado_en_id`.
- **Quien registra (`expedientes.registrar`) da de alta emisores en línea** (`EmisorPolicy`), pero no administra catálogos; editar y fusionar exige `configuracion.gestionar`.
- **Emisor y tipo de documento se indican al registrar** desde el detalle del expediente y son opcionales por ahora: el correo aún no los prellena. Se vuelven obligatorios con la captura de papel (fase 3). El resto de campos de 6.1 (N° de documento, folios, requiere respuesta) llega con esa captura y la clave anti-duplicados.
- **`Combobox` propio sobre Radix Popover** (filtra en el cliente por palabras, sin tildes): los catálogos de registro son de cientos de filas, no hace falta buscar en el servidor. `EmisorCombobox` añade el alta en línea con `useHttp`.
- **Activar o desactivar los catálogos simples va en el formulario** (interruptor), sin ruta aparte: no tienen efectos que confirmar.
- **Semáforo** (`SemaforoService`): sin semáforo `historico`, `no_tramite`, `anulado`, `atendido` y `cerrado`; gris lo que aún no se deriva (`por_revisar`, `registrado`). Abierto: rojo si venció o si nadie lo atiende (sin responsable y sin titular ni suplente vigente en el área); amarillo si queda menos del % configurado (en días calendario entre el ingreso y la fecha límite) o si pasan más de N días sin movimiento; si no, verde. Un expediente «para conocimiento» sin plazo nunca pasa a rojo.
- **El semáforo se guarda en cada `save()` del expediente** (`Expediente::booted`) y `semaforos:recalcular` corre cada hora para lo que cambia solo con el tiempo. Un solo punto de cálculo: ingesta, registro y atención no tienen que acordarse de recalcular. Asignar un responsable al área o cambiar un umbral se refleja en el siguiente recálculo.
- **Derivar copia el plazo del tipo** (con el override del área) al derivar por primera vez o al cambiar de tipo o de área; reasignar sin cambios conserva la fecha. Una fecha que fija el documento manda sobre el plazo (8). Un destinatario por derivación (área y, opcional, responsable); las áreas en copia esperan a un caso real.
- **Quién hace qué** (`ExpedientePolicy`): derivan director y administrativo (`expedientes.derivar`, nuevo: hay que volver a correr `RolesSeeder` en cada entorno). Atiende el responsable asignado o quien coordina el área. El cierre lo aprueba el rol que fija el tipo (director o coordinador del área), nunca quien registró ni quien lo pidió; sin aprobación configurada, solicitar el cierre ya cierra. Cerrar marca `atendido_at`.
- **«Para conocimiento» sin plazo**: tomarlo registra la toma de conocimiento y lo deja `atendido` (8). El `atendido` por respuesta vinculada llega con los documentos salientes (fase 5).
- **El coordinador de escuela ve las áreas dependientes** de las que coordina (`User::areasVigentes` recorre la jerarquía); vale para la base y para el índice de búsqueda.
- **Movimientos**: tabla propia que solo se inserta, además de la auditoría; la línea de tiempo muestra destino, instrucción, plazo y nota de cada uno.
- **Panel sin Recharts.** Los KPIs de hoy son conteos y promedios: cifras, semáforos con su badge (icono y texto) y barras de un solo tono con el valor escrito, más tablas. Recharts llega con la primera serie de tiempo (tendencias).
- **Los indicadores salen de `visiblesPara`**: cada rol ve los de lo que puede ver; el superadmin no recibe indicadores de trámites. «Atendidos en plazo» cuenta los atendidos o cerrados del año con fecha límite, comparando el día en Lima de `atendido_at` con la fecha límite. El tiempo de atención va del registro a la atención.
- **Resumen diario** (`resumen:diario`, días laborables a las 07:30, sin feriados institucionales): solo a coordinadores activos con pendientes (derivados o en atención), lo urgente primero; sin pendientes no se envía. Se encola (Horizon) y queda auditado con destinatario y expedientes. El estado de entrega y los rebotes (`notificaciones_enviadas`) esperan al envío por Gmail de la fase 5.
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
