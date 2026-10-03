import { NumberSymbol20Regular, Settings20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { useFiltros } from '@/hooks/useFiltros';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

/** NumeracionService::lista. */
type Correlativo = { clave: string; nombre: string; ultimo_usado: number | null; siguiente: number; ejemplo: string; tipo_documento_id: number | null; area_id: number | null };

type Filtros = { anio?: string };

const columnas: Columna<Correlativo>[] = [
    { clave: 'nombre', titulo: 'Correlativo', celda: (c) => <span className="font-semibold">{c.nombre}</span> },
    { clave: 'ultimo_usado', titulo: 'Último usado', alinear: 'derecha', ancho: '8rem', celda: (c) => c.ultimo_usado ?? <span className="text-fg-muted">—</span> },
    { clave: 'siguiente', titulo: 'Siguiente', alinear: 'derecha', ancho: '7rem', celda: (c) => c.siguiente },
    { clave: 'ejemplo', titulo: 'Así saldrá', celda: (c) => c.ejemplo },
];

type Props = { correlativos: Correlativo[]; filtros: Filtros; formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> };

// Ajustar un correlativo de la lista abre el formulario con él ya elegido.
const ajustar = (c: Correlativo, anio: string) => {
    const datos = c.tipo_documento_id
        ? { tipo: 'saliente', tipo_documento_id: String(c.tipo_documento_id), area_id: String(c.area_id), anio, siguiente: String(c.siguiente) }
        : { tipo: 'registro', anio, siguiente: String(c.siguiente) };
    router.get('/numeracion/ajustar', { ...datos }, { preserveScroll: true });
};

export default function NumeracionIndex({ correlativos, filtros: iniciales, formulario }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const anio = filtros.anio ?? String(new Date().getFullYear());

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal(`/numeracion?anio=${anio}`)} />}
            <ListPage
                titulo="Numeración"
                descripcion="Con qué número continúa cada correlativo. Los documentos emitidos llevan uno por tipo y área; aparecen aquí al emitir el primero o al ajustarlo."
                acciones={
                    <Link href={rutaModal('/numeracion/ajustar')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <Settings20Regular />
                        Ajustar numeración
                    </Link>
                }
                filtros={
                    <Input
                        type="number"
                        aria-label="Año"
                        className="w-24"
                        min={2000}
                        max={2100}
                        value={filtros.anio ?? ''}
                        onChange={(e) => cambiar({ anio: e.target.value }, { diferido: true })}
                    />
                }
                tabla={{
                    titulo: 'Correlativos',
                    columnas,
                    filas: correlativos,
                    claveFila: (c) => c.clave,
                    onElegirFila: (c) => ajustar(c, anio),
                    cargando,
                    vacio: <EmptyState icono={<NumberSymbol20Regular />} titulo="Sin correlativos" />,
                }}
            />
        </>
    );
}
