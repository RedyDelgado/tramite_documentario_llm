import { Dismiss20Regular } from '@fluentui/react-icons';
import { Dialog as D } from 'radix-ui';
import type { ReactNode } from 'react';
import { IconButton } from './IconButton';

type Props = {
    abierto: boolean;
    onCambiar: (abierto: boolean) => void;
    titulo: string;
    descripcion?: string;
    children?: ReactNode;
    pie?: ReactNode;
};

/** Modal centrado. Se cierra solo con la X, Cancelar o Escape: nunca por clic en el fondo. */
export function Dialog({ abierto, onCambiar, titulo, descripcion, children, pie }: Props) {
    return (
        <D.Root open={abierto} onOpenChange={onCambiar}>
            <D.Portal>
                <D.Overlay className="fixed inset-0 z-40 bg-overlay" />
                <D.Content
                    onInteractOutside={(e) => e.preventDefault()}
                    className="fixed top-1/2 left-1/2 z-50 flex max-h-[85vh] w-[min(32rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 flex-col rounded-card border border-border bg-surface shadow-card"
                >
                    <header className="flex items-start justify-between gap-4 px-5 pt-4">
                        <div>
                            <D.Title className="text-lg font-semibold text-fg">{titulo}</D.Title>
                            {descripcion ? (
                                <D.Description className="mt-1 text-base text-fg-muted">{descripcion}</D.Description>
                            ) : (
                                <D.Description className="sr-only">{titulo}</D.Description>
                            )}
                        </div>
                        <D.Close asChild>
                            <IconButton icono={<Dismiss20Regular />} etiqueta="Cerrar" />
                        </D.Close>
                    </header>
                    {children && <div className="overflow-y-auto px-5 py-4">{children}</div>}
                    {pie && <footer className="flex justify-end gap-2 border-t border-border px-5 py-3">{pie}</footer>}
                </D.Content>
            </D.Portal>
        </D.Root>
    );
}
