import { useState } from 'react';
import type { Opcion } from '@/types';
import { Combobox } from './Combobox';
import { Ficha } from './TagsInput';

type Props = {
    opciones: Opcion<number>[];
    valor: number[];
    onCambiar: (valor: number[]) => void;
    id?: string;
    placeholder?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/** Varias opciones de una lista larga: se buscan escribiendo y quedan como fichas, sin listas que obliguen a desplazarse. */
export function ComboboxMultiple({ opciones, valor, onCambiar, ...campo }: Props) {
    // Cambiar la clave vacía el buscador después de elegir.
    const [vuelta, setVuelta] = useState(0);

    return (
        <div className="flex flex-col gap-2">
            <Combobox
                key={vuelta}
                {...campo}
                opciones={opciones.filter((o) => !valor.includes(o.value))}
                value={null}
                onChange={(v) => {
                    if (v === null) return;
                    onCambiar([...valor, v]);
                    setVuelta((n) => n + 1);
                }}
            />
            {valor.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {valor.map((v) => (
                        <Ficha key={v} texto={opciones.find((o) => o.value === v)?.label ?? String(v)} onQuitar={() => onCambiar(valor.filter((x) => x !== v))} />
                    ))}
                </div>
            )}
        </div>
    );
}
