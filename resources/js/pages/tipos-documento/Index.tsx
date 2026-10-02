import { Add20Regular, DocumentCopy20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Paginado, TipoDocumento } from '@/types';

type Filtros = { q?: string; estado?: string };

const columnas: Columna<TipoDocumento>[] = [
    { clave: 'nombre', titulo: 'Nombre', celda: (t) => <span className="font-semibold">{t.nombre}</span> },
    { clave: 'actualizado', titulo: 'Última actualización', celda: (t) => formatearFechaHora(t.actualizado) },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (t) => <ActivoBadge activo={t.activo} /> },
];

export default function TiposDocumentoIndex({ tipos, filtros: iniciales }: { tipos: Paginado<TipoDocumento>; filtros: Filtros }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    return (
        <ListPage
            titulo="Tipos de documento"
            descripcion="La forma del documento (oficio, carta, informe). La naturaleza y el plazo los da el tipo de trámite."
            acciones={
                <Link href="/tipos-documento/create" className={botonClases({ variante: 'primario' })}>
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
                titulo: 'Tipos de documento',
                columnas,
                filas: tipos.data,
                claveFila: (t) => t.id,
                onElegirFila: (t) => router.visit(`/tipos-documento/${t.id}/edit`),
                cargando,
                vacio: (
                    <EmptyState
                        icono={<DocumentCopy20Regular />}
                        titulo={hayFiltros ? 'No hay tipos que coincidan con los filtros' : 'Aún no hay tipos de documento'}
                        descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Por ejemplo: oficio, oficio circular, oficio múltiple, carta circular, informe, solicitud.'}
                    />
                ),
            }}
            paginacion={tipos}
        />
    );
}
