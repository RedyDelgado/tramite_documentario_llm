# Revisión del backlog: Fase 1 · Lectura de Gmail con OAuth2 (#6)

Fecha: 15 de octubre de 2026. Implementación original: `ab3ae5e`.

## Correcciones

- La caché del ID de la etiqueta dependía solo del nombre. Con varias cuentas que usan `tramite/procesado`, podía reutilizar el ID de otra cuenta. Ahora la clave incluye la identidad de la configuración OAuth, el usuario y el nombre, mediante un hash. La prueba usa dos cuentas con `usuario=me`, el mismo nombre y distintos IDs de etiqueta.
- Los reintentos cubrían 429, 500, 502 y 503, pero omitían 504. Ahora cubren 429 y errores 5xx. Se añadieron pruebas de recuperación para 429/500/502/503/504, de agotamiento después de tres intentos y de rechazo de un refresh token revocado sin intentar leer Gmail.

Ambos fallos se reprodujeron mediante pruebas antes de corregirlos. Las etiquetas pertenecen a un usuario y 504 es un error transitorio documentado por Google: [creación de etiquetas](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.labels/create), [manejo de errores](https://developers.google.com/workspace/gmail/api/guides/handle-errors).

## Comportamiento comprobado

Las pruebas simulan las respuestas HTTP de Google: intercambio de refresh token, reutilización del access token en caché, paginación, mensajes raw, límite de lote, omisión de UIDs ya leídos, lectura de metadatos, etiquetado sin borrado y aislamiento entre cuentas.

La descripción antigua del backlog dice que se excluye lo ya etiquetado. El diseño actual usa `correos_leidos` para evitar descargas repetidas; la etiqueta es una referencia visual. Así se puede volver a importar al restaurar o reconstruir una base sin depender de quitar etiquetas en Gmail. El runbook ya documenta este comportamiento; se corrigió el nombre desactualizado de una prueba.

## Reproducir la validación

Resultado: 51 pruebas aprobadas con 235 aserciones; Pint correcto en el driver y sus pruebas. Todas las llamadas a Google se simularon.

```bash
docker compose exec --user www-data -T app php artisan test --compact --do-not-cache-result --filter='GmailMailboxDriverTest|IngestaCorreoTest|BuzonTest|SalientesTest'
docker compose exec --user www-data -T app vendor/bin/pint --test app/Correo/GmailMailboxDriver.php tests/Feature/GmailMailboxDriverTest.php
```

## Estado de aprobación

El usuario confirmó una primera descarga en vivo después de habilitar Gmail API en su proyecto de Google Cloud. No se ha confirmado en esta conversación el resultado de la segunda descarga para comprobar duplicados, ni la descarga de originales desde el expediente con una cuenta autorizada. Mantener esas comprobaciones pendientes; no registrar la prueba en vivo completa como aprobada. El procedimiento está en la sección 5 de [runbook-gmail.md](runbook-gmail.md).

También se añadieron controles para detener entre correos, reanudar desde la fecha original, reiniciar desde una fecha elegida y finalizar conservando lo importado. La detención apaga la descarga automática; las operaciones se restringen al superadmin y quedan auditadas. No se borra el historial de mensajes leídos al reiniciar y el contador visible sigue siendo el total acumulado, no un contador de la ejecución.

Validación posterior de los controles: 44 pruebas aprobadas con 221 aserciones para `BuzonTest|IngestaCorreoTest|GmailMailboxDriverTest`; TypeScript, Pint y compilación correctos. Estos resultados son automáticos y no sustituyen las comprobaciones en vivo pendientes.

La conexión del buzón institucional sigue sujeta a la autorización escrita del pendiente 1 del plan. Las pruebas automáticas no satisfacen ese requisito ni acreditan el funcionamiento en vivo con Google. Los cambios de código también deben incorporarse al repositorio antes de cerrar la tarea.
