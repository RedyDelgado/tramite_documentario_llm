import { Dismiss12Regular } from '@fluentui/react-icons';
import { useState, type KeyboardEvent } from 'react';
import { cn } from '@/lib/cn';
import { campoClases } from './campo';

type Props = {
    valor: string[];
    onCambiar: (valor: string[]) => void;
    id?: string;
    placeholder?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

/** Lista de términos (palabras clave, dominios): Enter o coma agrega, Retroceso en vacío quita el último. */
export function TagsInput({ valor, onCambiar, id, placeholder, disabled, ...aria }: Props) {
    const [texto, setTexto] = useState('');

    const agregar = (partes: string[]) => {
        const nuevos = [...valor];
        for (const parte of partes) {
            const termino = parte.trim();
            if (termino && !nuevos.some((v) => v.toLowerCase() === termino.toLowerCase())) nuevos.push(termino);
        }
        if (nuevos.length !== valor.length) onCambiar(nuevos);
    };

    // La coma se detecta en el valor y no en la tecla: así también funciona al pegar «a, b, c».
    const alEscribir = (entrada: string) => {
        const partes = entrada.split(',');
        setTexto(partes.pop() ?? '');
        agregar(partes);
    };

    const alTeclear = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            agregar([texto]);
            setTexto('');
        } else if (e.key === 'Backspace' && texto === '' && valor.length > 0) {
            onCambiar(valor.slice(0, -1));
        }
    };

    return (
        <div
            aria-invalid={aria['aria-invalid']}
            className={cn(
                campoClases,
                'flex min-h-8 flex-wrap items-center gap-1 py-1 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary-600',
                disabled && 'border-border bg-surface-subtle',
            )}
        >
            {valor.map((v) => (
                <span key={v} className="inline-flex h-5 items-center gap-1 rounded-control bg-primary-50 pr-0.5 pl-1.5 text-sm font-semibold text-primary-700">
                    {v}
                    {!disabled && (
                        <button
                            type="button"
                            aria-label={`Quitar ${v}`}
                            onClick={() => onCambiar(valor.filter((x) => x !== v))}
                            className="cursor-pointer rounded-control p-0.5 hover:bg-primary-100"
                        >
                            <Dismiss12Regular />
                        </button>
                    )}
                </span>
            ))}
            <input
                id={id}
                value={texto}
                disabled={disabled}
                placeholder={valor.length === 0 ? placeholder : undefined}
                onChange={(e) => alEscribir(e.target.value)}
                onKeyDown={alTeclear}
                onBlur={() => {
                    agregar([texto]);
                    setTexto('');
                }}
                aria-describedby={aria['aria-describedby']}
                className="h-6 min-w-24 flex-1 bg-transparent text-base text-fg outline-none placeholder:text-fg-disabled"
            />
        </div>
    );
}
