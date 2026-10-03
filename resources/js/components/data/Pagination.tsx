import { IcoFlechaDerecha16, IcoFlechaIzquierda16 } from '@/components/ui/iconos';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { Paginado } from '@/types';
import { botonClases } from '@/components/ui/Button';

type Props = Pick<Paginado<unknown>, 'links' | 'meta'>;

const clases = botonClases({ variante: 'secundario', tamano: 'icono-sm' });

function Paso({ href, etiqueta, icono }: { href: string | null; etiqueta: string; icono: ReactNode }) {
    if (!href) {
        return (
            <button type="button" disabled aria-label={etiqueta} className={clases}>
                {icono}
            </button>
        );
    }

    return (
        <Link href={href} preserveScroll preserveState aria-label={etiqueta} className={clases}>
            {icono}
        </Link>
    );
}

export function Pagination({ links, meta }: Props) {
    if (meta.total === 0) return null;

    return (
        <nav aria-label="Paginación" className="flex items-center justify-between gap-2 border-t border-separador px-4 py-3 text-sm text-fg-muted">
            <span>
                {meta.from}–{meta.to} de {meta.total}
            </span>
            <div className="flex items-center gap-1">
                <Paso href={links.prev} etiqueta="Página anterior" icono={<IcoFlechaIzquierda16 />} />
                <span className="px-2">
                    Página {meta.current_page} de {meta.last_page}
                </span>
                <Paso href={links.next} etiqueta="Página siguiente" icono={<IcoFlechaDerecha16 />} />
            </div>
        </nav>
    );
}
