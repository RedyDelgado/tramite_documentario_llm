# Runbook: respaldo y restauración

Escrito para que otra persona pueda recuperar el sistema sin ayuda (pendiente 12 del plan). Los comandos se ejecutan en la carpeta del proyecto, en el servidor.

## Qué se respalda

Todo vive en `storage/app/respaldos/`. Cada respaldo es una carpeta `AAAA-MM-DD_HHMMSS/` (hora de Lima) con:

| Archivo | Contenido |
|---|---|
| `base.dump.cifrado` | Toda la base de PostgreSQL: expedientes, movimientos, auditoría, configuración y usuarios (`pg_dump`, formato custom). |
| `modelos.tar.gz.cifrado` | Versiones entrenadas del clasificador de IA (volumen `modelos_ia`). |
| `originales.txt` | Manifiesto: SHA-256 y ruta de cada correo `.eml`, adjunto, escaneo y documento emitido de ese momento. |
| `SHA256SUMS` | Suma de cada archivo de la carpeta; se comprueba antes de restaurar. |

Los originales en sí van a **`archivos/`**, un almacén común a todos los respaldos: cada archivo se guarda **una sola vez**, con su SHA-256 como nombre. Un respaldo diario solo agrega lo nuevo, así que 30 respaldos no ocupan 30 veces el disco. La retención borra del almacén lo que ya ningún respaldo usa. **Una carpeta de respaldo sola no alcanza para restaurar: hace falta también `archivos/`.**

**Cifrado.** Con `RESPALDO_CLAVE` en el `.env`, todo se cifra (sufijo `.cifrado`): la base, los modelos y cada original del almacén. Es un cifrado autenticado (libsodium), así que una clave equivocada o un archivo alterado se detectan al restaurar. Sin `RESPALDO_CLAVE` no se cifra y los archivos van sin sufijo. La clave se genera una vez:

```bash
openssl rand -base64 32
```

**Si se pierde la clave, los respaldos no se pueden leer.** Se guarda junto con el `.env`, fuera del servidor (ver «Guardar el `.env` aparte»). Si se cambia, los respaldos anteriores siguen necesitando la clave vieja.

**No se respalda, a propósito:**

- **Meilisearch**: el índice se rehace desde la base al restaurar.
- **Redis**: sesiones y colas. Al restaurar en un servidor nuevo, todos deben volver a iniciar sesión; lo que estaba en cola se reencola solo (ver «Después de restaurar»).
- **`.env`**: tiene las claves de Google, de la base y `APP_KEY`. Se guarda aparte (abajo), nunca junto a los respaldos ni en el repositorio.

## Cuándo y cuánto se guarda

- Automático todos los días a las **02:30** (contenedor `scheduler`), antes de la verificación de la auditoría de las 03:00.
- Se conservan **30 días** (`RESPALDO_DIAS`). Es un valor provisional hasta definir la política de retención (pendiente 6). Las carpetas con otro nombre (una copia manual, por ejemplo) no se borran.
- Si el respaldo falla, el superadmin (`SUPERADMIN_EMAIL`) recibe un correo con el error.

Respaldo manual, por ejemplo antes de actualizar el sistema:

```bash
docker compose exec --user www-data app php artisan respaldo:crear
```

Usa siempre `--user www-data`: un comando corrido como root deja archivos que después ni el respaldo programado ni la web pueden leer. Si ya pasó, se corrige con `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`.

## Comprobar que se están haciendo (una vez por semana)

```bash
ls -l storage/app/respaldos
```

Debe haber una carpeta por día, la última de esta madrugada. Para comprobar que la última no está dañada (no hace falta la clave):

```bash
cd storage/app/respaldos/<carpeta> && sha256sum -c SHA256SUMS
```

Los originales del almacén no están en esa lista: los protege el cifrado autenticado, que se comprueba al restaurar.

## Copia fuera del servidor (obligatoria)

Los respaldos se guardan en el mismo disco que el sistema: si el disco o el servidor se pierden, se pierden también. **Hay que copiar la carpeta `storage/app/respaldos` completa (con `archivos/`) fuera del servidor** a diario, por ejemplo a un disco externo o a otro equipo de la institución. El destino está por decidir (pendiente 12). Un ejemplo con `rsync` a otro equipo:

