import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';

type Props = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { etiqueta?: string };

export function Checkbox({ etiqueta, className, ...props }: Props) {
    const control = (
        <input
            type="checkbox"
            className={cn('size-4 cursor-pointer accent-primary-600 disabled:cursor-not-allowed', !etiqueta && className)}
            {...props}
        />
    );

    if (!etiqueta) return control;

    return (
        <label className={cn('inline-flex cursor-pointer items-center gap-2 text-base text-fg has-disabled:cursor-not-allowed has-disabled:text-fg-disabled', className)}>
            {control}
            {etiqueta}
        </label>
    );
}
