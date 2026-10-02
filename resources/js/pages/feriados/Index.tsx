import { Add20Regular, CalendarCancel20Regular, Delete20Regular, Edit20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { DetalleDialog } from '@/components/ui/DetalleDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import type { Feriado, Opcion, Paginado } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { anio?: string; area?: string };

const alcance = (f: Feriado) => f.area ?? 'Toda la institución';

const columnas: Columna<Feriado>[] = [
    { clave: 'fecha', titulo: 'Fecha', ancho: '12rem', celda: (f) => <span className="font-semibold">{formatearFecha(f.fecha)}</span> },
    { clave: 'descripcion', titulo: 'Descripción', celda: (f) => f.descripcion },
    { clave: 'alcance', titulo: 'Alcance', celda: alcance },
];

type Props = { feriados: Paginado<Feriado>; filtros: Filtros; opcionesArea: Opcion<number>[] };

export default function FeriadosIndex({ feriados, filtros: iniciales, opcionesArea, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const elegido = feriados.data.find((f) => f.id === elegidoId) ?? null;

    const quitar = (feriado: Feriado) =>
        router.delete(`/feriados/${feriado.id}`, {
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
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/feriados')} />}
            <ListPage
                titulo="Feriados"
                descripcion="Días no hábiles para el cálculo de plazos, además de sábados y domingos."
                acciones={
                    <Link href={rutaModal('/feriados/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <Add20Regular />
                        Nuevo feriado
                    </Link>
                }
                filtros={
                    <>
                        <Input
                            type="number"
                            aria-label="Año"
                            className="w-24"
                            min={2000}
                            max={2100}
                            value={filtros.anio ?? ''}
                            onChange={(e) => cambiar({ anio: e.target.value }, { diferido: true })}
                        />
                        <Select
                            aria-label="Área"
                            className="w-56"
                            vacia="Todos los alcances"
                            opciones={opcionesArea}
                            value={filtros.area ?? ''}
                            onChange={(e) => cambiar({ area: e.target.value })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Feriados',
                    columnas,
                    filas: feriados.data,
                    claveFila: (f) => f.id,
                    seleccionada: elegidoId,
                    onElegirFila: (f) => setElegidoId(f.id),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<CalendarCancel20Regular />}
                            titulo={`No hay feriados registrados en ${filtros.anio ?? 'este año'}`}
                            descripcion="Registra los feriados nacionales y los días no laborables de la institución."
                        />
                    ),
                }}
                paginacion={feriados}
                detalle={
                    elegido && (
                        <DetalleDialog
                            abierto
                            onCambiar={(abierto) => !abierto && setElegidoId(null)}
                            titulo={elegido.descripcion}
                            subtitulo={formatearFecha(elegido.fecha)}
                            acciones={
                                <>
                                    <Link href={rutaModal(`/feriados/${elegido.id}/edit`)} preserveScroll className={botonClases()}>
                                        <Edit20Regular />
                                        Editar
                                    </Link>
                                    <Button icono={<Delete20Regular />} onClick={() => setConfirmando(true)}>
                                        Quitar
                                    </Button>
                                </>
                            }
                        >
                            <DetalleLista
                                items={[
                                    { etiqueta: 'Alcance', valor: alcance(elegido) },
                                    { etiqueta: 'Última actualización', valor: formatearFechaHora(elegido.actualizado) },
                                ]}
                            />
                            <ConfirmDialog
                                abierto={confirmando}
                                onCambiar={setConfirmando}
                                titulo={`¿Quitar «${elegido.descripcion}»?`}
                                descripcion="El día vuelve a contarse como hábil para los nuevos plazos. Los expedientes ya ingresados conservan su fecha límite."
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
