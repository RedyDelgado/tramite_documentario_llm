import { Link } from '@inertiajs/react';
import { IcoPersona, IcoUsuarios } from '@/components/ui/iconos';
import { cn } from '@/lib/cn';
import { formatearMomento } from '@/lib/fechas';
import type { ConversacionResumen } from '@/types';

/** Conversaciones del usuario, la más reciente arriba; la elegida se resalta como en la barra lateral. */
export function ListaConversaciones({ conversaciones, activa }: { conversaciones: ConversacionResumen[]; activa: number | null }) {
    return (
        <ul className="flex flex-col gap-0.5" aria-label="Conversaciones">
            {conversaciones.map((c) => {
                const elegida = c.id === activa;
                const pendientes = c.sin_leer ?? 0;

                return (
                    <li key={c.id}>
                        <Link
                            href={`/mensajes?c=${c.id}`}
                            preserveScroll
                            aria-current={elegida ? 'true' : undefined}
                            className={cn('flex items-center gap-3 rounded-control px-3 py-2.5 transition-colors', elegida ? 'bg-primary-50' : 'hover:bg-relleno')}
                        >
                            <span aria-hidden className="flex size-9 shrink-0 items-center justify-center rounded-full bg-relleno text-fg-muted">
                                {c.grupo ? <IcoUsuarios /> : <IcoPersona />}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex items-baseline gap-2">
                                    <span className={cn('flex-1 truncate text-base text-fg', (pendientes > 0 || elegida) && 'font-bold')}>{c.titulo}</span>
                                    {c.ultimo_mensaje_at && <span className="shrink-0 text-sm text-fg-muted">{formatearMomento(c.ultimo_mensaje_at)}</span>}
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className={cn('flex-1 truncate text-sm', pendientes > 0 ? 'font-semibold text-fg' : 'text-fg-muted')}>
                                        {c.ultimo_texto ?? 'Sin mensajes todavía'}
                                    </span>
                                    {pendientes > 0 && (
                                        <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary-600 px-1.5 text-sm leading-none font-bold text-on-primary">
                                            <span className="sr-only">Sin leer: </span>
                                            {pendientes}
                                        </span>
                                    )}
                                </span>
                            </span>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