```bash
rsync -a --delete storage/app/respaldos/ usuario@otro-equipo:/respaldos/tramite/
```

Como el almacén solo crece con lo nuevo, `rsync` copia cada día únicamente los archivos agregados. Cifrados, los respaldos se pueden guardar en un disco o equipo externo sin exponer los documentos; aun así, conviene que el destino tenga acceso restringido.

## Guardar el `.env` aparte

**El `.env` lleva `RESPALDO_CLAVE`: sin ella, los respaldos cifrados no sirven.** Sin el `.env` el sistema arranca, pero sin conexión a Google ni al buzón. Guarda una copia del `.env` del servidor en un lugar seguro y distinto de los respaldos (por ejemplo, un gestor de contraseñas institucional) cada vez que cambie. Si se pierde, se rehace con `.env.example` y los runbooks de [Gmail](runbook-gmail.md) y del [inicio de sesión con Google](runbook-google-login.md); un `APP_KEY` nuevo solo cierra las sesiones abiertas.

## Restaurar

La restauración **reemplaza** la base y los archivos por los del respaldo: lo registrado después de ese respaldo se pierde. Antes de empezar, si el sistema todavía funciona, haz un respaldo del estado actual (`respaldo:crear`) por si hay que volver atrás.

### En el mismo servidor

```bash
docker compose stop nginx horizon scheduler
docker compose exec --user www-data app php artisan respaldo:restaurar storage/app/respaldos/<carpeta>
docker compose restart ai
docker compose start nginx horizon scheduler
```

El comando pide confirmación y, **antes de tocar nada**, comprueba las sumas de la carpeta, descifra y verifica cada original del almacén contra su SHA-256, y descifra la base. Si algo no coincide (respaldo dañado, incompleto o con otra clave), se niega a seguir. La base se restaura en una sola transacción: si falla a la mitad, queda como estaba.

### En un servidor nuevo (el anterior se perdió)

1. Instalar Docker y clonar el repositorio.
2. Copiar el `.env` guardado aparte a la carpeta del proyecto.
3. Copiar la carpeta del respaldo **y** `archivos/` (desde la copia fuera del servidor) a `storage/app/respaldos/`.
4. Seguir «Arranque» del `README.md` **sin** `key:generate` (la clave viene en el `.env`) ni `db:actualizar --seed` (la base sale del respaldo; la restauración vuelve a dar los permisos al rol de la aplicación).
5. `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`
6. Restaurar como en la sección anterior, desde `respaldo:restaurar`.

### Después de restaurar

- Entrar al sistema y abrir un expediente reciente y uno con escaneo: deben verse sus datos y su PDF.
- `docker compose exec app php artisan auditoria:verificar` debe decir «Cadena íntegra». La restauración queda registrada en la auditoría (`respaldo.restaurado`).
- La búsqueda se reindexa sola; si un expediente no aparece al buscarlo, espera a que Horizon termine la cola.
- **Trabajos que estaban en cola**: la restauración vuelve a encolar los envíos aprobados que no salieron, los escaneos sin OCR y los expedientes sin clasificar; lo que aún seguía en la cola no se duplica. Si alguna vez Redis se pierde sin restaurar nada, se hace a mano con `docker compose exec --user www-data app php artisan colas:reencolar`.

## Simulacro (una vez al mes durante el piloto)

Un respaldo que nunca se restauró no está probado. En una máquina distinta del servidor (un portátil con Docker basta), sigue «En un servidor nuevo» con el respaldo más reciente y la copia del `.env`, y comprueba «Después de restaurar». Anota la fecha y el tiempo que tomó.

Las pruebas automáticas (`tests/Feature/RespaldoTest.php`) restauran un respaldo en la base de pruebas y comprueban que vuelven los mismos datos, archivos y modelos, también cifrados; que sin la clave no se lee la base ni los documentos; que una clave equivocada, un original alterado o un respaldo dañado se rechazan sin tocar la base; que los originales se copian una sola vez, y que la retención solo borra lo vencido y lo que ya nadie usa.
