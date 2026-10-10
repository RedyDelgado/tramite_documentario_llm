import { IcoCerrar16 } from '@/components/ui/iconos';
import { Dialog as D } from 'radix-ui';
import { useEffect, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { IconButton } from './IconButton';

// Ancho según el contenido: un formulario corto, uno largo o un detalle con mucha información.
const ANCHO = {
    md: 'w-[min(32rem,calc(100vw-2rem))]',
    lg: 'w-[min(48rem,calc(100vw-2rem))]',
    xl: 'w-[min(76rem,calc(100vw-2rem))]',
} as const;

type Props = {
    abierto: boolean;
    onCambiar: (abierto: boolean) => void;
    titulo: string;
    descripcion?: ReactNode;
    // Acciones junto al título (p. ej. en un detalle).
    acciones?: ReactNode;
    children?: ReactNode;
    pie?: ReactNode;
    tamano?: keyof typeof ANCHO;
    // Mientras el sistema lee o la IA trabaja: cubre toda la pantalla con este aviso y no deja cerrar.
    ocupado?: ReactNode;
};

/** Modal centrado. Se cierra solo con la X, Cancelar o Escape: nunca por clic en el fondo. */
export function Dialog({ abierto, onCambiar, titulo, descripcion, acciones, children, pie, tamano = 'md', ocupado }: Props) {
    // Cerrar la pestaña también perdería la espera: el navegador pide confirmación.
    useEffect(() => {
        if (!ocupado) return;
        const avisar = (e: BeforeUnloadEvent) => e.preventDefault();
        window.addEventListener('beforeunload', avisar);
        return () => window.removeEventListener('beforeunload', avisar);
    }, [ocupado]);

    return (
        <D.Root open={abierto} onOpenChange={(a) => (a || !ocupado) && onCambiar(a)}>
            <D.Portal>
                <D.Overlay className="fixed inset-0 z-40 bg-overlay backdrop-blur-sm motion-safe:animate-[velo_180ms_ease-out]" />
                <D.Content
                    onInteractOutside={(e) => e.preventDefault()}
                    onEscapeKeyDown={(e) => ocupado && e.preventDefault()}
                    className={cn(
                        'fixed top-1/2 left-1/2 z-50 flex max-h-[90vh] -translate-x-1/2 -translate-y-1/2 flex-col overflow-hidden rounded-hoja bg-surface shadow-flotante motion-safe:animate-[hoja_220ms_cubic-bezier(0.2,0.9,0.3,1)]',
                        ANCHO[tamano],
                    )}
                >
                    <header className="flex items-start justify-between gap-4 px-6 pt-5">
                        <div className="min-w-0">
                            <D.Title className="text-lg font-bold tracking-tight text-fg">{titulo}</D.Title>
                            {descripcion ? (
                                <D.Description asChild>
                                    <div className="mt-1 text-base text-fg-muted">{descripcion}</div>
                                </D.Description>
                            ) : (
                                <D.Description className="sr-only">{titulo}</D.Description>
                            )}
                        </div>
                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                            {acciones}
                            <D.Close asChild>
                                <IconButton icono={<IcoCerrar16 />} etiqueta="Cerrar" tamano="sm" className="rounded-full bg-relleno text-fg-muted hover:bg-relleno-fuerte" />
                            </D.Close>
                        </div>
                    </header>
                    {children && <div className="overflow-y-auto px-6 py-5">{children}</div>}
                    {pie && <footer className="flex items-center justify-end gap-2 border-t border-separador bg-surface-subtle/60 px-6 py-3.5">{pie}</footer>}
                </D.Content>
                {/* Fuera de Content: dentro, el transform lo encerraría en el modal. pointer-events: Radix los apaga fuera del modal. */}
                {ocupado && (
                    <div className="pointer-events-auto fixed inset-0 z-[60] flex items-center justify-center bg-overlay p-4 backdrop-blur-sm motion-safe:animate-[velo_180ms_ease-out]">
                        <div className="max-w-md rounded-hoja bg-surface px-6 py-5 text-base text-fg shadow-flotante motion-safe:animate-[hoja_220ms_cubic-bezier(0.2,0.9,0.3,1)]">
                            {ocupado}
                        </div>
                    </div>
                )}
            </D.Portal>
        </D.Root>
    );
}
