import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';

// `ayuda` explica la vista al pasar el mouse, para quien no conoce el término («Histórico»).
export type Pestana = { valor: string; etiqueta: string; total?: number; ayuda?: string };

type Props = {
    etiqueta: string;
    pestanas: Pestana[];
    activa: string;
    onCambiar: (valor: string) => void;
};

/** Control segmentado (HIG, Segmented controls) para cambiar de vista en una bandeja; el total va al lado. */
export function Pestanas({ etiqueta, pestanas, activa, onCambiar }: Props) {
    return (
        <div role="tablist" aria-label={etiqueta} className="flex w-fit max-w-full gap-1 overflow-x-auto rounded-full bg-relleno p-1">
            {pestanas.map((p) => {
                const seleccionada = p.valor === activa;
                const boton = (
                    <button
                        key={p.valor}
                        type="button"
                        role="tab"
                        aria-selected={seleccionada}
                        onClick={() => onCambiar(p.valor)}
                        className={cn(
                            'inline-flex h-8 shrink-0 items-center gap-2 rounded-full px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                            seleccionada ? 'bg-surface text-fg shadow-card' : 'text-fg-muted hover:text-fg',
                        )}
                    >
                        {p.etiqueta}
                        {p.total !== undefined && (
                            <span className={cn('min-w-5 rounded-full px-1.5 text-sm tabular-nums', seleccionada ? 'bg-primary-100 text-primary-700' : 'bg-relleno-fuerte')}>
                                {p.total}
                            </span>
                        )}
                    </button>
                );

                return p.ayuda ? (
                    <Tooltip key={p.valor} texto={p.ayuda}>
                        {boton}
                    </Tooltip>
                ) : (
                    boton
                );
            })}
        </div>
    );
}
