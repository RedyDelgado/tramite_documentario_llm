import type { ReactNode } from 'react';
import { CommandBar } from '@/components/data/CommandBar';
import { DataTable, type DataTableProps } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import type { Paginado } from '@/types';
import { AppShell } from './AppShell';
import { PageHeader } from './PageHeader';

type Props<T> = {
    titulo: string;
    descripcion?: string;
    acciones?: ReactNode;
    filtros?: ReactNode;
    tabla: DataTableProps<T>;
    paginacion?: Pick<Paginado<T>, 'links' | 'meta'>;
    // Detalle en modal: se abre al elegir una fila.
    detalle?: ReactNode;
    // Bloque sobre la lista que pide atención (p. ej. posibles duplicados).
    aviso?: ReactNode;
};

/** Plantilla de toda bandeja o catálogo: CommandBar + tabla con cabecera fija + paginación + detalle lateral. */
export function ListPage<T>({ titulo, descripcion, acciones, filtros, tabla, paginacion, detalle, aviso }: Props<T>) {
    return (
        <AppShell>
            <PageHeader titulo={titulo} descripcion={descripcion} />
            {aviso && <div className="mb-4">{aviso}</div>}
            <section className="overflow-hidden rounded-card border border-border bg-surface shadow-card">
                <CommandBar acciones={acciones} filtros={filtros} />
                <div className="max-h-[calc(100vh-16rem)] overflow-auto">
                    <DataTable {...tabla} />
                </div>
                {paginacion && <Pagination links={paginacion.links} meta={paginacion.meta} />}
            </section>
            {detalle}
        </AppShell>
    );
}
