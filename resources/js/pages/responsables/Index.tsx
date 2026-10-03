import { IcoAgregar, IcoEditar, IcoResponsables } from '@/components/ui/iconos';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { DetalleDialog } from '@/components/ui/DetalleDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import type { Opcion, Paginado, Responsable } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { area?: string; estado?: string };

const TIPOS = { titular: 'Titular', suplente: 'Suplente' };

const vigencia = (r: Responsable) => `${formatearFecha(r.vigente_desde)} – ${r.vigente_hasta ? formatearFecha(r.vigente_hasta) : 'sin fin'}`;

const columnas: Columna<Responsable>[] = [
    { clave: 'area', titulo: 'Área', celda: (r) => <span className="font-semibold">{r.area}</span> },
    {
        clave: 'usuario',
        titulo: 'Responsable',
        celda: (r) => (
            <div>
                <p>{r.usuario}</p>
                <p className="text-sm text-fg-muted">{r.email}</p>
            </div>
        ),
    },
    { clave: 'tipo', titulo: 'Tipo', ancho: '7rem', celda: (r) => TIPOS[r.tipo] },
    { clave: 'vigencia', titulo: 'Vigencia', celda: vigencia },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (r) => <ActivoBadge activo={r.vigente} /> },
];

type Props = { responsables: Paginado<Responsable>; filtros: Filtros; opcionesArea: Opcion<number>[] };

export default function ResponsablesIndex({ responsables, filtros: iniciales, opcionesArea, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    const elegido = responsables.data.find((r) => r.id === elegidoId) ?? null;

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/responsables')} />}
            <ListPage
                titulo="Responsables por área"
                descripcion="Titular y suplentes de cada área. Ven los expedientes de su área mientras su vigencia esté en curso."
                acciones={
                    <Link href={rutaModal('/responsables/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <IcoAgregar />
                        Asignar responsable
                    </Link>
                }
                filtros={
                    <>
                        <Select
                            aria-label="Vigencia"
                            className="w-36"
                            opciones={[
                                { value: 'vigentes', label: 'Vigentes' },
                                { value: 'todos', label: 'Todos' },
                            ]}
                            value={filtros.estado ?? 'vigentes'}
                            onChange={(e) => cambiar({ estado: e.target.value })}
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
                    titulo: 'Responsables por área',
                    columnas,
                    filas: responsables.data,
                    claveFila: (r) => r.id,
                    seleccionada: elegidoId,
                    onElegirFila: (r) => setElegidoId(r.id),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<IcoResponsables />}
                            titulo="No hay responsables que coincidan"
                            descripcion="Asigna un titular a cada área para que alguien atienda sus expedientes."
                        />
                    ),
                }}
                paginacion={responsables}
                detalle={
                    elegido && (
                        <DetalleDialog
                            abierto
                            onCambiar={(abierto) => !abierto && setElegidoId(null)}
                            titulo={elegido.usuario}
                            subtitulo={`${TIPOS[elegido.tipo]} de ${elegido.area}`}
                            acciones={
                                <Link href={rutaModal(`/responsables/${elegido.id}/edit`)} preserveScroll className={botonClases()}>
                                    <IcoEditar />
                                    Editar
                                </Link>
                            }
                        >
                            <DetalleLista
                                items={[
                                    { etiqueta: 'Correo', valor: elegido.email },
                                    { etiqueta: 'Vigencia', valor: vigencia(elegido) },
                                    { etiqueta: 'Estado', valor: <ActivoBadge activo={elegido.vigente} /> },
                                    { etiqueta: 'Última actualización', valor: formatearFechaHora(elegido.actualizado) },
                                ]}
                            />
                        </DetalleDialog>
                    )
                }
            />
        </>
    );
}
