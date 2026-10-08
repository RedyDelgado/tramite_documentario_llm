import { IcoAdjunto16, IcoBuscar, IcoExpedientes, IcoNuevoDocumento } from '@/components/ui/iconos';
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
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import { cerrarModal, rutaModal } from '@/lib/modal';
import type { ExpedienteFila, Opcion, Paginado, SharedProps } from '@/types';
import RegistroNuevo from '../registro/Nuevo';
import ExpedienteShow, { type DetalleExpedienteProps } from './Show';

type Filtros = { q?: string; estado?: string; semaforo?: string; dir?: 'asc' | 'desc'; vista?: string };

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
    { clave: 'fecha', titulo: 'Ingreso', sinCorte: true, ordenable: true, ancho: '11rem', celda: (e) => formatearFechaHora(e.fecha_ingreso) },
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
    { clave: 'estado', titulo: 'Estado', ancho: '10rem', celda: (e) => <EstadoBadge estado={e.estado} /> },
    { clave: 'semaforo', titulo: 'Semáforo', ancho: '9rem', celda: (e) => (e.semaforo ? <SemaforoBadge estado={e.semaforo} /> : <span className="text-fg-muted">—</span>) },
];

type Props = {
    expedientes: Paginado<ExpedienteFila>;
    filtros: Filtros;
    vista: string;
    vistas: Pestana[];
    estados: Opcion<string>[];
    semaforos: Opcion<string>[];
    registro?: Omit<ComponentProps<typeof RegistroNuevo>, 'onCerrar'>;
} & Partial<DetalleExpedienteProps>;

export default function ExpedientesIndex({ expedientes, filtros: iniciales, vista, vistas, estados, semaforos, registro, expediente, historial, ...detalle }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const hayFiltros = Boolean(filtros.q || filtros.estado || filtros.semaforo);
    const puedeRegistrar = usePage<SharedProps>().props.auth.can.includes('expedientes.registrar');

    return (
        <>
            {registro && <RegistroNuevo {...registro} onCerrar={() => cerrarModal('/expedientes')} />}
            {expediente && historial && <ExpedienteShow expediente={expediente} historial={historial} {...detalle} onCerrar={() => cerrarModal('/expedientes')} />}
            <ListPage
                titulo="Expedientes"
                descripcion="Todo lo que ingresa por el buzón central o en papel: revisa lo que llega, regístralo como trámite y síguelo hasta cerrarlo."
                pestanas={<Pestanas etiqueta="Vista" pestanas={vistas} activa={vista} onCambiar={(v) => cambiar({ vista: v })} />}
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
                    columnas,
                    filas: expedientes.data,
                    claveFila: (e) => e.id,
                    // Con búsqueda manda la relevancia; sin ella, la fecha de ingreso.
                    orden: filtros.q ? undefined : { clave: 'fecha', dir: filtros.dir ?? 'desc' },
                    onOrdenar: filtros.q ? undefined : () => cambiar({ dir: filtros.dir === 'asc' ? 'desc' : 'asc' }),
                    seleccionada: expediente?.id ?? null,
                    onElegirFila: (e) => router.visit(rutaModal(`/expedientes/${e.id}`), { preserveScroll: true }),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<IcoExpedientes />}
                            titulo={hayFiltros ? 'Ningún expediente coincide' : vista === 'por_revisar' ? 'Nada por revisar' : 'Nada en esta pestaña'}
                            descripcion={hayFiltros ? 'Prueba con otras palabras o quita el filtro de estado.' : vista === 'por_revisar' ? 'No hay correos esperando revisión: los nuevos del buzón central aparecen aquí.' : 'Prueba en otra pestaña.'}
                        />
                    ),
                }}
                paginacion={expedientes}
            />
        </>
    );
}
