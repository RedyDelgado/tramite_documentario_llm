import type { ReactNode } from 'react';

type Props = { icono?: ReactNode; titulo: string; descripcion?: string; accion?: ReactNode };

export function EmptyState({ icono, titulo, descripcion, accion }: Props) {
    return (
        <div className="flex flex-col items-center gap-2 px-4 py-12 text-center">
            {icono && <div className="mb-1 flex size-14 items-center justify-center rounded-full bg-primary-50 text-primary-600 [&>svg]:size-7">{icono}</div>}
            <p className="text-md font-semibold text-fg">{titulo}</p>
            {descripcion && <p className="max-w-md text-base text-fg-muted">{descripcion}</p>}
            {accion && <div className="mt-2">{accion}</div>}
        </div>
    );
}
