import { ChevronDown16Regular } from '@fluentui/react-icons';
import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';
import type { Opcion } from '@/types';
import { campoClases } from './campo';

type Props = Omit<SelectHTMLAttributes<HTMLSelectElement>, 'children'> & {
    opciones: Opcion[];
    // Primera opción con valor vacío (p. ej. «Todas», «Ninguna»).
    vacia?: string;
};

/** Select nativo: accesible y con teclado del sistema; el autocompletado es Combobox. */
export function Select({ opciones, vacia, className, ...props }: Props) {
    return (
        <div className={cn('relative', className)}>
            <select className={cn(campoClases, 'h-8 cursor-pointer appearance-none pr-8')} {...props}>
                {vacia !== undefined && <option value="">{vacia}</option>}
                {opciones.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
            <ChevronDown16Regular className="pointer-events-none absolute inset-y-0 right-2 my-auto text-fg-muted" />
        </div>
    );
}
