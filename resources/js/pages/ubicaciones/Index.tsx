import { IcoAgregar, IcoArchivo, IcoBuscar } from '@/components/ui/iconos';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { coincide, useListaLocal } from '@/hooks/useListaLocal';
import type { UbicacionFisica } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { q?: string; estado?: string };

const filtrar = (x: UbicacionFisica, f: Filtros) => coincide(f.q, x.nombre, x.descripcion) && (!f.estado || x.activa === (f.estado === 'activas'));

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

type Props = { ubicaciones: UbicacionFisica[] };

export default function UbicacionesIndex({ ubicaciones, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, filas, paginacion } = useListaLocal<UbicacionFisica, Filtros>(ubicaciones, { filtrar });
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/ubicaciones')} />}
            <ListPage
                titulo="Ubicaciones físicas"
                descripcion="Archivadores, cajas o estantes donde se guardan los originales en papel, que nunca se descartan."
                acciones={
                    <Link href={rutaModal('/ubicaciones/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <IcoAgregar />
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
                            iconoInicio={<IcoBuscar />}
                            className="w-64"
                            value={filtros.q ?? ''}
                            onChange={(e) => cambiar({ q: e.target.value })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Ubicaciones físicas',
                    columnas,
                    filas,
                    claveFila: (u) => u.id,
                    onElegirFila: (u) => router.visit(rutaModal(`/ubicaciones/${u.id}/edit`), { preserveScroll: true }),
                    vacio: (
                        <EmptyState
                            icono={<IcoArchivo />}
                            titulo={hayFiltros ? 'No hay ubicaciones que coincidan con los filtros' : 'Aún no hay ubicaciones'}
                            descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Por ejemplo: Archivador 1 – 2026, Caja 3, Estante B.'}
                        />
                    ),
                }}
                paginacion={paginacion}
            />
        </>
    );
}
