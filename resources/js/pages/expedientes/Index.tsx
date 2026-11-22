import { IcoAdjunto16, IcoBuscar, IcoExpedientes, IcoNuevoDocumento, IcoRespondido16 } from '@/components/ui/iconos';
import { Link, router, usePage } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { type Pestana, Pestanas } from '@/components/data/Pestanas';
import { EstadoBadge } from '@/components/domain/EstadoBadge';
import { SemaforoBadge } from '@/components/domain/SemaforoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { Badge } from '@/components/ui/Badge';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Tooltip } from '@/components/ui/Tooltip';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora, textoEspera, textoVencimiento } from '@/lib/fechas';
import { cerrarModal, rutaModal } from '@/lib/modal';
import type { ExpedienteFila, Opcion, Paginado, SharedProps } from '@/types';
import RegistroNuevo from '../registro/Nuevo';
import ExpedienteShow, { type DetalleExpedienteProps } from './Show';

type Filtros = { q?: string; estado?: string; semaforo?: string; tipo?: string; area?: string; dir?: 'asc' | 'desc'; vista?: string };

function Tipo({ e }: { e: ExpedienteFila }) {
    if (e.tipo_tramite) {
        return e.tipo_tramite;
    }
    // Solo a un registrado le falta derivarse; lo demás sin tipo (por revisar, no trámite…) no espera ese paso.
    if (e.estado.valor !== 'registrado') {
        return <span className="text-fg-muted">—</span>;
    }
    // El tipo de documento, si lo hay, orienta mientras tanto.
    return <span className="text-fg-muted">{e.tipo_documento ? `${e.tipo_documento} · por derivar` : 'Por derivar'}</span>;
}

function Remitente({ e }: { e: ExpedienteFila }) {
    return (
        <span className="flex flex-wrap items-center gap-1">
            {e.remitente_nombre ?? e.remitente_email}
            {e.institucion && <span>· {e.institucion}</span>}
            {e.remitente_por_confirmar && <Badge>Remitente por confirmar</Badge>}
        </span>
    );
}

const columnas: Columna<ExpedienteFila>[] = [
    {
        clave: 'numero',
        titulo: 'N° registro',
        ancho: '8rem',
        celda: (e) => e.numero_registro ?? <span className="text-fg-muted">—</span>,
    },
    {
        clave: 'asunto',
        titulo: 'Asunto',
        celda: (e) => (
            <div className="min-w-64">
                <p className="font-semibold">{e.asunto}</p>
                <p className="text-sm text-fg-muted">
                    <Remitente e={e} />
                </p>
            </div>
        ),
    },
    { clave: 'tipo', titulo: 'Tipo de trámite', ancho: '13rem', celda: (e) => <Tipo e={e} /> },
    {
        clave: 'fecha',
        titulo: 'Ingreso',
        sinCorte: true,
        ordenable: true,
        ancho: '11rem',
        celda: (e) => (
            <>
                {formatearFechaHora(e.fecha_ingreso)}
                {/* Aún sin área: lo que lleva esperando es lo que se le está gastando del plazo. */}
                {(e.estado.valor === 'por_revisar' || e.estado.valor === 'registrado') && <p className="text-sm text-fg-muted">{textoEspera(e.fecha_ingreso)}</p>}
            </>
        ),
    },
    { clave: 'area', titulo: 'Área', ancho: '12rem', celda: (e) => e.area ?? <span className="text-fg-muted">Sin asignar</span> },
    {
        clave: 'adjuntos',
        titulo: 'Adjuntos',
        alinear: 'derecha',
        ancho: '6rem',
        celda: (e) =>
            e.documentos_count ? (
                <span className="inline-flex items-center gap-1">
                    <IcoAdjunto16 aria-hidden />
                    {e.documentos_count}
                </span>
            ) : (
                <span className="text-fg-muted">—</span>
            ),
    },
    {
        clave: 'respuesta',
        titulo: 'Respuesta',
        ancho: '11rem',
        celda: (e) =>
            e.respuesta ? (
                <span className="flex flex-wrap items-center gap-1">
                    <Tooltip texto={`Respondido el ${formatearFechaHora(e.respuesta.fecha)}`}>
                        <span tabIndex={0}>
                            <Badge tono="ok" icono={<IcoRespondido16 aria-hidden />}>
                                Respondido
                            </Badge>
                        </span>
                    </Tooltip>
                    {e.respuesta.nueva && (
                        <Tooltip texto="Llegó una respuesta que aún no se revisa">
                            <span tabIndex={0}>
                                <Badge tono="marca">Nuevo</Badge>
                            </span>
                        </Tooltip>
                    )}
                </span>
            ) : (
                <span className="text-fg-muted">—</span>
            ),
    },
    { clave: 'estado', titulo: 'Estado', ancho: '10rem', celda: (e) => <EstadoBadge estado={e.estado} /> },
    {
        clave: 'semaforo',
        titulo: 'Semáforo',
        ancho: '10rem',
        celda: (e) =>
            e.semaforo ? (
                <>
                    <SemaforoBadge estado={e.semaforo} />
                    {/* El color dice si urge; la fecha, cuánto. */}
                    {e.fecha_limite && (e.estado.valor === 'derivado' || e.estado.valor === 'en_atencion') && (
                        <p className="mt-1 text-sm text-fg-muted">{textoVencimiento(e.fecha_limite)}</p>
                    )}
                </>
            ) : (
                <span className="text-fg-muted">—</span>
            ),
    },
];

