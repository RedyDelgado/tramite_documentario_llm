import type { InputHTMLAttributes, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { campoClases } from './campo';

type Props = InputHTMLAttributes<HTMLInputElement> & { iconoInicio?: ReactNode };

export function Input({ iconoInicio, className, ...props }: Props) {
    if (!iconoInicio) {
        return <input className={cn(campoClases, 'h-8', className)} {...props} />;
    }

    return (
        <div className={cn('relative', className)}>
            <span className="pointer-events-none absolute inset-y-0 left-2 flex items-center text-fg-muted">{iconoInicio}</span>
            <input className={cn(campoClases, 'h-8 pl-8')} {...props} />
        </div>
    );
}
