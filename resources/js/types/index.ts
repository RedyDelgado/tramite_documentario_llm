// Formas que envía Laravel; cada una espeja su API Resource o el middleware de Inertia.
import type { Semaforo } from '@/components/domain/SemaforoBadge';

export type Usuario = { id: number; name: string; email: string };

/** UsuarioResource. */
export type UsuarioFila = Usuario & {
    rol: string | null;
    rol_etiqueta: string | null;
    activo: boolean;
    administra_configuracion: boolean;
    administra_por_rol: boolean;
    actualizado: string | null;
};

export type Toast ={ tipo: 'ok' | 'error' | 'info'; mensaje: string };

export type SharedProps = {
    app: { nombre: string; local: boolean };
    auth: { user: Usuario | null; roles: string[]; can: string[] };
};

export type Opcion<V = string | number> = { value: V; label: string };

/** Emisor.opciones: persona o institución, y la institución habitual de una persona. */
export type OpcionEmisor = Opcion<number> & { clase?: 'persona' | 'institucion'; institucion_id?: number | null };

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
    // Institución a la que pertenece el emisor en este documento.
    institucion: string | null;
    remitente_por_confirmar: boolean;
    estado: { valor: EstadoExpediente; etiqueta: string };
    semaforo: Semaforo | null;
    fecha_limite: string | null;
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

export type DocumentoDetalle = { id: number; nombre: string; mime: string; tamano: number; sha256: string; con_texto: boolean; amenaza: string | null };

/** ExpedienteController@show. */
export type ExpedienteDetalle = ExpedienteFila & {
    origen: 'correo' | 'fisico' | 'pdf';
    registrado_at: string | null;
    motivo_anulacion: string | null;
    responsable: string | null;
    emisor: string | null;
    tipo_documento: string | null;
    numero_documento: string | null;
    fecha_documento: string | null;
    folios: number | null;
    motivo_folios: string | null;
    tipo_tramite_id: number | null;
    tipo_tramite: string | null;
    area_principal_id: number | null;
    areas_copia: { id: number; nombre: string }[];
    responsable_id: number | null;
    plazo_dias_aplicado: number | null;
    requiere_respuesta: boolean;
    cierre_solicitado_at: string | null;
    atendido_at: string | null;
    permisos: { derivar: boolean; tomar: boolean; comentar: boolean; solicitar_cierre: boolean; resolver_cierre: boolean; custodiar: boolean; agrupar: boolean; redactar: boolean; sugerir: boolean };
    serie: { id: number; nombre: string; expedientes: ResumenExpediente[] } | null;
    original: { ubicacion_fisica_id: number | null; ubicacion: string | null; custodio_id: number | null; custodio: string | null } | null;
    cargos: { id: number; fecha: string; area: string | null; firmado: number | null }[];
    correos: CorreoDetalle[];
    documentos: DocumentoDetalle[];
};

export type EventoHistorial = { id: number; fecha: string; accion: string; usuario: string; detalle?: string | null };

/** ExpedienteController@resumen. */
export type ResumenExpediente = { id: number; numero_registro: string | null; asunto: string; estado: string };

export type OpcionesAgrupacion = { parecidos: ResumenExpediente[]; series: Opcion<number>[] };

/** ExpedienteController@opcionesDerivacion. */
export type OpcionesDerivacion = {
    tipos: Opcion<number>[];
    areas: Opcion<number>[];
    usuarios: Opcion<number>[];
    instrucciones: Opcion<number>[];
    sugerencia: { regla: string; tipo_tramite_id: number | null; area_id: number; responsable_id: number | null } | null;
    // Solo en modo activo y sobre el umbral (ClasificacionService::sugerencia).
    ia: { area_id: number | null; tipo_tramite_id: number | null; confianza_area: number | null; confianza_tipo: number | null; alta: boolean } | null;
};

/** AreaResource. */
export type Area = {
    id: number;
    nombre: string;
    siglas: string | null;
    descripcion: string | null;
    palabras_clave: string[];
    parent_id: number | null;
    padre?: string | null;
    orden: number;
    activa: boolean;
    actualizada: string | null;
};

