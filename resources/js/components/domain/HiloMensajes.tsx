import { useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { IconButton } from '@/components/ui/IconButton';
import { IcoEnviar } from '@/components/ui/iconos';
import { Textarea } from '@/components/ui/Textarea';
import { cn } from '@/lib/cn';
import { formatearFechaHora, formatearMomento } from '@/lib/fechas';
import type { ConversacionResumen, MensajeChat } from '@/types';

/**
 * Una conversación abierta: los mensajes y el campo para escribir. Enviar no pide confirmación (como el asistente IA):
 * en un chat sería un paso de más en cada mensaje. Enter envía y Mayús + Enter salta de línea.
 */
export function HiloMensajes({ conversacion, mensajes }: { conversacion: ConversacionResumen; mensajes: MensajeChat[] }) {
    const form = useForm({ texto: '' });
    const lista = useRef<HTMLDivElement>(null);
    const ultimo = mensajes.at(-1)?.id;

    // Al llegar un mensaje se baja al final dentro del hilo, sin mover la página.
    useEffect(() => {
        if (lista.current) lista.current.scrollTop = lista.current.scrollHeight;
    }, [ultimo, conversacion.id]);

    const enviar = () => {
        if (!form.data.texto.trim() || form.processing) return;
        form.post(`/mensajes/${conversacion.id}`, { preserveScroll: true, onSuccess: () => form.reset('texto') });
    };

    return (
        <section aria-label={`Conversación con ${conversacion.titulo}`} className="flex h-[calc(100vh-15rem)] min-h-96 flex-col">
            <header className="px-5 pt-4 pb-3">
                <h2 className="truncate text-md font-bold text-fg">{conversacion.titulo}</h2>
                {conversacion.grupo && <p className="truncate text-sm text-fg-muted">Contigo: {conversacion.participantes.join(', ')}</p>}
            </header>

            <div ref={lista} className="scroll-fino mx-3 flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto rounded-card bg-relleno p-3" aria-live="polite">
                {mensajes.length === 0 && <p className="m-auto text-sm text-fg-muted">Todavía no hay mensajes. Escribe el primero.</p>}
                {mensajes.map((m, i) => {
                    // En grupo, el nombre va sobre el primero de cada tanda de la misma persona.
                    const conNombre = conversacion.grupo && !m.propio && mensajes[i - 1]?.autor !== m.autor;

                    return (
                        <div key={m.id} className={cn('flex max-w-[80%] flex-col', m.propio ? 'items-end self-end' : 'items-start self-start')}>
                            {conNombre && <span className="px-1 text-sm font-semibold text-fg-muted">{m.autor}</span>}
                            <p className={cn('rounded-card px-3 py-2 text-base break-words whitespace-pre-line text-fg shadow-card', m.propio ? 'bg-primary-50' : 'bg-surface')}>
                                {m.texto}
                            </p>
                            <time dateTime={m.enviado_at} title={formatearFechaHora(m.enviado_at)} className="px-1 text-sm text-fg-muted">
                                {formatearMomento(m.enviado_at)}
                            </time>
                        </div>
                    );
                })}
            </div>

            <form
                className="flex items-end gap-2 p-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    enviar();
                }}
            >
                <Textarea
                    compacto
                    aria-label="Mensaje"
                    placeholder="Escribe un mensaje"
                    className="min-w-0 flex-1"
                    maxLength={2000}
                    value={form.data.texto}
                    aria-invalid={!!form.errors.texto}
                    onChange={(e) => form.setData('texto', e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey && !e.nativeEvent.isComposing) {
                            e.preventDefault();
                            enviar();
                        }
                    }}
                />
                <IconButton type="submit" icono={<IcoEnviar />} etiqueta="Enviar mensaje" disabled={!form.data.texto.trim() || form.processing} />
            </form>
            {form.errors.texto && <p className="px-4 pb-3 text-sm text-danger">{form.errors.texto}</p>}
        </section>
    );
}
