import type { ReactNode } from 'react';

/** Pares etiqueta–valor de un panel de detalle. */
export function DetalleLista({ items }: { items: Array<{ etiqueta: string; valor: ReactNode }> }) {
    return (
        <dl className="flex flex-col gap-3">
            {items.map((i) => (
                <div key={i.etiqueta}>
                    <dt className="text-sm font-semibold text-fg-muted">{i.etiqueta}</dt>
                    <dd className="mt-0.5 text-base text-fg">{i.valor}</dd>
                </div>
            ))}
        </dl>
    );
}
