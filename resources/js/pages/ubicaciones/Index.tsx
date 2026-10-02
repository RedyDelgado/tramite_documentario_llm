import { Add20Regular, Archive20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import type { Paginado, UbicacionFisica } from '@/types';

type Filtros = { q?: string; estado?: string };

const columnas: Columna<UbicacionFisica>[] = [
    {
        clave: 'nombre',
        titulo: 'Ubicación',
        celda: (u) => (
            <div>
                <p className="font-semibold">{u.nombre}</p>
                {u.descripcion && <p className="text-sm text-fg-muted">{u.descripcion}</p>}
            </div>
        ),
    },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (u) => <ActivoBadge activo={u.activa} femenino /> },
];

type Props = { ubicaciones: Paginado<UbicacionFisica>; filtros: Filtros };

export default function UbicacionesIndex({ ubicaciones, filtros: iniciales }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    return (
        <ListPage
            titulo="Ubicaciones físicas"
            descripcion="Archivadores, cajas o estantes donde se guardan los originales en papel, que nunca se descartan."
            acciones={
                <Link href="/ubicaciones/create" className={botonClases({ variante: 'primario' })}>
                    <Add20Regular />
                    Nueva ubicación
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
                        aria-label="Buscar"
                        placeholder="Buscar"
                        iconoInicio={<Search20Regular />}
                        className="w-64"
                        value={filtros.q ?? ''}
                        onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                    />
                </>
            }
            tabla={{
                titulo: 'Ubicaciones físicas',
                columnas,
                filas: ubicaciones.data,
                claveFila: (u) => u.id,
                onElegirFila: (u) => router.visit(`/ubicaciones/${u.id}/edit`),
                cargando,
                vacio: (
                    <EmptyState
                        icono={<Archive20Regular />}
                        titulo={hayFiltros ? 'No hay ubicaciones que coincidan con los filtros' : 'Aún no hay ubicaciones'}
                        descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Por ejemplo: Archivador 1 – 2026, Caja 3, Estante B.'}
                    />
                ),
            }}
            paginacion={ubicaciones}
        />
    );
}
