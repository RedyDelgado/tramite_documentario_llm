// Formas que envía Laravel; cada una espeja su API Resource o el middleware de Inertia.

export type Usuario = { id: number; name: string; email: string };

/** UsuarioResource. */
export type UsuarioFila = Usuario & { rol: string | null; rol_etiqueta: string | null; activo: boolean; actualizado: string | null };

export type Toast ={ tipo: 'ok' | 'error' | 'info'; mensaje: string };

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

export type EstadoExpediente =
    | 'por_revisar'
    | 'registrado'
    | 'derivado'
    | 'en_atencion'
    | 'atendido'
    | 'cerrado'
    | 'no_tramite'
    | 'historico'
    | 'anulado';

/** ExpedienteResource. */
export type ExpedienteFila = {
    id: number;
    numero_registro: string | null;
    codigo: string | null;
    asunto: string;
    remitente_nombre: string | null;
    remitente_email: string | null;
    remitente_por_confirmar: boolean;
    estado: { valor: EstadoExpediente; etiqueta: string };
    fecha_ingreso: string;
    area?: string | null;
    documentos_count?: number;
    puede_registrar: boolean;
};

export type CorreoDetalle = {
    id: number;
    de_nombre: string | null;
    de_email: string;
    para: string[];
    asunto: string;
    fecha: string;
    cuerpo: string | null;
    es_reenvio: boolean;
    documentos: number[];
};

export type DocumentoDetalle = { id: number; nombre: string; mime: string; tamano: number; sha256: string; con_texto: boolean };

/** ExpedienteController@show. */
export type ExpedienteDetalle = ExpedienteFila & {
    origen: 'correo' | 'fisico' | 'pdf';
    registrado_at: string | null;
    motivo_anulacion: string | null;
    responsable: string | null;
    correos: CorreoDetalle[];
    documentos: DocumentoDetalle[];
};

export type EventoHistorial = { id: number; fecha: string; accion: string; usuario: string };

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
