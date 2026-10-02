import type { ReactNode } from 'react';
import { Dialog } from './Dialog';

type Props = {
    abierto: boolean;
    onCambiar: (abierto: boolean) => void;
    titulo: string;
    subtitulo?: ReactNode;
    acciones?: ReactNode;
    tamano?: 'md' | 'lg' | 'xl';
    children: ReactNode;
};

/** Detalle de un registro de la lista, en modal (ver, como crear y editar, va en modal). */
export function DetalleDialog({ abierto, onCambiar, titulo, subtitulo, acciones, tamano = 'md', children }: Props) {
    return (
        <Dialog abierto={abierto} onCambiar={onCambiar} titulo={titulo} descripcion={subtitulo} tamano={tamano} pie={acciones}>
            {children}
        </Dialog>
    );
}
