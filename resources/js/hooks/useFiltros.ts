import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

type Valores = Record<string, string | null | undefined>;

/**
 * Filtros de una lista sincronizados con la URL: cada cambio recarga la página con Inertia
 * conservando estado y scroll. El texto libre (`diferido`) espera 300 ms para no consultar en cada tecla.
 */
export function useFiltros<F extends Valores>(iniciales: F) {
    const [filtros, setFiltros] = useState<F>(iniciales);
    const [cargando, setCargando] = useState(false);
    const espera = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(espera.current), []);

    const visitar = (valores: F) => {
        const limpios = Object.fromEntries(Object.entries(valores).filter(([, v]) => v != null && v !== ''));
        router.get(window.location.pathname, limpios, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => setCargando(true),
            onFinish: () => setCargando(false),
        });
    };

    const cambiar = (parcial: Partial<F>, { diferido = false } = {}) => {
        const nuevos = { ...filtros, ...parcial };
        setFiltros(nuevos);
        clearTimeout(espera.current);
        if (diferido) {
            espera.current = setTimeout(() => visitar(nuevos), 300);
        } else {
            visitar(nuevos);
        }
    };

    return { filtros, cambiar, cargando };
}
