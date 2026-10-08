import { useMemo, useState } from 'react';

/** Filas por página en todas las listas (decisión del usuario). */
export const POR_PAGINA = 10;

/** «Acuña» y «acuna» son lo mismo al buscar: sin tildes ni mayúsculas. */
export const normalizar = (texto: string | null | undefined) =>
    (texto ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

/** Cada palabra buscada aparece en alguno de los campos. */
export function coincide(q: string | undefined, ...campos: (string | null | undefined)[]): boolean {
    const palabras = normalizar(q).split(/\s+/).filter(Boolean);
    if (palabras.length === 0) return true;
    const texto = normalizar(campos.join(' '));

    return palabras.every((p) => texto.includes(p));
}

export type PaginacionLocal = { pagina: number; total: number; porPagina: number; onPagina: (pagina: number) => void };

type Orden = { clave: string; dir: 'asc' | 'desc' };
type Valor = string | number | boolean | null | undefined;

type Opciones<T, F> = {
    filtros?: F;
    filtrar?: (fila: T, filtros: F) => boolean;
    // Valor por el que se ordena cada columna ordenable.
    ordenes?: Record<string, (fila: T) => Valor>;
    orden?: Orden;
};

/**
 * Catálogo completo ya cargado: buscar, filtrar, ordenar y paginar ocurre en el navegador, sin volver al servidor.
 * Solo para tablas chicas (áreas, usuarios, tipos…); lo que crece sin límite (expedientes) se busca en el servidor.
 */
export function useListaLocal<T, F extends Record<string, string | undefined> = Record<string, string | undefined>>(todas: T[], opciones: Opciones<T, F> = {}) {
    const [filtros, setFiltros] = useState<F>(opciones.filtros ?? ({} as F));
    const [orden, setOrden] = useState<Orden | undefined>(opciones.orden);
    const [pagina, setPagina] = useState(1);
    const { filtrar, ordenes } = opciones;

    const filtradas = useMemo(() => {
        const lista = filtrar ? todas.filter((f) => filtrar(f, filtros)) : [...todas];
        const valor = orden && ordenes?.[orden.clave];
        if (valor) {
            const signo = orden.dir === 'desc' ? -1 : 1;
            lista.sort((a, b) => {
                const x = valor(a);
                const y = valor(b);
                if (typeof x === 'number' && typeof y === 'number') return (x - y) * signo;
                return String(x ?? '').localeCompare(String(y ?? ''), 'es', { numeric: true }) * signo;
            });
        }

        return lista;
    }, [todas, filtros, orden, filtrar, ordenes]);

    const ultima = Math.max(1, Math.ceil(filtradas.length / POR_PAGINA));
    const actual = Math.min(pagina, ultima);

    return {
        filtros,
        // Cambiar un filtro vuelve a la primera página.
        cambiar: (parcial: Partial<F>) => {
            setFiltros((f) => ({ ...f, ...parcial }));
            setPagina(1);
        },
        orden,
        ordenar: (clave: string) => setOrden((o) => ({ clave, dir: o?.clave === clave && o.dir === 'asc' ? 'desc' : 'asc' })),
        filas: filtradas.slice((actual - 1) * POR_PAGINA, actual * POR_PAGINA),
        paginacion: { pagina: actual, total: filtradas.length, porPagina: POR_PAGINA, onPagina: setPagina } satisfies PaginacionLocal,
    };
}
