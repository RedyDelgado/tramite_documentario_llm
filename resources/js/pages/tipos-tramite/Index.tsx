import { Add20Regular, DocumentText20Regular, Edit20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DetalleDialog } from '@/components/ui/DetalleDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora, formatearPlazo } from '@/lib/fechas';
import type { Paginado, TipoTramite } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { q?: string; estado?: string; orden?: string; dir?: 'asc' | 'desc' };

const columnas: Columna<TipoTramite>[] = [
    {
        clave: 'nombre',
        titulo: 'Nombre',
        ordenable: true,
        celda: (t) => (
            <div className="min-w-48">
                <p className="font-semibold">{t.nombre}</p>
                {t.descripcion && <p className="max-w-md truncate text-sm text-fg-muted">{t.descripcion}</p>}
            </div>
        ),
    },
    { clave: 'plazo', titulo: 'Plazo', ordenable: true, celda: (t) => formatearPlazo(t.plazo_dias, t.tipo_dias) },
    { clave: 'cierre', titulo: 'Aprueba el cierre', celda: (t) => t.aprueba_cierre_etiqueta ?? <span className="text-fg-muted">No requiere</span> },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (t) => <ActivoBadge activo={t.activo} /> },
];

export default function TiposTramiteIndex({ tipos, filtros: iniciales, formulario }: { tipos: Paginado<TipoTramite>; filtros: Filtros } & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const elegido = tipos.data.find((t) => t.id === elegidoId) ?? null;
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    const ordenar = (clave: string) =>
        cambiar({ orden: clave, dir: (filtros.orden ?? 'nombre') === clave && filtros.dir !== 'desc' ? 'desc' : 'asc' });

    const cambiarEstado = (tipo: TipoTramite) =>
        router.patch(
            `/tipos-tramite/${tipo.id}/estado`,
            { activo: !tipo.activo },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcesando(true),
                onFinish: () => {
                    setProcesando(false);
                    setConfirmando(false);
                },
            },
        );

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/tipos-tramite')} />}
            <ListPage
                titulo="Tipos de trámite"
                descripcion="La naturaleza del trámite define su plazo. Cambiar un plazo no altera los expedientes ya ingresados."
                acciones={
                    <Link href={rutaModal('/tipos-tramite/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <Add20Regular />
                        Nuevo tipo
                    </Link>
                }
                filtros={
                    <>
                        <Select
                            aria-label="Estado"
                            className="w-36"
                            vacia="Todos"
                            opciones={[
                                { value: 'activos', label: 'Activos' },
                                { value: 'inactivos', label: 'Inactivos' },
                            ]}
                            value={filtros.estado ?? ''}
                            onChange={(e) => cambiar({ estado: e.target.value })}
                        />
                        <Input
                            type="search"
                            aria-label="Buscar por nombre"
                            placeholder="Buscar por nombre"
                            iconoInicio={<Search20Regular />}
                            className="w-64"
                            value={filtros.q ?? ''}
                            onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Tipos de trámite',
                    columnas,
                    filas: tipos.data,
                    claveFila: (t) => t.id,
                    orden: { clave: filtros.orden ?? 'nombre', dir: filtros.dir ?? 'asc' },
                    onOrdenar: ordenar,
                    seleccionada: elegidoId,
                    onElegirFila: (t) => setElegidoId(t.id),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<DocumentText20Regular />}
                            titulo={hayFiltros ? 'No hay tipos que coincidan con los filtros' : 'Aún no hay tipos de trámite'}
                            descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Por ejemplo: invitación, requerimiento de información, solicitud de recursos.'}
                        />
                    ),
                }}
                paginacion={tipos}
                detalle={
                    elegido && (
                        <DetalleDialog
                            abierto
                            onCambiar={(abierto) => !abierto && setElegidoId(null)}
                            titulo={elegido.nombre}
                            subtitulo={<ActivoBadge activo={elegido.activo} />}
                            acciones={
                                <>
                                    <Link href={rutaModal(`/tipos-tramite/${elegido.id}/edit`)} preserveScroll className={botonClases()}>
                                        <Edit20Regular />
                                        Editar
                                    </Link>
                                    {elegido.activo ? (
                                        <Button onClick={() => setConfirmando(true)}>Desactivar</Button>
                                    ) : (
                                        <BotonConfirmado
                                            titulo={`¿Activar «${elegido.nombre}»?`}
                                            descripcion="Se podrá asignar a nuevos expedientes."
                                            confirmar="Activar"
                                            cargando={procesando}
                                            onConfirmar={() => cambiarEstado(elegido)}
                                        >
                                            Activar
                                        </BotonConfirmado>
                                    )}
                                </>
                            }
                        >
                            <DetalleLista
                                items={[
                                    { etiqueta: 'Descripción', valor: elegido.descripcion ?? '—' },
                                    { etiqueta: 'Plazo', valor: formatearPlazo(elegido.plazo_dias, elegido.tipo_dias) },
                                    { etiqueta: 'Aprueba el cierre', valor: elegido.aprueba_cierre_etiqueta ?? 'No requiere aprobación' },
                                    { etiqueta: 'Última actualización', valor: formatearFechaHora(elegido.actualizado) },
                                ]}
                            />
                            <ConfirmDialog
                                abierto={confirmando}
                                onCambiar={setConfirmando}
                                titulo={`¿Desactivar «${elegido.nombre}»?`}
                                descripcion="No se podrá asignar a nuevos expedientes. Los que ya lo tienen conservan su tipo y su plazo."
                                confirmar="Desactivar"
                                peligro
                                cargando={procesando}
                                onConfirmar={() => cambiarEstado(elegido)}
                            />
                        </DetalleDialog>
                    )
                }
            />
        </>
    );
}
