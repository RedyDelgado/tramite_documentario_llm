import { Dismiss20Regular } from '@fluentui/react-icons';
import { Dialog as D } from 'radix-ui';
import type { ReactNode } from 'react';
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
};

/** Modal centrado. Se cierra solo con la X, Cancelar o Escape: nunca por clic en el fondo. */
export function Dialog({ abierto, onCambiar, titulo, descripcion, acciones, children, pie, tamano = 'md' }: Props) {
    return (
        <D.Root open={abierto} onOpenChange={onCambiar}>
            <D.Portal>
                <D.Overlay className="fixed inset-0 z-40 bg-overlay" />
                <D.Content
                    onInteractOutside={(e) => e.preventDefault()}
                    className={cn(
                        'fixed top-1/2 left-1/2 z-50 flex max-h-[90vh] -translate-x-1/2 -translate-y-1/2 flex-col rounded-card border border-border bg-surface shadow-card',
                        ANCHO[tamano],
                    )}
                >
                    <header className="flex items-start justify-between gap-4 px-5 pt-4">
                        <div className="min-w-0">
                            <D.Title className="text-lg font-semibold text-fg">{titulo}</D.Title>
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
                                <IconButton icono={<Dismiss20Regular />} etiqueta="Cerrar" />
                            </D.Close>
                        </div>
                    </header>
                    {children && <div className="overflow-y-auto px-5 py-4">{children}</div>}
                    {pie && <footer className="flex items-center justify-end gap-2 border-t border-border px-5 py-3">{pie}</footer>}
                </D.Content>
            </D.Portal>
        </D.Root>
    );
}
