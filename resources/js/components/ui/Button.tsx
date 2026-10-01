import { cva, type VariantProps } from 'class-variance-authority';
import type { ButtonHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { Spinner } from './Spinner';

// Exportado para dar el mismo aspecto a enlaces de Inertia (<Link className={botonClases(...)}>).
export const botonClases = cva(
    'inline-flex shrink-0 cursor-pointer items-center justify-center gap-1.5 rounded-control font-semibold whitespace-nowrap select-none transition-colors disabled:cursor-not-allowed aria-disabled:pointer-events-none',
    {
        variants: {
            variante: {
                primario:
                    'bg-primary-600 text-on-primary hover:bg-primary-700 active:bg-primary-800 disabled:bg-border disabled:text-fg-disabled',
                secundario:
                    'border border-border-strong bg-surface text-fg hover:bg-surface-subtle active:bg-border disabled:border-border disabled:bg-surface disabled:text-fg-disabled',
                sutil: 'bg-transparent text-fg hover:bg-primary-50 active:bg-primary-100 disabled:bg-transparent disabled:text-fg-disabled',
                peligro:
                    'bg-danger text-on-primary hover:bg-danger/90 active:bg-danger/80 disabled:bg-border disabled:text-fg-disabled',
            },
            tamano: {
                sm: 'h-7 px-2 text-sm',
                md: 'h-8 px-3 text-base',
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
