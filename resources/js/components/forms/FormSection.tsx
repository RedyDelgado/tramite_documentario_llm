import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

// 4 columnas para formularios densos: los campos cortos (fecha, folios) no ocupan media fila.
type Props = { titulo?: string; descripcion?: string; columnas?: 2 | 4; children: ReactNode };

/** Bloque de campos con su título dentro de un modal; los campos van en dos columnas desde pantallas medianas. */
export function FormSection({ titulo, descripcion, columnas = 2, children }: Props) {
    return (
        <section className="rounded-card bg-surface-subtle p-4">
            {/* Sin título cuando es el único bloque del modal: el título del modal ya lo dice. */}
            {titulo && (
                <header className="mb-3">
                    <h2 className="text-base font-semibold text-fg">{titulo}</h2>
                    {descripcion && <p className="mt-0.5 text-base text-fg-muted">{descripcion}</p>}
                </header>
            )}
            <div className={cn('grid gap-4', columnas === 4 ? 'md:grid-cols-4' : 'md:grid-cols-2')}>{children}</div>
        </section>
    );
}
