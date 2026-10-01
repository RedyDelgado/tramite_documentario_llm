import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { botonClases } from './Button';
import { Tooltip } from './Tooltip';

type Props = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children'> & {
    icono: ReactNode;
    // Obligatoria: es el nombre accesible y el texto del tooltip.
    etiqueta: string;
    tamano?: 'sm' | 'md';
};

export function IconButton({ icono, etiqueta, tamano = 'md', className, type = 'button', ...props }: Props) {
    return (
        <Tooltip texto={etiqueta}>
            <button
                type={type}
                aria-label={etiqueta}
                className={cn(botonClases({ variante: 'sutil', tamano: tamano === 'sm' ? 'icono-sm' : 'icono-md' }), className)}
                {...props}
            >
                {icono}
            </button>
        </Tooltip>
    );
}
