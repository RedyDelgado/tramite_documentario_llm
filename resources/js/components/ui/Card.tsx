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
        <section className={cn('rounded-card bg-surface shadow-card', className)}>
            {(titulo || acciones) && (
                <header className="flex min-h-12 items-center justify-between gap-2 px-5 pt-4 pb-1">
                    {titulo && <h2 className="text-md font-semibold text-fg">{titulo}</h2>}
                    {acciones}
                </header>
            )}
            <div className={sinRelleno ? 'pb-2' : cn('px-5 pb-5', titulo || acciones ? 'pt-2' : 'pt-5')}>{children}</div>
        </section>
    );
}
