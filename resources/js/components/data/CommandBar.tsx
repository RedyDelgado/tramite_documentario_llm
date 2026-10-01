import type { ReactNode } from 'react';

type Props = { acciones?: ReactNode; filtros?: ReactNode };

/** Barra sobre cada tabla: acciones principales a la izquierda, filtros y búsqueda a la derecha (5.3). */
export function CommandBar({ acciones, filtros }: Props) {
    return (
        <div role="toolbar" className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-3 py-2">
            <div className="flex items-center gap-1">{acciones}</div>
            <div className="flex flex-wrap items-center gap-2">{filtros}</div>
        </div>
    );
}
