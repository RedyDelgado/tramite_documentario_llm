import { useId, type KeyboardEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';

type Props = {
    titulo: string;
    descripcion?: ReactNode;
    onCerrar: () => void;
    onEnviar: () => void;
    procesando: boolean;
    textoGuardar?: string;
    tamano?: 'md' | 'lg' | 'xl';
    children: ReactNode;
};

/**
 * Plantilla de todo alta o edición: un modal sobre la lista. Enter en un campo no guarda (evita envíos
 * accidentales); Ctrl/Cmd + Enter sí. La validación la hace Laravel y llega como `errors`.
 */
export function FormDialog({ titulo, descripcion, onCerrar, onEnviar, procesando, textoGuardar = 'Guardar', tamano = 'lg', children }: Props) {
    const id = useId();

    const alTeclear = (e: KeyboardEvent<HTMLFormElement>) => {
        if (e.key !== 'Enter') return;
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            e.currentTarget.requestSubmit();
        } else if (e.target instanceof HTMLInputElement) {
            e.preventDefault();
        }
    };

    return (
        <Dialog
            abierto
            onCambiar={(abierto) => !abierto && onCerrar()}
            titulo={titulo}
            descripcion={descripcion}
            tamano={tamano}
            pie={
                <>
                    <span className="mr-auto text-sm text-fg-muted">Ctrl + Enter para guardar</span>
                    <Button onClick={onCerrar}>Cancelar</Button>
                    <Button type="submit" form={id} variante="primario" cargando={procesando}>
                        {textoGuardar}
                    </Button>
                </>
            }
        >
            <form
                id={id}
                noValidate
                onKeyDown={alTeclear}
                onSubmit={(e) => {
                    e.preventDefault();
                    onEnviar();
                }}
                className="flex flex-col gap-4"
            >
                {children}
            </form>
        </Dialog>
    );
}
