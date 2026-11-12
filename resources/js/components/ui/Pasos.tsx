import { IcoCheck16 } from '@/components/ui/iconos';
import { Fragment } from 'react';
import { cn } from '@/lib/cn';

/** Avance de un proceso en pasos: los hechos con check, el actual resaltado y los que faltan atenuados. */
export function Pasos({ etiqueta, pasos, actual }: { etiqueta: string; pasos: string[]; actual: number }) {
    return (
        <ol aria-label={etiqueta} className="flex flex-wrap items-center gap-x-2 gap-y-2">
            {pasos.map((paso, i) => {
                const hecho = i < actual;
                const enCurso = i === actual;

                return (
                    <Fragment key={paso}>
                        {i > 0 && <li aria-hidden className={cn('h-0.5 w-6 rounded-full', hecho || enCurso ? 'bg-primary-600' : 'bg-relleno-fuerte')} />}
                        <li aria-current={enCurso ? 'step' : undefined} className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums',
                                    hecho ? 'bg-primary-600 text-on-primary' : enCurso ? 'bg-primary-100 text-primary-700 ring-2 ring-primary-600' : 'bg-relleno text-fg-muted',
                                )}
                            >
                                {hecho ? <IcoCheck16 aria-hidden /> : i + 1}
                            </span>
                            <span className={cn('text-sm whitespace-nowrap', enCurso ? 'font-semibold text-fg' : hecho ? 'text-fg' : 'text-fg-muted')}>
                                {paso}
                                {hecho && <span className="sr-only"> (hecho)</span>}
                            </span>
                        </li>
                    </Fragment>
                );
            })}
        </ol>
    );
}
