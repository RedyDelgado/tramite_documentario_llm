import { useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { Combobox } from '@/components/ui/Combobox';
import type { Opcion } from '@/types';

type Props = {
    opciones: Opcion<number>[];
    value: number | null;
    onChange: (value: number | null) => void;
    // El error del alta en línea («Ya existe», «Parecido a…») se muestra en el FormField.
    onError: (mensaje: string | undefined) => void;
    id?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/** Emisor con alta en línea (7.3.5): si el servidor avisa de un parecido, crear otra vez lo confirma. */
export function EmisorCombobox({ opciones: iniciales, value, onChange, onError, ...campo }: Props) {
    const [opciones, setOpciones] = useState(iniciales);
    const [porConfirmar, setPorConfirmar] = useState<string | null>(null);
    const alta = useHttp<{ nombre: string; tipo: string; confirmado: boolean }, Opcion<number>>('post', '/emisores/rapido', {
        nombre: '',
        tipo: 'externo',
        confirmado: false,
    });

    const crear = (nombre: string) => {
        alta.transform(() => ({ nombre, tipo: 'externo', confirmado: porConfirmar === nombre }));
        alta.post('/emisores/rapido', {
            onSuccess: (nuevo) => {
                setOpciones((o) => [...o, nuevo].sort((a, b) => a.label.localeCompare(b.label)));
                onChange(nuevo.value);
                setPorConfirmar(null);
                onError(undefined);
            },
            onError: (errores) => {
                setPorConfirmar(nombre);
                onError(errores.nombre);
            },
        }).catch(() => undefined);
    };

    return (
        <Combobox
            {...campo}
            opciones={opciones}
            value={value}
            onChange={(v) => {
                onChange(v);
                onError(undefined);
            }}
            onCrear={crear}
            creando={alta.processing}
            placeholder="Escribe para buscar o crear"
        />
    );
}
