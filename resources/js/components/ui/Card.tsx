import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

type Props = {
    titulo?: string;
    acciones?: ReactNode;
    children: ReactNode;
    className?: string;
    sinRelleno?: boolean;
};

export function Card({ titulo, acciones, children, className, sinRelleno = false }: Props) {
    return (
        <section className={cn('rounded-card border border-border bg-surface shadow-card', className)}>
            {(titulo || acciones) && (
                <header className="flex h-11 items-center justify-between gap-2 border-b border-border px-4">
                    {titulo && <h2 className="text-md font-semibold text-fg">{titulo}</h2>}
                    {acciones}
                </header>
            )}
            <div className={sinRelleno ? undefined : 'p-4'}>{children}</div>
        </section>
    );
}
