import { cva, type VariantProps } from 'class-variance-authority';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { Spinner } from './Spinner';

// Exportado para dar el mismo aspecto a enlaces de Inertia (<Link className={botonClases(...)}>).
// Al estilo de Apple: el secundario va relleno de gris y no con contorno; deshabilitado se atenúa (HIG, Buttons).
export const botonClases = cva(
    'inline-flex shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-control font-medium whitespace-nowrap select-none transition-[background-color,opacity] disabled:cursor-not-allowed disabled:opacity-40 aria-disabled:pointer-events-none aria-disabled:opacity-40',
    {
        variants: {
            variante: {
                primario: 'bg-primary-600 text-on-primary shadow-card hover:bg-primary-700 active:bg-primary-800',
                secundario: 'bg-relleno text-fg hover:bg-relleno-fuerte active:bg-relleno-fuerte',
                sutil: 'bg-transparent text-fg hover:bg-relleno active:bg-relleno-fuerte',
                peligro: 'bg-danger text-on-primary shadow-card hover:bg-danger/90 active:bg-danger/80',
            },
            tamano: {
                sm: 'h-7 px-2.5 text-sm',
                md: 'h-8 px-3.5 text-base',
                'icono-sm': 'size-7',
                'icono-md': 'size-8',
            },
        },
        defaultVariants: { variante: 'secundario', tamano: 'md' },
    },
);

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
    VariantProps<typeof botonClases> & {
        icono?: ReactNode;
        cargando?: boolean;
    };

export function Button({ variante, tamano, icono, cargando = false, disabled, className, children, type = 'button', ...props }: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || cargando}
            aria-busy={cargando || undefined}
            className={cn(botonClases({ variante, tamano }), className)}
            {...props}
        >
            {cargando ? <Spinner /> : icono}
            {children}
        </button>
    );
}
