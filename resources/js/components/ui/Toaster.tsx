import { IcoAlerta, IcoCerrar16, IcoCorrecto, IcoInfo } from '@/components/ui/iconos';
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
    ok: <IcoCorrecto className="shrink-0 text-ok" />,
    error: <IcoAlerta className="shrink-0 text-danger" />,
    info: <IcoInfo className="shrink-0 text-primary-600" />,
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
                    className="flex items-start gap-2.5 rounded-card bg-material px-4 py-3 shadow-flotante backdrop-blur-xl motion-safe:animate-[hoja_220ms_cubic-bezier(0.2,0.9,0.3,1)]"
                >
                    {ICONOS[a.tipo]}
                    <T.Title className="flex-1 text-base text-fg">{a.mensaje}</T.Title>
                    <T.Close aria-label="Cerrar aviso" className="cursor-pointer rounded-full p-0.5 text-fg-muted hover:bg-relleno">
                        <IcoCerrar16 />
                    </T.Close>
                </T.Root>
            ))}
            <T.Viewport className="fixed top-16 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2 outline-none" />
        </T.Provider>
    );
}
