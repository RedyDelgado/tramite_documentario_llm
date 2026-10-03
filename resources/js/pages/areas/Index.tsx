import { IcoAgregar, IcoAreas, IcoBuscar, IcoEditar, IcoFusionar } from '@/components/ui/iconos';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ListaEtiquetas } from '@/components/data/ListaEtiquetas';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { FormField } from '@/components/forms/FormField';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog } from '@/components/ui/Dialog';
import { DetalleDialog } from '@/components/ui/DetalleDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Area, Opcion, Paginado } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { q?: string; estado?: string; orden?: string; dir?: 'asc' | 'desc' };

const columnas: Columna<Area>[] = [
    {
        clave: 'nombre',
        titulo: 'Nombre',
        ordenable: true,
        celda: (a) => (
            <div className="min-w-48">
                <p className="font-semibold">{a.nombre}</p>
                {a.descripcion && <p className="max-w-md truncate text-sm text-fg-muted">{a.descripcion}</p>}
            </div>
        ),
    },
    { clave: 'padre', titulo: 'Área superior', celda: (a) => a.padre ?? <span className="text-fg-muted">—</span> },
    { clave: 'palabras', titulo: 'Palabras clave', celda: (a) => <ListaEtiquetas lista={a.palabras_clave} max={3} /> },
    { clave: 'orden', titulo: 'Orden', ordenable: true, alinear: 'derecha', ancho: '6rem', celda: (a) => a.orden },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (a) => <ActivoBadge activo={a.activa} femenino /> },
];

type Props = { areas: Paginado<Area>; filtros: Filtros; opcionesArea: Opcion<number>[] };

export default function AreasIndex({ areas, filtros: iniciales, opcionesArea, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidaId, setElegidaId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [destino, setDestino] = useState<string | null>(null);
    const elegida = areas.data.find((a) => a.id === elegidaId) ?? null;
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    const ordenar = (clave: string) =>
        cambiar({ orden: clave, dir: (filtros.orden ?? 'orden') === clave && filtros.dir !== 'desc' ? 'desc' : 'asc' });

    const cambiarEstado = (area: Area) =>
        router.patch(
            `/areas/${area.id}/estado`,
            { activa: !area.activa },
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

    const fusionar = (area: Area) =>
        router.post(
            `/areas/${area.id}/fusionar`,
            { destino_id: Number(destino) },
            {
                preserveScroll: true,
                onStart: () => setProcesando(true),
                onFinish: () => setProcesando(false),
                onSuccess: () => {
                    setDestino(null);
                    setElegidaId(null);
                },
            },
        );

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/areas')} />}
            <ListPage
                titulo="Áreas"
                descripcion="Áreas de la institución, su jerarquía y las palabras clave que orientan la derivación."
                acciones={
                    <Link href={rutaModal('/areas/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <IcoAgregar />
                        Nueva área
                    </Link>
                }
                filtros={
                    <>
                        <Select
                            aria-label="Estado"
                            className="w-36"
                            vacia="Todas"
                            opciones={[
                                { value: 'activas', label: 'Activas' },
                                { value: 'inactivas', label: 'Inactivas' },
                            ]}
                            value={filtros.estado ?? ''}
                            onChange={(e) => cambiar({ estado: e.target.value })}
                        />
                        <Input
                            type="search"
                            aria-label="Buscar por nombre"
                            placeholder="Buscar por nombre"
                            iconoInicio={<IcoBuscar />}
                            className="w-64"
                            value={filtros.q ?? ''}
                            onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Áreas',
                    columnas,
                    filas: areas.data,
                    claveFila: (a) => a.id,
                    orden: { clave: filtros.orden ?? 'orden', dir: filtros.dir ?? 'asc' },
                    onOrdenar: ordenar,
                    seleccionada: elegidaId,
                    onElegirFila: (a) => setElegidaId(a.id),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<IcoAreas />}
                            titulo={hayFiltros ? 'No hay áreas que coincidan con los filtros' : 'Aún no hay áreas'}
                            descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Crea la primera área para empezar a derivar expedientes.'}
                        />
                    ),
                }}
                paginacion={areas}
                detalle={
                    elegida && (
                        <DetalleDialog
                            abierto
                            onCambiar={(abierto) => !abierto && setElegidaId(null)}
                            titulo={elegida.nombre}
                            subtitulo={<ActivoBadge activo={elegida.activa} femenino />}
                            acciones={
                                <>
                                    <Link href={rutaModal(`/areas/${elegida.id}/edit`)} preserveScroll className={botonClases()}>
                                        <IcoEditar />
                                        Editar
                                    </Link>
                                    {elegida.activa && (
                                        <Button icono={<IcoFusionar />} onClick={() => setDestino('')}>
                                            Fusionar
                                        </Button>
                                    )}
                                    {elegida.activa ? (
                                        <Button onClick={() => setConfirmando(true)}>Desactivar</Button>
                                    ) : (
                                        <BotonConfirmado
                                            titulo={`¿Activar «${elegida.nombre}»?`}
                                            descripcion="Volverá a recibir derivaciones."
                                            confirmar="Activar"
                                            cargando={procesando}
                                            onConfirmar={() => cambiarEstado(elegida)}
                                        >
                                            Activar
                                        </BotonConfirmado>
                                    )}
                                </>
                            }
                        >
                            <DetalleLista
                                items={[
                                    { etiqueta: 'Descripción', valor: elegida.descripcion ?? '—' },
                                    { etiqueta: 'Área superior', valor: elegida.padre ?? 'Ninguna (área principal)' },
                                    { etiqueta: 'Palabras clave', valor: <ListaEtiquetas lista={elegida.palabras_clave} /> },
                                    { etiqueta: 'Orden', valor: elegida.orden },
                                    { etiqueta: 'Última actualización', valor: formatearFechaHora(elegida.actualizada) },
                                ]}
                            />
                            <ConfirmDialog
                                abierto={confirmando}
                                onCambiar={setConfirmando}
                                titulo={`¿Desactivar «${elegida.nombre}»?`}
                                descripcion="Dejará de aparecer para nuevas derivaciones. Sus registros se conservan y puedes volver a activarla."
                                confirmar="Desactivar"
                                peligro
                                cargando={procesando}
                                onConfirmar={() => cambiarEstado(elegida)}
                            />
                            <Dialog
                                abierto={destino !== null}
                                onCambiar={(abierto) => !abierto && setDestino(null)}
                                titulo={`Fusionar «${elegida.nombre}»`}
                                descripcion="Sus expedientes y áreas dependientes pasan al área elegida y esta queda inactiva. Sus responsables no se trasladan. Queda en la auditoría."
                                pie={
                                    <>
                                        <Button onClick={() => setDestino(null)}>Cancelar</Button>
                                        <Button variante="peligro" disabled={!destino} cargando={procesando} onClick={() => fusionar(elegida)}>
                                            Fusionar
                                        </Button>
                                    </>
                                }
                            >
                                <FormField etiqueta="Fusionar en" requerido>
                                    {(c) => (
                                        <Select
                                            {...c}
                                            vacia="Elige el área que la absorbe"
                                            opciones={opcionesArea.filter((o) => o.value !== elegida.id)}
                                            value={destino ?? ''}
                                            onChange={(e) => setDestino(e.target.value)}
                                        />
                                    )}
                                </FormField>
                            </Dialog>
                        </DetalleDialog>
                    )
                }
            />
        </>
    );
}