/** TipoTramiteResource. */
export type TipoTramite = {
    id: number;
    nombre: string;
    descripcion: string | null;
    plazo_dias: number | null;
    tipo_dias: 'habiles' | 'calendario';
    aprueba_cierre: 'director' | 'coordinador' | null;
    aprueba_cierre_etiqueta: string | null;
    activo: boolean;
    actualizado: string | null;
};

/** PlazoAreaResource. */
export type PlazoArea = {
    id: number;
    tipo_tramite_id: number;
    tipo: string;
    tipo_dias: TipoTramite['tipo_dias'];
    plazo_del_tipo: number | null;
    area_id: number;
    area: string;
    plazo_dias: number;
    actualizado: string | null;
};

/** FeriadoResource. */
export type Feriado = { id: number; fecha: string; descripcion: string; area_id: number | null; area?: string | null; actualizado: string | null };

/** ResponsableResource. */
export type Responsable = {
    id: number;
    area_id: number;
    area: string;
    user_id: number;
    usuario: string;
    email: string;
    tipo: 'titular' | 'suplente';
    vigente_desde: string;
    vigente_hasta: string | null;
    vigente: boolean;
    actualizado: string | null;
};

/** ReglaDerivacionResource. */
export type ReglaDerivacion = {
    id: number;
    nombre: string;
    tipo_tramite_id: number | null;
    tipo: string | null;
    palabras_clave: string[];
    remitentes: string[];
    area_destino_id: number;
    area_destino: string;
    responsable_id: number | null;
    responsable: string | null;
    prioridad: number;
    activa: boolean;
    actualizado: string | null;
};

/** EmisorController@fila. */
export type Emisor = {
    id: number;
    nombre: string;
    tipo: 'interno' | 'externo';
    clase: 'persona' | 'institucion';
    institucion_id: number | null;
    institucion: string | null;
    activo: boolean;
    expedientes: number | null;
    actualizado: string | null;
};

export type ParDuplicado = { a: { id: number; nombre: string }; b: { id: number; nombre: string } };

/** TipoDocumentoController@fila. */
export type TipoDocumento = { id: number; nombre: string; formato_numero: string; aprueba_salida: 'director' | 'coordinador'; activo: boolean; actualizado: string | null };

/** InstruccionFrecuenteController@fila. */
export type InstruccionFrecuente = { id: number; texto: string; orden: number; activa: boolean; actualizado: string | null };

/** ReglaNoTramiteController@fila. */
export type ReglaNoTramite = {
    id: number;
    nombre: string;
    campo: 'remitente' | 'dominio' | 'asunto' | 'encabezado';
    campo_etiqueta: string;
    valor: string;
    activa: boolean;
    actualizado: string | null;
};

/** UbicacionFisicaController@fila. */
export type UbicacionFisica = { id: number; nombre: string; descripcion: string | null; activa: boolean; actualizado: string | null };

/** SalienteController@fila. */
export type Saliente = {
    id: number;
    numero: string | null;
    asunto: string;
    tipo: string;
    area: string;
    estado: { valor: 'borrador' | 'en_revision' | 'aprobado' | 'enviado'; etiqueta: string };
    expediente: { id: number; numero_registro: string | null } | null;
    semaforo: Semaforo | null;
    fecha_limite_respuesta: string | null;
    respondido_at: string | null;
    enviado_at: string | null;
    rebotes: number;
    actualizado: string | null;
};

/** SalienteController@show. */
export type SalienteDetalle = Saliente & {
    // Mensaje opcional del correo; el documento es el archivo subido.
    mensaje: string | null;
    // Nombres originales del borrador revisado y del documento final que sale.
    borrador: string | null;
    final: string | null;
    sha256_final: string | null;
    destinatarios: { email: string; nombre: string | null }[];
    autor: string;
    aprobador: string | null;
    aprobado_at: string | null;
    observacion: string | null;
    es_respuesta: boolean;
    requiere_respuesta: boolean;
    plazo_respuesta_dias: number | null;
    envios: { id: number; email: string; nombre: string | null; estado: 'pendiente' | 'enviado' | 'rebotado' | 'fallido'; enviado_at: string | null; detalle: string | null }[];
    permisos: { editar: boolean; revision: boolean; aprobar: boolean; firmar: boolean };
};
