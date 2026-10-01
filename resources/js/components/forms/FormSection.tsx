import type { ReactNode } from 'react';

type Props = { titulo: string; descripcion?: string; children: ReactNode };

/** Bloque de campos con su título; los campos se ordenan en dos columnas desde pantallas medianas. */
export function FormSection({ titulo, descripcion, children }: Props) {
    return (
        <section className="rounded-card border border-border bg-surface shadow-card">
            <header className="border-b border-border px-4 py-3">
                <h2 className="text-md font-semibold text-fg">{titulo}</h2>
                {descripcion && <p className="mt-0.5 text-base text-fg-muted">{descripcion}</p>}
            </header>
            <div className="grid gap-4 p-4 md:grid-cols-2">{children}</div>
        </section>
    );
}
