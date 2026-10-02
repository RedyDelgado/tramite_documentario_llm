import { useState, type ReactNode } from 'react';
import { Button } from './Button';
import { ConfirmDialog } from './ConfirmDialog';

type Props = {
    children: ReactNode;
    icono?: ReactNode;
    variante?: 'primario' | 'secundario' | 'peligro';
    tamano?: 'sm' | 'md';
    // La confirmación dice en una frase qué va a pasar.
    titulo: string;
    descripcion: string;
    confirmar: string;
    peligro?: boolean;
    cargando?: boolean;
    disabled?: boolean;
    /** Recibe `cerrar` para cerrar la confirmación cuando termine la petición. */
    onConfirmar: (cerrar: () => void) => void;
};

/** Botón que cambia algo: siempre pide confirmación antes de actuar (CLAUDE.md, «Confirmación antes de actuar»). */
export function BotonConfirmado({ children, icono, variante, tamano, titulo, descripcion, confirmar, peligro, cargando, disabled, onConfirmar }: Props) {
    const [abierto, setAbierto] = useState(false);

    return (
        <>
            <Button icono={icono} variante={variante} tamano={tamano} disabled={disabled} onClick={() => setAbierto(true)}>
                {children}
            </Button>
            <ConfirmDialog
                abierto={abierto}
                onCambiar={setAbierto}
                titulo={titulo}
                descripcion={descripcion}
                confirmar={confirmar}
                peligro={peligro}
                cargando={cargando}
                onConfirmar={() => onConfirmar(() => setAbierto(false))}
            />
        </>
    );
}
