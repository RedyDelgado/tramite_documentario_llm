import { IcoFlechaDerecha16, IcoFlechaIzquierda16 } from '@/components/ui/iconos';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { PaginacionLocal } from '@/hooks/useListaLocal';
import type { Paginado } from '@/types';
import { botonClases } from '@/components/ui/Button';

/** Paginada en el servidor (listas que crecen sin límite) o en el navegador (catálogos ya cargados, useListaLocal). */
export type PaginacionProps = Pick<Paginado<unknown>, 'links' | 'meta'> | PaginacionLocal;

const clases = botonClases({ variante: 'secundario', tamano: 'icono-sm' });

// Un paso es un enlace (servidor), una acción (navegador) o nada (primera o última página).
function Paso({ destino, etiqueta, icono }: { destino: string | (() => void) | null; etiqueta: string; icono: ReactNode }) {
    if (typeof destino === 'string') {
        return (
            <Link href={destino} preserveScroll preserveState aria-label={etiqueta} className={clases}>
                {icono}
            </Link>
        );
    }

    return (
        <button type="button" disabled={!destino} onClick={destino ?? undefined} aria-label={etiqueta} className={clases}>
            {icono}
        </button>
    );
}

/** Pie de toda lista: «1–10 de 37» y página anterior y siguiente. */
export function Pagination(props: PaginacionProps) {
    const p =
        'meta' in props
            ? {
                  desde: props.meta.from ?? 0,
                  hasta: props.meta.to ?? 0,
                  total: props.meta.total,
                  pagina: props.meta.current_page,
                  ultima: props.meta.last_page,
                  anterior: props.links.prev,
                  siguiente: props.links.next,
              }
            : (() => {
                  const ultima = Math.max(1, Math.ceil(props.total / props.porPagina));

                  return {
                      desde: props.total ? (props.pagina - 1) * props.porPagina + 1 : 0,
                      hasta: Math.min(props.pagina * props.porPagina, props.total),
                      total: props.total,
                      pagina: props.pagina,
                      ultima,
                      anterior: props.pagina > 1 ? () => props.onPagina(props.pagina - 1) : null,
                      siguiente: props.pagina < ultima ? () => props.onPagina(props.pagina + 1) : null,
                  };
              })();

    if (p.total === 0) return null;

    return (
        <nav aria-label="Paginación" className="flex items-center justify-between gap-2 border-t border-separador px-4 py-3 text-sm text-fg-muted">
            <span>
                {p.desde}–{p.hasta} de {p.total}
            </span>
            <div className="flex items-center gap-1">
                <Paso destino={p.anterior} etiqueta="Página anterior" icono={<IcoFlechaIzquierda16 />} />
                <span className="px-2">
                    Página {p.pagina} de {p.ultima}
                </span>
                <Paso destino={p.siguiente} etiqueta="Página siguiente" icono={<IcoFlechaDerecha16 />} />
            </div>
        </nav>
    );
}
