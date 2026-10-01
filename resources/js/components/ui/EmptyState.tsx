import type { ReactNode } from 'react';

type Props = { icono?: ReactNode; titulo: string; descripcion?: string; accion?: ReactNode };

export function EmptyState({ icono, titulo, descripcion, accion }: Props) {
    return (
        <div className="flex flex-col items-center gap-2 px-4 py-10 text-center">
            {icono && <div className="text-primary-400 [&>svg]:size-10">{icono}</div>}
            <p className="text-md font-semibold text-fg">{titulo}</p>
            {descripcion && <p className="max-w-md text-base text-fg-muted">{descripcion}</p>}
            {accion && <div className="mt-2">{accion}</div>}
        </div>
    );
}
