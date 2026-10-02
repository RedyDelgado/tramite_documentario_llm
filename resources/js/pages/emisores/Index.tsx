import { Add20Regular, BuildingGovernment20Regular, Edit20Regular, Merge20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { FormField } from '@/components/forms/FormField';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Dialog } from '@/components/ui/Dialog';
import { DetalleDialog } from '@/components/ui/DetalleDialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Emisor, Opcion, Paginado, ParDuplicado } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { q?: string; tipo?: string; estado?: string };

const TIPOS = { interno: 'Interno', externo: 'Externo' };

const columnas: Columna<Emisor>[] = [
    { clave: 'nombre', titulo: 'Nombre', celda: (e) => <span className="font-semibold">{e.nombre}</span> },
    { clave: 'tipo', titulo: 'Tipo', ancho: '8rem', celda: (e) => TIPOS[e.tipo] },
    { clave: 'expedientes', titulo: 'Expedientes', ancho: '8rem', celda: (e) => e.expedientes ?? 0 },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (e) => <ActivoBadge activo={e.activo} /> },
];

type Props = { emisores: Paginado<Emisor>; filtros: Filtros; duplicados: ParDuplicado[]; opcionesEmisor: Opcion<number>[] };

export default function EmisoresIndex({ emisores, filtros: iniciales, duplicados, opcionesEmisor, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    // Emisor que se fusiona y destino propuesto.
    const [fusion, setFusion] = useState<{ id: number; nombre: string; destino: string } | null>(null);
    const [procesando, setProcesando] = useState(false);
    const elegido = emisores.data.find((e) => e.id === elegidoId) ?? null;
    const hayFiltros = Boolean(filtros.q || filtros.tipo || filtros.estado);

    const fusionar = () =>
        fusion &&
        router.post(
            `/emisores/${fusion.id}/fusionar`,
            { destino_id: Number(fusion.destino) },
            {
                preserveScroll: true,
                onStart: () => setProcesando(true),
                onFinish: () => setProcesando(false),
                onSuccess: () => {
                    setFusion(null);
                    setElegidoId(null);
                },
            },
        );

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/emisores')} />}
            <ListPage
                titulo="Emisores"
                descripcion="Dependencias internas y externas que envían documentos. Un duplicado se fusiona: sus expedientes pasan al emisor que queda."
                acciones={
                    <Link href={rutaModal('/emisores/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <Add20Regular />
                        Nuevo emisor
                    </Link>
                }
                aviso={
                    duplicados.length > 0 && (
                        <Card titulo={`Posibles duplicados (${duplicados.length})`}>
                            <ul className="flex flex-col gap-2">
                                {duplicados.map(({ a, b }) => (
                                    <li key={`${a.id}-${b.id}`} className="flex flex-wrap items-center justify-between gap-2">
                                        <span>
                                            «{a.nombre}» y «{b.nombre}»
                                        </span>
                                        <Button icono={<Merge20Regular />} onClick={() => setFusion({ id: b.id, nombre: b.nombre, destino: String(a.id) })}>
                                            Revisar y fusionar
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )
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
                        <Select
                            aria-label="Tipo"
                            className="w-36"
                            vacia="Internos y externos"
                            opciones={[
                                { value: 'interno', label: 'Internos' },
                                { value: 'externo', label: 'Externos' },
                            ]}
                            value={filtros.tipo ?? ''}
                            onChange={(e) => cambiar({ tipo: e.target.value })}
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
                    titulo: 'Emisores',
                    columnas,
                    filas: emisores.data,
                    claveFila: (e) => e.id,
                    seleccionada: elegidoId,
                    onElegirFila: (e) => setElegidoId(e.id),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<BuildingGovernment20Regular />}
                            titulo={hayFiltros ? 'No hay emisores que coincidan con los filtros' : 'Aún no hay emisores'}
                            descripcion={hayFiltros ? 'Cambia la búsqueda, el tipo o el estado.' : 'También se crean al registrar un documento, sin salir del formulario.'}
                        />
                    ),
                }}
                paginacion={emisores}
                detalle={
                    <>
                        {elegido && (
                            <DetalleDialog
                                abierto
                                onCambiar={(abierto) => !abierto && setElegidoId(null)}
                                titulo={elegido.nombre}
                                subtitulo={<ActivoBadge activo={elegido.activo} />}
                                acciones={
                                    <>
                                        <Link href={rutaModal(`/emisores/${elegido.id}/edit`)} preserveScroll className={botonClases()}>
                                            <Edit20Regular />
                                            Editar
                                        </Link>
                                        <Button icono={<Merge20Regular />} onClick={() => setFusion({ id: elegido.id, nombre: elegido.nombre, destino: '' })}>
                                            Fusionar
                                        </Button>
                                    </>
                                }
                            >
                                <DetalleLista
                                    items={[
                                        { etiqueta: 'Tipo', valor: TIPOS[elegido.tipo] },
                                        { etiqueta: 'Expedientes', valor: elegido.expedientes ?? 0 },
                                        { etiqueta: 'Última actualización', valor: formatearFechaHora(elegido.actualizado) },
                                    ]}
                                />
                            </DetalleDialog>
                        )}
                        <Dialog
                            abierto={fusion !== null}
                            onCambiar={(abierto) => !abierto && setFusion(null)}
                            titulo={`Fusionar «${fusion?.nombre ?? ''}»`}
                            descripcion="Sus expedientes pasan al emisor elegido y este queda inactivo como duplicado. Queda en la auditoría."
                            pie={
                                <>
                                    <Button onClick={() => setFusion(null)}>Cancelar</Button>
                                    <Button variante="peligro" disabled={!fusion?.destino} cargando={procesando} onClick={fusionar}>
                                        Fusionar
                                    </Button>
                                </>
                            }
                        >
                            <FormField etiqueta="Fusionar en" requerido>
                                {(c) => (
                                    <Select
                                        {...c}
                                        vacia="Elige el emisor que queda"
                                        opciones={opcionesEmisor.filter((o) => o.value !== fusion?.id)}
                                        value={fusion?.destino ?? ''}
                                        onChange={(e) => fusion && setFusion({ ...fusion, destino: e.target.value })}
                                    />
                                )}
                            </FormField>
                        </Dialog>
                    </>
                }
            />
        </>
    );
}
