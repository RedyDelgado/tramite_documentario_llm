# Trámite Documentario — guía para agentes

Lee primero `PLAN_SISTEMA_TRAMITE_DOCUMENTARIO.md`: es la fuente de verdad. Trabaja fase por fase y espera aprobación al cerrar cada una (sección 16). Las decisiones de implementación van en `docs/decisiones.md`.

## Reglas que más se rompen

- **Interfaz solo con el sistema de componentes** (`resources/js/components`, sección 5.2). Las páginas componen plantillas (`ListPage`, `FormPage`) y compuestos; no llevan CSS propio. Antes de crear un componente, busca uno que sirva o añádele una variante.
- **Colores solo desde `resources/css/tokens.css`** (5.3). Tailwind no tiene su paleta por defecto; `npm test` falla con un HEX fuera de tokens.
- **Sin `tailwind-merge`**: no pases clases que choquen con las del componente (`px-*`, `bg-*`); usa una variante.
- **Escritura por Services** (`app/Services`), nunca desde el controlador: ahí se engancha la auditoría.
- **Permisos en Policies**; ocultar algo en React no es seguridad.
- **Modales**: no se cierran por clic en el fondo ni Enter guarda; se sale por X, Cancelar o Escape.
- **Fechas y horas**: `America/Lima`.
- Comentarios en español, una línea, solo el porqué (12.1).

## Tablero (GitHub Projects #3)

Una issue por entregable, con el hito de su fase. `gh` está en `"/c/Program Files/GitHub CLI/gh.exe"` (no en el PATH).

- **Backlog → Ready**: lo decide el usuario. No hay columna Ready: lo que está arriba en Backlog va primero.
- **En proceso**: al empezar un entregable. Commits con `refs #N`.
- **En revisión**: al terminarlo con los tests en verde; es la aprobación de la sección 16.
- **Aprobado**: solo el usuario. Nunca cerrar issues ni usar `closes #N`: al pasar a Aprobado, el workflow cierra la issue; si la reabre, vuelve sola a En proceso.
- Límites: Backlog 5, En proceso 3, En revisión 5.
- El repositorio es público: en las issues, nada que no esté ya en el plan.

Mover una tarjeta (ids fijos del proyecto):

```bash
"/c/Program Files/GitHub CLI/gh.exe" project item-edit --project-id PVT_kwHOACYV184BlYzh --id <ITEM_ID> --field-id PVTSSF_lAHOACYV184BlYzhzhkF4Xk --single-select-option-id <OPCION>
```

Opciones: Backlog `f75ad846`, En proceso `47fc9ee4`, En revisión `df73e18b`, Aprobado `98236657`. El `ITEM_ID` sale de `gh project item-list 3 --owner RedyDelgado --format json`.

## Comandos

Ver `README.md`. PHP corre en el contenedor `app` (Windows no tiene `pcntl` ni `pdo_pgsql`); Node puede correr en el host.
