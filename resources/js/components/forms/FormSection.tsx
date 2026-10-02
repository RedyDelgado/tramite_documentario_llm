import type { ReactNode } from 'react';

type Props = { titulo: string; descripcion?: string; children: ReactNode };

/** Bloque de campos con su título dentro de un modal; los campos van en dos columnas desde pantallas medianas. */
export function FormSection({ titulo, descripcion, children }: Props) {
    return (
        <section className="border-t border-border pt-4 first:border-t-0 first:pt-0">
            <header className="mb-3">
                <h2 className="text-md font-semibold text-fg">{titulo}</h2>
                {descripcion && <p className="mt-0.5 text-base text-fg-muted">{descripcion}</p>}
            </header>
            <div className="grid gap-4 md:grid-cols-2">{children}</div>
        </section>
    );
}
