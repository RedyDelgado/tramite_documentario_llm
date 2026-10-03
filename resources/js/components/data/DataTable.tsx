import { IcoOrdenAsc16, IcoOrdenDesc16, IcoOrdenar16 } from '@/components/ui/iconos';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type Columna<T> = {
    clave: string;
    titulo: string;
    celda: (fila: T) => ReactNode;
    ordenable?: boolean;
    alinear?: 'izquierda' | 'derecha' | 'centro';
    ancho?: string;
    // Fechas, códigos: en una sola línea aunque la columna sea angosta.
    sinCorte?: boolean;
};

export type Orden = { clave: string; dir: 'asc' | 'desc' };

export type DataTableProps<T> = {
    // Nombre accesible de la tabla (se lee como caption).
    titulo: string;
    columnas: Columna<T>[];
    filas: T[];
    claveFila: (fila: T) => string | number;
    orden?: Orden;
    onOrdenar?: (clave: string) => void;
    seleccionada?: string | number | null;
    onElegirFila?: (fila: T) => void;
    cargando?: boolean;
    vacio?: ReactNode;
};

const ALINEAR = { izquierda: 'text-left', derecha: 'text-right', centro: 'text-center' };

export function DataTable<T>({
    titulo,
    columnas,
    filas,
    claveFila,
    orden,
    onOrdenar,
    seleccionada,
    onElegirFila,
    cargando = false,
    vacio,
}: DataTableProps<T>) {
    const alTeclearFila = (e: KeyboardEvent<HTMLTableRowElement>, fila: T) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onElegirFila?.(fila);
        }
    };

    return (
        <table aria-busy={cargando || undefined} className={cn('w-full border-collapse text-base transition-opacity', cargando && 'opacity-60')}>
            <caption className="sr-only">{titulo}</caption>
            <thead>
                <tr>
                    {columnas.map((c) => {
                        const activa = orden?.clave === c.clave;
                        const ariaSort = activa ? (orden.dir === 'asc' ? 'ascending' : 'descending') : undefined;

                        return (
                            <th
                                key={c.clave}
                                scope="col"
                                aria-sort={ariaSort}
                                style={c.ancho ? { width: c.ancho } : undefined}
                                className={cn(
                                    'sticky top-0 z-10 h-10 border-b border-separador bg-material px-4 text-sm font-medium text-fg-muted backdrop-blur-xl',
                                    ALINEAR[c.alinear ?? 'izquierda'],
                                )}
                            >
                                {c.ordenable && onOrdenar ? (
                                    <button
                                        type="button"
                                        onClick={() => onOrdenar(c.clave)}
                                        className={cn('inline-flex cursor-pointer items-center gap-1 rounded-control hover:text-fg', activa && 'text-fg')}
                                    >
                                        {c.titulo}
                                        {activa ? orden.dir === 'asc' ? <IcoOrdenAsc16 /> : <IcoOrdenDesc16 /> : <IcoOrdenar16 className="opacity-50" />}
                                    </button>
                                ) : (
                                    c.titulo
                                )}
                            </th>
                        );
                    })}
                </tr>
            </thead>
            <tbody>
                {filas.length === 0 && (
                    <tr>
                        <td colSpan={columnas.length}>
                            {vacio ?? <p className="px-4 py-10 text-center text-fg-muted">No hay registros.</p>}
                        </td>
                    </tr>
                )}
                {filas.map((fila) => {
                    const clave = claveFila(fila);
                    const esSeleccionada = seleccionada === clave;

                    return (
                        <tr
                            key={clave}
                            tabIndex={onElegirFila ? 0 : undefined}
                            aria-selected={onElegirFila ? esSeleccionada : undefined}
                            onClick={onElegirFila ? () => onElegirFila(fila) : undefined}
                            onKeyDown={onElegirFila ? (e) => alTeclearFila(e, fila) : undefined}
                            className={cn(
                                onElegirFila && 'cursor-pointer',
                                'group transition-colors',
                                esSeleccionada ? 'bg-primary-50' : cn('bg-surface', onElegirFila && 'hover:bg-surface-subtle'),
                            )}
                        >
                            {columnas.map((c) => (
                                <td key={c.clave} className={cn('h-11 border-b border-separador px-4 py-2 align-middle text-fg group-last:border-b-0', c.sinCorte && 'whitespace-nowrap', ALINEAR[c.alinear ?? 'izquierda'])}>
                                    {c.celda(fila)}
                                </td>
                            ))}
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}
