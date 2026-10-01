import { CheckmarkCircle20Filled, Dismiss16Regular, ErrorCircle20Filled, Info20Filled } from '@fluentui/react-icons';
import { usePage } from '@inertiajs/react';
import { Toast as T } from 'radix-ui';
import { useEffect, useState } from 'react';
import type { Toast } from '@/types';

const oyentes = new Set<(t: Toast) => void>();

/** Muestra un aviso desde cualquier parte; los flash de Laravel (`Inertia::flash('toast', ...)`) llegan solos. */
export function avisar(toast: Toast) {
    oyentes.forEach((oyente) => oyente(toast));
}

const ICONOS = {
    ok: <CheckmarkCircle20Filled className="shrink-0 text-ok" />,
    error: <ErrorCircle20Filled className="shrink-0 text-danger" />,
    info: <Info20Filled className="shrink-0 text-primary-600" />,
};

let siguienteId = 0;

export function Toaster() {
    const [avisos, setAvisos] = useState<Array<Toast & { id: number }>>([]);
    const { flash } = usePage();

    useEffect(() => {
        const oyente = (t: Toast) => setAvisos((previos) => [...previos, { ...t, id: ++siguienteId }]);
        oyentes.add(oyente);
        return () => {
            oyentes.delete(oyente);
        };
    }, []);

    useEffect(() => {
        if (flash.toast) avisar(flash.toast);
    }, [flash]);

    return (
        <T.Provider duration={5000} label="Notificación">
            {avisos.map((a) => (
                <T.Root
                    key={a.id}
                    duration={a.tipo === 'error' ? 10000 : undefined}
                    onOpenChange={(abierto) => !abierto && setAvisos((previos) => previos.filter((p) => p.id !== a.id))}
                    className="flex items-start gap-2 rounded-card border border-border bg-surface px-3 py-2.5 shadow-card"
                >
                    {ICONOS[a.tipo]}
                    <T.Title className="flex-1 text-base text-fg">{a.mensaje}</T.Title>
                    <T.Close aria-label="Cerrar aviso" className="cursor-pointer rounded-control p-0.5 text-fg-muted hover:bg-surface-subtle">
                        <Dismiss16Regular />
                    </T.Close>
                </T.Root>
            ))}
            <T.Viewport className="fixed right-4 bottom-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2 outline-none" />
        </T.Provider>
    );
}
