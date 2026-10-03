import { cva, type VariantProps } from 'class-variance-authority';
import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

const badgeClases = cva('inline-flex h-6 items-center gap-1 rounded-full px-2.5 text-sm font-medium whitespace-nowrap', {
    variants: {
        tono: {
            neutro: 'bg-neutral-soft text-fg-muted',
            marca: 'bg-primary-50 text-primary-700',
            // Los tonos semánticos se reservan para estados (5.3), nunca para decorar.
            ok: 'bg-ok-soft text-ok',
            aviso: 'bg-warn-soft text-fg',
            peligro: 'bg-danger-soft text-danger',
        },
    },
    defaultVariants: { tono: 'neutro' },
});

type Props = VariantProps<typeof badgeClases> & { icono?: ReactNode; children: ReactNode; className?: string };

export function Badge({ tono, icono, children, className }: Props) {
    return (
        <span className={cn(badgeClases({ tono }), className)}>
            {icono}
            {children}
        </span>
    );
}
