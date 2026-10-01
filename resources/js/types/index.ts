// Formas que envía Laravel; cada una espeja su API Resource o el middleware de Inertia.

export type Usuario = { id: number; name: string; email: string };

export type Toast = { tipo: 'ok' | 'error' | 'info'; mensaje: string };

export type SharedProps = {
    app: { nombre: string; local: boolean };
    auth: { user: Usuario | null; roles: string[]; can: string[] };
};

export type Opcion<V = string | number> = { value: V; label: string };

/** Respuesta de una ResourceCollection paginada de Laravel. */
export type Paginado<T> = {
    data: T[];
    links: { first: string | null; last: string | null; prev: string | null; next: string | null };
    meta: { current_page: number; last_page: number; from: number | null; to: number | null; total: number; per_page: number };
};

/** AreaResource. */
export type Area = {
    id: number;
    nombre: string;
    descripcion: string | null;
    palabras_clave: string[];
    parent_id: number | null;
    padre?: string | null;
    orden: number;
    activa: boolean;
    actualizada: string | null;
};