/** En trámite el clic recorre urgencia → más recientes → más antiguos → urgencia; en lo demás, alterna. */
function siguienteOrden(vista: string, dir: Filtros['dir']): Filtros['dir'] {
    if (vista === 'en_curso') return dir === undefined ? 'desc' : dir === 'desc' ? 'asc' : undefined;
    return (dir ?? (vista === 'por_revisar' ? 'asc' : 'desc')) === 'asc' ? 'desc' : 'asc';
}

type Props = {
    expedientes: Paginado<ExpedienteFila>;
    filtros: Filtros;
    vista: string;
    vistas: Pestana[];
    estados: Opcion<string>[];
    semaforos: Opcion<string>[];
    tipos: Opcion<number>[];
    areas: Opcion<number>[];
    registro?: Omit<ComponentProps<typeof RegistroNuevo>, 'onCerrar'>;
} & Partial<DetalleExpedienteProps>;

export default function ExpedientesIndex({ expedientes, filtros: iniciales, vista, vistas, estados, semaforos, tipos, areas, registro, expediente, historial, ...detalle }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const dirIngreso = filtros.dir ?? (vista === 'por_revisar' ? 'asc' : 'desc');
    const hayFiltros = Boolean(filtros.q || filtros.estado || filtros.semaforo || filtros.tipo || filtros.area);
    const puedeRegistrar = usePage<SharedProps>().props.auth.can.includes('expedientes.registrar');

    return (
        <>
            {registro && <RegistroNuevo {...registro} onCerrar={() => cerrarModal('/expedientes')} />}
            {expediente && historial && <ExpedienteShow expediente={expediente} historial={historial} {...detalle} onCerrar={() => cerrarModal('/expedientes')} />}
            <ListPage
                titulo="Expedientes"
                descripcion="Todo lo que ingresa por el buzón central o en papel: revisa lo que llega, regístralo como trámite y síguelo hasta cerrarlo."
                pestanas={<Pestanas etiqueta="Vista" pestanas={vistas} activa={vista} onCambiar={(v) => cambiar({ vista: v, dir: undefined })} />}
                acciones={
                    puedeRegistrar && (
                        <Link href={rutaModal('/registro/nuevo')} preserveScroll className={botonClases({ variante: 'primario' })}>
                            <IcoNuevoDocumento />
                            Registrar papel
                        </Link>
                    )
                }
                filtros={
                    <>
                        <Select
                            aria-label="Estado"
                            className="w-40"
                            vacia="Todos los estados"
                            opciones={estados}
                            value={filtros.estado ?? ''}
                            onChange={(e) => cambiar({ estado: e.target.value })}
                        />
                        <Select
                            aria-label="Semáforo"
                            className="w-48"
                            vacia="Todos los semáforos"
                            opciones={semaforos}
                            value={filtros.semaforo ?? ''}
                            onChange={(e) => cambiar({ semaforo: e.target.value })}
                        />
                        <Select
                            aria-label="Tipo de trámite"
                            className="w-56"
                            vacia="Todos los tipos"
                            opciones={tipos}
                            value={filtros.tipo ?? ''}
                            onChange={(e) => cambiar({ tipo: e.target.value })}
                        />
                        <Select
                            aria-label="Área"
                            className="w-48"
                            vacia="Todas las áreas"
                            opciones={areas}
                            value={filtros.area ?? ''}
                            onChange={(e) => cambiar({ area: e.target.value })}
                        />
                        <Input
                            type="search"
                            aria-label="Filtrar la lista"
                            placeholder="Asunto, remitente, código o texto"
                            iconoInicio={<IcoBuscar />}
                            className="w-72"
                            value={filtros.q ?? ''}
                            onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Expedientes',
                    // En «Por revisar» nadie eligió aún el tipo: la columna saldría vacía en todas las filas.
                    columnas: vista === 'por_revisar' ? columnas.filter((c) => c.clave !== 'tipo') : columnas,
                    filas: expedientes.data,
                    claveFila: (e) => e.id,
                    // Con búsqueda manda la relevancia; sin ella y sin orden elegido, la urgencia en trámite y lo que más espera en por revisar.
                    orden: filtros.q || (!filtros.dir && vista === 'en_curso') ? undefined : { clave: 'fecha', dir: dirIngreso },
                    onOrdenar: filtros.q ? undefined : () => cambiar({ dir: siguienteOrden(vista, filtros.dir) }),
                    seleccionada: expediente?.id ?? null,
                    onElegirFila: (e) => router.visit(rutaModal(`/expedientes/${e.id}`), { preserveScroll: true }),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<IcoExpedientes />}
                            titulo={hayFiltros ? 'Ningún expediente coincide' : vista === 'por_revisar' ? 'Nada por revisar' : 'Nada en esta pestaña'}
                            descripcion={hayFiltros ? 'Prueba con otras palabras o quita algún filtro.' : vista === 'por_revisar' ? 'No hay correos esperando revisión: los nuevos del buzón central aparecen aquí.' : 'Prueba en otra pestaña.'}
                        />
                    ),
                }}
                paginacion={expedientes}
            />
        </>
    );
}
