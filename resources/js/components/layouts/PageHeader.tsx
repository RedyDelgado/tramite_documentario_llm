import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = { titulo: string; descripcion?: string; acciones?: ReactNode };

export function PageHeader({ titulo, descripcion, acciones }: Props) {
    return (
        <>
            <Head title={titulo} />
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="text-xl font-semibold text-fg">{titulo}</h1>
                    {descripcion && <p className="mt-1 text-base text-fg-muted">{descripcion}</p>}
                </div>
                {acciones && <div className="flex items-center gap-2">{acciones}</div>}
            </div>
        </>
    );
}
