import type { ReactNode } from 'react';

/** Pares etiqueta–valor de un panel de detalle. */
export function DetalleLista({ items }: { items: Array<{ etiqueta: string; valor: ReactNode }> }) {
    return (
        <dl className="divide-y divide-separador rounded-card bg-surface-subtle">
            {items.map((i) => (
                <div key={i.etiqueta} className="flex items-center justify-between gap-4 px-4 py-2.5">
                    <dt className="shrink-0 text-base text-fg-muted">{i.etiqueta}</dt>
                    <dd className="min-w-0 text-right text-base text-fg [overflow-wrap:anywhere]">{i.valor}</dd>
                </div>
            ))}
        </dl>
    );
}
