import { Add20Regular, Edit20Regular, Organization20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ListaEtiquetas } from '@/components/data/ListaEtiquetas';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Drawer } from '@/components/ui/Drawer';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Area, Paginado } from '@/types';

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

export default function AreasIndex({ areas, filtros: iniciales }: { areas: Paginado<Area>; filtros: Filtros }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidaId, setElegidaId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
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

    return (
        <ListPage
            titulo="Áreas"
            descripcion="Áreas de la institución, su jerarquía y las palabras clave que orientan la derivación."
            acciones={
                <Link href="/areas/create" className={botonClases({ variante: 'primario' })}>
                    <Add20Regular />
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
                        iconoInicio={<Search20Regular />}
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
                        icono={<Organization20Regular />}
                        titulo={hayFiltros ? 'No hay áreas que coincidan con los filtros' : 'Aún no hay áreas'}
                        descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Crea la primera área para empezar a derivar expedientes.'}
                    />
                ),
            }}
            paginacion={areas}
            detalle={
                elegida && (
                    <Drawer
                        abierto
                        onCambiar={(abierto) => !abierto && setElegidaId(null)}
                        titulo={elegida.nombre}
                        subtitulo={<ActivoBadge activo={elegida.activa} femenino />}
                        acciones={
                            <>
                                <Link href={`/areas/${elegida.id}/edit`} className={botonClases()}>
                                    <Edit20Regular />
                                    Editar
                                </Link>
                                {elegida.activa ? (
                                    <Button onClick={() => setConfirmando(true)}>Desactivar</Button>
                                ) : (
                                    <Button cargando={procesando} onClick={() => cambiarEstado(elegida)}>
                                        Activar
                                    </Button>
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
                    </Drawer>
                )
            }
        />
    );
}
