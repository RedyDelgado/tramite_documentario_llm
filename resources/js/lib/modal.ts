import { router } from '@inertiajs/react';

/** Ruta de un modal (alta, edición o detalle) que conserva los filtros de la lista de atrás. */
export const rutaModal = (ruta: string) => `${ruta}${window.location.search}`;

/** Cierra el modal volviendo a la lista con sus filtros. */
export const cerrarModal = (lista: string) => router.get(rutaModal(lista), {}, { preserveScroll: true });
