import { router } from '@inertiajs/react';

/** Ruta de un modal (alta, edición o detalle) que conserva los filtros de la lista de atrás. */
export const rutaModal = (ruta: string) => `${ruta}${window.location.search}`;

// Props que solo traen las rutas de modal; la lista de atrás no las usa.
const PROPS_MODAL = ['formulario', 'registro', 'expediente', 'historial', 'detalle'];

/**
 * Cierra el modal al instante volviendo a la lista con sus filtros, sin pedirla al servidor: la lista ya llegó con el
 * modal y lo que guarda o cambia algo redirige y la trae al día.
 */
export const cerrarModal = (lista: string) =>
    router.push({
        url: rutaModal(lista),
        props: (props) => Object.fromEntries(Object.entries(props).filter(([clave]) => !PROPS_MODAL.includes(clave))),
        preserveScroll: true,
        preserveState: true,
    });
