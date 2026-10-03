import { IcoAgregar16, IcoFlechaAbajo16 } from '@/components/ui/iconos';
import { Popover as P } from 'radix-ui';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '@/lib/cn';
import type { Opcion } from '@/types';
import { campoClases } from './campo';

type Props = {
    opciones: Opcion<number>[];
    value: number | null;
    onChange: (value: number | null) => void;
    // Con ella aparece «Crear «texto»» cuando nada coincide exactamente (alta en línea, 7.3.5).
    onCrear?: (texto: string) => void;
    creando?: boolean;
    id?: string;
    placeholder?: string;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

// Sin tildes ni mayúsculas: «gestion» encuentra «Gestión».
const normalizar = (texto: string) =>
    texto
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

/** Autocompletado sobre una lista: cada palabra escrita debe aparecer en la opción, en cualquier orden. */
export function Combobox({ opciones, value, onChange, onCrear, creando, id, placeholder, disabled, ...aria }: Props) {
    const elegida = opciones.find((o) => o.value === value) ?? null;
    const [texto, setTexto] = useState(elegida?.label ?? '');
    const [abierto, setAbierto] = useState(false);
    const [activa, setActiva] = useState(0);
    const ancla = useRef<HTMLDivElement>(null);
    const lista = useId();

    // La opción puede llegar después (alta en línea): el texto sigue a la elegida.
    useEffect(() => setTexto(elegida?.label ?? ''), [elegida?.label]);

    const palabras = normalizar(texto).split(/\s+/).filter(Boolean);
    const filtradas = opciones.filter((o) => palabras.every((p) => normalizar(o.label).includes(p))).slice(0, 50);
    const puedeCrear = Boolean(onCrear) && palabras.length > 0 && !opciones.some((o) => normalizar(o.label) === normalizar(texto));
    const total = filtradas.length + (puedeCrear ? 1 : 0);

    const elegir = (indice: number) => {
        if (indice < filtradas.length) {
            onChange(filtradas[indice].value);
            setTexto(filtradas[indice].label);
        } else if (puedeCrear) {
            onCrear?.(texto.trim());
        }
        setAbierto(false);
    };

    const alTeclear = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            setAbierto(true);
            if (total > 0) setActiva((a) => (a + (e.key === 'ArrowDown' ? 1 : total - 1)) % total);
        } else if (e.key === 'Enter' && abierto && total > 0) {
            // Enter elige la opción; nunca envía el formulario.
            e.preventDefault();
            elegir(activa);
        }
    };

    const alSalir = () => {
        setAbierto(false);
        if (texto.trim() === '') {
            if (value !== null) onChange(null);
        } else if (elegida) {
            setTexto(elegida.label);
        }
        // Sin elegida se conserva lo escrito: el alta en línea puede estar pendiente de confirmar.
    };

    return (
        <P.Root open={abierto && total > 0} onOpenChange={setAbierto}>
            <P.Anchor asChild>
                <div ref={ancla} className="relative">
                    <input
                        id={id}
                        role="combobox"
                        aria-expanded={abierto && total > 0}
                        aria-controls={lista}
                        aria-autocomplete="list"
                        aria-activedescendant={abierto && total > 0 ? `${lista}-${activa}` : undefined}
                        autoComplete="off"
                        value={texto}
                        placeholder={placeholder}
                        disabled={disabled}
                        aria-busy={creando}
                        onChange={(e) => {
                            setTexto(e.target.value);
                            setActiva(0);
                            setAbierto(true);
                        }}
                        onClick={() => setAbierto(true)}
                        onKeyDown={alTeclear}
                        onBlur={alSalir}
                        className={cn(campoClases, 'h-9 pr-9')}
                        {...aria}
                    />
                    <IcoFlechaAbajo16 className="pointer-events-none absolute inset-y-0 right-3 my-auto text-fg-muted" />
                </div>
            </P.Anchor>
            <P.Portal>
                <P.Content
                    id={lista}
                    role="listbox"
                    align="start"
                    sideOffset={4}
                    onOpenAutoFocus={(e) => e.preventDefault()}
                    onCloseAutoFocus={(e) => e.preventDefault()}
                    onInteractOutside={(e) => ancla.current?.contains(e.target as Node) && e.preventDefault()}
                    className="z-50 max-h-64 w-(--radix-popper-anchor-width) overflow-y-auto rounded-card bg-surface p-1.5 shadow-flotante"
                >
                    {filtradas.map((o, i) => (
                        <div
                            key={o.value}
                            id={`${lista}-${i}`}
                            role="option"
                            aria-selected={o.value === value}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => elegir(i)}
                            onMouseEnter={() => setActiva(i)}
                            className={cn('flex h-8 cursor-pointer items-center rounded-[6px] px-2.5 text-base', i === activa ? 'bg-primary-600 text-on-primary' : 'text-fg', o.value === value && 'font-semibold')}
                        >
                            {o.label}
                        </div>
                    ))}
                    {puedeCrear && (
                        <div
                            id={`${lista}-${filtradas.length}`}
                            role="option"
                            aria-selected={false}
                            onMouseDown={(e) => e.preventDefault()}
                            onClick={() => elegir(filtradas.length)}
                            onMouseEnter={() => setActiva(filtradas.length)}
                            className={cn(
                                'flex h-8 cursor-pointer items-center gap-2 rounded-[6px] px-2.5 text-base',
                                activa === filtradas.length ? 'bg-primary-600 text-on-primary' : 'text-primary-700',
                                filtradas.length > 0 && 'mt-1',
                            )}
                        >
                            <IcoAgregar16 />
                            Crear «{texto.trim()}»
                        </div>
                    )}
                </P.Content>
            </P.Portal>
        </P.Root>
    );
}
