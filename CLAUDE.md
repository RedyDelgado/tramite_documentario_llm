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

## Comandos

Ver `README.md`. PHP corre en el contenedor `app` (Windows no tiene `pcntl` ni `pdo_pgsql`); Node puede correr en el host.
