import { IcoArchivo, IcoAreas, IcoColas, IcoComponentes, IcoCorreo,
    IcoCorreoAviso, IcoCorreoBloqueado, IcoDocumento, IcoDocumentos, IcoEnviar, IcoExpedientes, IcoFeriado, IcoIa, IcoInicio, IcoInstitucion, IcoInstrucciones, IcoNuevoDocumento, IcoNumeral, IcoPlazo, IcoResponsables, IcoRuta, IcoUsuarios, IcoVelocimetro } from '@/components/ui/iconos';
import type { ReactElement } from 'react';
import type { SharedProps } from '@/types';

export type ItemNav = {
    etiqueta: string;
    href: string;
    icono: ReactElement;
    // Basta con tener uno de estos permisos.
    permisos?: string[];
    rol?: string;
    soloLocal?: boolean;
    // Fuera de Inertia (p. ej. Horizon): navegación con recarga completa.
    externo?: boolean;
};

export type GrupoNav = { titulo?: string; items: ItemNav[] };

export const PERMISOS_EXPEDIENTES = ['expedientes.ver_todos', 'expedientes.ver_areas', 'expedientes.ver_asignados'];

const NAVEGACION: GrupoNav[] = [
    {
        items: [
            { etiqueta: 'Inicio', href: '/', icono: <IcoInicio /> },
            { etiqueta: 'Expedientes', href: '/expedientes', icono: <IcoExpedientes />, permisos: PERMISOS_EXPEDIENTES },
            { etiqueta: 'Registrar papel', href: '/registro/nuevo', icono: <IcoNuevoDocumento />, permisos: ['expedientes.registrar'] },
            { etiqueta: 'Documentos emitidos', href: '/salientes', icono: <IcoEnviar />, permisos: [...PERMISOS_EXPEDIENTES, 'expedientes.registrar'] },
        ],
    },
    {
        titulo: 'Configuración',
        items: [
            { etiqueta: 'Áreas', href: '/areas', icono: <IcoAreas />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Responsables', href: '/responsables', icono: <IcoResponsables />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Tipos de trámite', href: '/tipos-tramite', icono: <IcoDocumento />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Plazos por área', href: '/plazos', icono: <IcoPlazo />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Feriados', href: '/feriados', icono: <IcoFeriado />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Numeración', href: '/numeracion', icono: <IcoNumeral />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Reglas de derivación', href: '/reglas-derivacion', icono: <IcoRuta />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Umbrales', href: '/umbrales', icono: <IcoVelocimetro />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Inteligencia artificial', href: '/ia', icono: <IcoIa />, permisos: ['ia.validar', 'configuracion.gestionar'] },
            { etiqueta: 'Usuarios', href: '/usuarios', icono: <IcoUsuarios />, permisos: ['usuarios.gestionar'] },
        ],
    },
    {
        titulo: 'Catálogos de registro',
        items: [
            { etiqueta: 'Emisores', href: '/emisores', icono: <IcoInstitucion />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Tipos de documento', href: '/tipos-documento', icono: <IcoDocumentos />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Instrucciones', href: '/instrucciones', icono: <IcoInstrucciones />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Ubicaciones físicas', href: '/ubicaciones', icono: <IcoArchivo />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Correo no trámite', href: '/reglas-no-tramite', icono: <IcoCorreoBloqueado />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Plantillas', href: '/plantillas', icono: <IcoDocumento />, permisos: ['configuracion.gestionar'] },
        ],
    },
    {
        titulo: 'Sistema',
        items: [
            { etiqueta: 'Buzón central', href: '/buzon', icono: <IcoCorreo />, rol: 'superadmin' },
            { etiqueta: 'Notificaciones', href: '/notificaciones', icono: <IcoCorreoAviso />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Colas (Horizon)', href: '/horizon', icono: <IcoColas />, rol: 'superadmin', externo: true },
            { etiqueta: 'Componentes', href: '/ui', icono: <IcoComponentes />, soloLocal: true },
        ],
    },
];

export function tieneAlguno(can: string[], permisos: string[]): boolean {
    return permisos.some((p) => can.includes(p));
}

/** Menú según permisos y roles compartidos por Laravel; ocultar no autoriza, solo ordena la interfaz. */
export function navegacionVisible({ auth, app }: SharedProps): GrupoNav[] {
    return NAVEGACION.map((grupo) => ({
        ...grupo,
        items: grupo.items.filter(
            (i) =>
                (!i.permisos || tieneAlguno(auth.can, i.permisos)) &&
                (!i.rol || auth.roles.includes(i.rol)) &&
                (!i.soloLocal || app.local),
        ),
    })).filter((grupo) => grupo.items.length > 0);
}

export function esActiva(href: string, url: string): boolean {
    const ruta = url.split('?')[0];
    return href === '/' ? ruta === '/' : ruta === href || ruta.startsWith(`${href}/`);
}
