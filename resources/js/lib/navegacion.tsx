import { Apps20Regular, Gauge20Regular, Home20Regular, Organization20Regular } from '@fluentui/react-icons';
import type { ReactElement } from 'react';
import type { SharedProps } from '@/types';

export type ItemNav = {
    etiqueta: string;
    href: string;
    icono: ReactElement;
    permiso?: string;
    rol?: string;
    soloLocal?: boolean;
    // Fuera de Inertia (p. ej. Horizon): navegación con recarga completa.
    externo?: boolean;
};

export type GrupoNav = { titulo?: string; items: ItemNav[] };

const NAVEGACION: GrupoNav[] = [
    { items: [{ etiqueta: 'Inicio', href: '/', icono: <Home20Regular /> }] },
    {
        titulo: 'Configuración',
        items: [{ etiqueta: 'Áreas', href: '/areas', icono: <Organization20Regular />, permiso: 'configuracion.gestionar' }],
    },
    {
        titulo: 'Sistema',
        items: [
            { etiqueta: 'Colas (Horizon)', href: '/horizon', icono: <Gauge20Regular />, rol: 'superadmin', externo: true },
            { etiqueta: 'Componentes', href: '/ui', icono: <Apps20Regular />, soloLocal: true },
        ],
    },
];

/** Menú según permisos y roles compartidos por Laravel; ocultar no autoriza, solo ordena la interfaz. */
export function navegacionVisible({ auth, app }: SharedProps): GrupoNav[] {
    return NAVEGACION.map((grupo) => ({
        ...grupo,
        items: grupo.items.filter(
            (i) =>
                (!i.permiso || auth.can.includes(i.permiso)) &&
                (!i.rol || auth.roles.includes(i.rol)) &&
                (!i.soloLocal || app.local),
        ),
    })).filter((grupo) => grupo.items.length > 0);
}

export function esActiva(href: string, url: string): boolean {
    const ruta = url.split('?')[0];
    return href === '/' ? ruta === '/' : ruta === href || ruta.startsWith(`${href}/`);
}
