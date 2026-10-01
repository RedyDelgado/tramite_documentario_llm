import { AlertDialog as A } from 'radix-ui';
import { Button } from './Button';

type Props = {
    abierto: boolean;
    onCambiar: (abierto: boolean) => void;
    titulo: string;
    descripcion: string;
    confirmar: string;
    onConfirmar: () => void;
    peligro?: boolean;
    cargando?: boolean;
};

/** Confirmación de una acción; el foco inicial va a Cancelar para que Enter no confirme por error. */
export function ConfirmDialog({ abierto, onCambiar, titulo, descripcion, confirmar, onConfirmar, peligro = false, cargando = false }: Props) {
    return (
        <A.Root open={abierto} onOpenChange={onCambiar}>
            <A.Portal>
                <A.Overlay className="fixed inset-0 z-40 bg-overlay" />
                <A.Content className="fixed top-1/2 left-1/2 z-50 w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 rounded-card border border-border bg-surface p-5 shadow-card">
                    <A.Title className="text-lg font-semibold text-fg">{titulo}</A.Title>
                    <A.Description className="mt-2 text-base text-fg-muted">{descripcion}</A.Description>
                    <div className="mt-5 flex justify-end gap-2">
                        <A.Cancel asChild>
                            <Button>Cancelar</Button>
                        </A.Cancel>
                        <Button variante={peligro ? 'peligro' : 'primario'} cargando={cargando} onClick={onConfirmar}>
                            {confirmar}
                        </Button>
                    </div>
                </A.Content>
            </A.Portal>
        </A.Root>
    );
}
