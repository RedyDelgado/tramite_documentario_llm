import { useEffect, useState } from 'react';

const CADA = 30_000;

/** Contador del chat: parte de lo que trajo la página y se consulta cada 30 s sin recargarla. */
export function useMensajesSinLeer(inicial: number): number {
    const [total, setTotal] = useState(inicial);

    // Cada visita de Inertia trae el valor fresco: manda sobre la última consulta.
    useEffect(() => setTotal(inicial), [inicial]);

    useEffect(() => {
        const consultar = async () => {
            // Con la pestaña oculta no hay a quién avisar; al volver, la siguiente vuelta lo pone al día.
            if (document.hidden) return;
            try {
                const r = await fetch('/mensajes/sin-leer', { headers: { Accept: 'application/json' } });
                if (r.ok) setTotal(((await r.json()) as { total: number }).total);
            } catch {
                // Sin red se queda el último valor conocido.
            }
        };
        const intervalo = setInterval(() => void consultar(), CADA);

        return () => clearInterval(intervalo);
    }, []);

    return total;
}
