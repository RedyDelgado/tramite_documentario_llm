import { IcoAgregar, IcoEditar, IcoEliminar, IcoPlazo } from '@/components/ui/iconos';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DetalleDialog } from '@/components/ui/DetalleDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import { useListaLocal } from '@/hooks/useListaLocal';
import { formatearFechaHora, formatearPlazo } from '@/lib/fechas';
import type { Opcion, PlazoArea } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { tipo?: string; area?: string };

const filtrar = (x: PlazoArea, f: Filtros) => (!f.tipo || String(x.tipo_tramite_id) === f.tipo) && (!f.area || String(x.area_id) === f.area);

const columnas: Columna<PlazoArea>[] = [
    { clave: 'tipo', titulo: 'Tipo de trámite', celda: (p) => <span className="font-semibold">{p.tipo}</span> },
    { clave: 'area', titulo: 'Área', celda: (p) => p.area },
    { clave: 'plazo', titulo: 'Plazo en el área', celda: (p) => formatearPlazo(p.plazo_dias, p.tipo_dias) },
    { clave: 'base', titulo: 'Plazo del tipo', celda: (p) => <span className="text-fg-muted">{formatearPlazo(p.plazo_del_tipo, p.tipo_dias)}</span> },
];

type Props = { plazos: PlazoArea[]; opcionesTipo: Opcion<number>[]; opcionesArea: Opcion<number>[] };

export default function PlazosIndex({ plazos, opcionesTipo, opcionesArea, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, filas, paginacion } = useListaLocal<PlazoArea, Filtros>(plazos, { filtrar });
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const elegido = plazos.find((p) => p.id === elegidoId) ?? null;
    const hayFiltros = Boolean(filtros.tipo || filtros.area);

    const quitar = (plazo: PlazoArea) =>
        router.delete(`/plazos/${plazo.id}`, {
            preserveScroll: true,
            onStart: () => setProcesando(true),
            onFinish: () => setProcesando(false),
            onSuccess: () => {
                setConfirmando(false);
                setElegidoId(null);
            },
        });

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/plazos')} />}
            <ListPage
                titulo="Plazos por área"
                descripcion="Un tipo de trámite puede tener otro plazo en un área concreta; si no hay uno aquí, se usa el del tipo."
                acciones={
                    <Link href={rutaModal('/plazos/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <IcoAgregar />
                        Nuevo plazo por área
                    </Link>
                }
                filtros={
                    <>
                        <Select
                            aria-label="Tipo de trámite"
                            className="w-56"
                            vacia="Todos los tipos"
                            opciones={opcionesTipo}
                            value={filtros.tipo ?? ''}
                            onChange={(e) => cambiar({ tipo: e.target.value })}
                        />
                        <Select
                            aria-label="Área"
                            className="w-56"
                            vacia="Todas las áreas"
                            opciones={opcionesArea}
                            value={filtros.area ?? ''}
                            onChange={(e) => cambiar({ area: e.target.value })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Plazos por área',
                    columnas,
                    filas,
                    claveFila: (p) => p.id,
                    seleccionada: elegidoId,
                    onElegirFila: (p) => setElegidoId(p.id),
                    vacio: (
                        <EmptyState
                            icono={<IcoPlazo />}
                            titulo={hayFiltros ? 'No hay plazos que coincidan con los filtros' : 'Ningún área tiene un plazo propio'}
                            descripcion={hayFiltros ? 'Cambia el tipo o el área.' : 'Todas usan el plazo de cada tipo de trámite.'}
                        />
                    ),
                }}
                paginacion={paginacion}
                detalle={
                    elegido && (
                        <DetalleDialog
                            abierto
                            onCambiar={(abierto) => !abierto && setElegidoId(null)}
                            titulo={elegido.tipo}
                            subtitulo={elegido.area}
                            acciones={
                                <>
                                    <Link href={rutaModal(`/plazos/${elegido.id}/edit`)} preserveScroll className={botonClases()}>
                                        <IcoEditar />
                                        Editar
                                    </Link>
                                    <Button icono={<IcoEliminar />} onClick={() => setConfirmando(true)}>
                                        Quitar
                                    </Button>
                                </>
                            }
                        >
                            <DetalleLista
                                items={[
                                    { etiqueta: 'Plazo en el área', valor: formatearPlazo(elegido.plazo_dias, elegido.tipo_dias) },
                                    { etiqueta: 'Plazo del tipo', valor: formatearPlazo(elegido.plazo_del_tipo, elegido.tipo_dias) },
                                    { etiqueta: 'Última actualización', valor: formatearFechaHora(elegido.actualizado) },
                                ]}
                            />
                            <ConfirmDialog
                                abierto={confirmando}
                                onCambiar={setConfirmando}
                                titulo="¿Quitar el plazo propio del área?"
                                descripcion="El área volverá a usar el plazo del tipo para los nuevos expedientes. Los ya ingresados conservan su plazo."
                                confirmar="Quitar"
                                peligro
                                cargando={procesando}
                                onConfirmar={() => quitar(elegido)}
                            />
                        </DetalleDialog>
                    )
                }
            />
        </>
    );
}
