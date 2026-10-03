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
                <A.Overlay className="fixed inset-0 z-40 bg-overlay backdrop-blur-sm motion-safe:animate-[velo_180ms_ease-out]" />
                <A.Content className="fixed top-1/2 left-1/2 z-50 w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2 -translate-y-1/2 rounded-hoja bg-surface p-6 shadow-flotante motion-safe:animate-[hoja_220ms_cubic-bezier(0.2,0.9,0.3,1)]">
                    <A.Title className="text-lg font-bold tracking-tight text-fg">{titulo}</A.Title>
                    <A.Description className="mt-2 text-base text-fg-muted">{descripcion}</A.Description>
                    <div className="mt-6 flex justify-end gap-2">
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
