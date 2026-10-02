import { Apps20Regular, DocumentBulletList20Regular, Gauge20Regular, Home20Regular, Organization20Regular, People20Regular } from '@fluentui/react-icons';
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

export const PERMISOS_EXPEDIENTES = ['expedientes.ver_todos', 'expedientes.ver_areas'];

const NAVEGACION: GrupoNav[] = [
    {
        items: [
            { etiqueta: 'Inicio', href: '/', icono: <Home20Regular /> },
            { etiqueta: 'Expedientes', href: '/expedientes', icono: <DocumentBulletList20Regular />, permisos: PERMISOS_EXPEDIENTES },
        ],
    },
    {
        titulo: 'Configuración',
        items: [
            { etiqueta: 'Áreas', href: '/areas', icono: <Organization20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Usuarios', href: '/usuarios', icono: <People20Regular />, permisos: ['usuarios.gestionar'] },
        ],
    },
    {
        titulo: 'Sistema',
        items: [
            { etiqueta: 'Colas (Horizon)', href: '/horizon', icono: <Gauge20Regular />, rol: 'superadmin', externo: true },
            { etiqueta: 'Componentes', href: '/ui', icono: <Apps20Regular />, soloLocal: true },
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
