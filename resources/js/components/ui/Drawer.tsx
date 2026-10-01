import { Dismiss20Regular } from '@fluentui/react-icons';
import { Dialog as D } from 'radix-ui';
import type { ReactNode } from 'react';
import { IconButton } from './IconButton';

type Props = {
    abierto: boolean;
    onCambiar: (abierto: boolean) => void;
    titulo: string;
    subtitulo?: ReactNode;
    acciones?: ReactNode;
    children: ReactNode;
};

/**
 * Panel lateral no modal: la lista de atrás sigue usable y elegir otra fila cambia el contenido.
 * Se cierra con la X o Escape, no con clics fuera.
 */
export function Drawer({ abierto, onCambiar, titulo, subtitulo, acciones, children }: Props) {
    return (
        <D.Root open={abierto} onOpenChange={onCambiar} modal={false}>
            <D.Portal>
                <D.Content
                    onInteractOutside={(e) => e.preventDefault()}
                    onOpenAutoFocus={(e) => e.preventDefault()}
                    className="fixed top-12 right-0 bottom-0 z-30 flex w-[min(30rem,100vw)] flex-col border-l border-border bg-surface shadow-card"
                >
                    <header className="flex items-start justify-between gap-3 border-b border-border px-4 py-3">
                        <div className="min-w-0">
                            <D.Title className="truncate text-lg font-semibold text-fg">{titulo}</D.Title>
                            {subtitulo ? (
                                <D.Description asChild>
                                    <div className="text-base text-fg-muted">{subtitulo}</div>
                                </D.Description>
                            ) : (
                                <D.Description className="sr-only">Detalle de {titulo}</D.Description>
                            )}
                        </div>
                        <D.Close asChild>
                            <IconButton icono={<Dismiss20Regular />} etiqueta="Cerrar panel" />
                        </D.Close>
                    </header>
                    {acciones && <div className="flex items-center gap-2 border-b border-border px-4 py-2">{acciones}</div>}
                    <div className="flex-1 overflow-y-auto px-4 py-4">{children}</div>
                </D.Content>
            </D.Portal>
        </D.Root>
    );
}
