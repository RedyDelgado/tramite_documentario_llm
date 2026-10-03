import { IcoAgregar, IcoBuscar, IcoInstrucciones } from '@/components/ui/iconos';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import type { InstruccionFrecuente, Paginado } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { q?: string; estado?: string };

const columnas: Columna<InstruccionFrecuente>[] = [
    { clave: 'orden', titulo: 'Orden', ancho: '6rem', celda: (i) => i.orden },
    { clave: 'texto', titulo: 'Instrucción', celda: (i) => <span className="font-semibold">{i.texto}</span> },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (i) => <ActivoBadge activo={i.activa} femenino /> },
];

type Props = { instrucciones: Paginado<InstruccionFrecuente>; filtros: Filtros };

export default function InstruccionesIndex({ instrucciones, filtros: iniciales, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/instrucciones')} />}
            <ListPage
                titulo="Instrucciones frecuentes"
                descripcion="Se eligen de una lista al derivar, en el orden indicado, en vez de escribirlas cada vez."
                acciones={
                    <Link href={rutaModal('/instrucciones/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <IcoAgregar />
                        Nueva instrucción
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
                            onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Instrucciones frecuentes',
                    columnas,
                    filas: instrucciones.data,
                    claveFila: (i) => i.id,
                    onElegirFila: (i) => router.visit(rutaModal(`/instrucciones/${i.id}/edit`), { preserveScroll: true }),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<IcoInstrucciones />}
                            titulo={hayFiltros ? 'No hay instrucciones que coincidan con los filtros' : 'Aún no hay instrucciones'}
                            descripcion={hayFiltros ? 'Cambia la búsqueda o el estado.' : 'Por ejemplo: para conocimiento, atender, presentar información.'}
                        />
                    ),
                }}
                paginacion={instrucciones}
            />
        </>
    );
}
