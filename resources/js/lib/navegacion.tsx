import {
    Apps20Regular,
    Archive20Regular,
    ArrowRouting20Regular,
    BrainCircuit20Regular,
    BuildingGovernment20Regular,
    CalendarCancel20Regular,
    DocumentAdd20Regular,
    DocumentBulletList20Regular,
    DocumentCopy20Regular,
    DocumentText20Regular,
    Gauge20Regular,
    Home20Regular,
    MailAlert20Regular,
    MailProhibited20Regular,
    NumberSymbol20Regular,
    Organization20Regular,
    People20Regular,
    PersonAccounts20Regular,
    Send20Regular,
    TaskListLtr20Regular,
    Timer20Regular,
    TopSpeed20Regular,
} from '@fluentui/react-icons';
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
            { etiqueta: 'Registrar papel', href: '/registro/nuevo', icono: <DocumentAdd20Regular />, permisos: ['expedientes.registrar'] },
            { etiqueta: 'Documentos emitidos', href: '/salientes', icono: <Send20Regular />, permisos: [...PERMISOS_EXPEDIENTES, 'expedientes.registrar'] },
        ],
    },
    {
        titulo: 'Configuración',
        items: [
            { etiqueta: 'Áreas', href: '/areas', icono: <Organization20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Responsables', href: '/responsables', icono: <PersonAccounts20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Tipos de trámite', href: '/tipos-tramite', icono: <DocumentText20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Plazos por área', href: '/plazos', icono: <Timer20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Feriados', href: '/feriados', icono: <CalendarCancel20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Numeración', href: '/numeracion', icono: <NumberSymbol20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Reglas de derivación', href: '/reglas-derivacion', icono: <ArrowRouting20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Umbrales', href: '/umbrales', icono: <TopSpeed20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Inteligencia artificial', href: '/ia', icono: <BrainCircuit20Regular />, permisos: ['ia.validar', 'configuracion.gestionar'] },
            { etiqueta: 'Usuarios', href: '/usuarios', icono: <People20Regular />, permisos: ['usuarios.gestionar'] },
        ],
    },
    {
        titulo: 'Catálogos de registro',
        items: [
            { etiqueta: 'Emisores', href: '/emisores', icono: <BuildingGovernment20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Tipos de documento', href: '/tipos-documento', icono: <DocumentCopy20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Instrucciones', href: '/instrucciones', icono: <TaskListLtr20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Ubicaciones físicas', href: '/ubicaciones', icono: <Archive20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Correo no trámite', href: '/reglas-no-tramite', icono: <MailProhibited20Regular />, permisos: ['configuracion.gestionar'] },
            { etiqueta: 'Plantillas', href: '/plantillas', icono: <DocumentText20Regular />, permisos: ['configuracion.gestionar'] },
        ],
    },
    {
        titulo: 'Sistema',
        items: [
            { etiqueta: 'Notificaciones', href: '/notificaciones', icono: <MailAlert20Regular />, permisos: ['configuracion.gestionar'] },
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
