import { usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { HiloMensajes } from '@/components/domain/HiloMensajes';
import { ListaConversaciones } from '@/components/domain/ListaConversaciones';
import { NuevaConversacion } from '@/components/domain/NuevaConversacion';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { IcoAgregar, IcoComentario } from '@/components/ui/iconos';
import type { ConversacionResumen, MensajeChat, Opcion } from '@/types';

type Props = {
    conversaciones: ConversacionResumen[];
    activa: ConversacionResumen | null;
    mensajes: MensajeChat[];
    usuarios: Opcion<number>[];
};

// Sin WebSockets: con pocas personas en línea basta con preguntar cada 5 s mientras la página está abierta.
const CADA = 5_000;

/** Chat entre usuarios del sistema: la lista de conversaciones a un lado y la abierta al otro. */
export default function MensajesIndex({ conversaciones, activa, mensajes, usuarios }: Props) {
    const [nueva, setNueva] = useState(false);
    usePoll(CADA, { only: ['conversaciones', 'activa', 'mensajes', 'mensajesSinLeer'] });

    const botonNueva = (
        <Button variante="primario" icono={<IcoAgregar />} onClick={() => setNueva(true)}>
            Nueva conversación
        </Button>
    );

    return (
        <AppShell>
            <PageHeader titulo="Mensajes" descripcion="Conversa con otras personas del sistema, a solas o en grupo. Solo quienes participan ven cada conversación." acciones={botonNueva} />

            {conversaciones.length === 0 ? (
                <Card>
                    <EmptyState icono={<IcoComentario />} titulo="Aún no tienes conversaciones" descripcion="Empieza una con alguien de mesa de partes, la dirección u otra persona del sistema." accion={botonNueva} />
                </Card>
            ) : (
                <div className="grid items-start gap-4 lg:grid-cols-[22rem_1fr]">
                    <Card sinRelleno className="p-2">
                        <ListaConversaciones conversaciones={conversaciones} activa={activa?.id ?? null} />
                    </Card>
                    <Card sinRelleno>
                        {activa ? (
                            <HiloMensajes conversacion={activa} mensajes={mensajes} />
                        ) : (
                            <EmptyState icono={<IcoComentario />} titulo="Elige una conversación" descripcion="O empieza una nueva con el botón de arriba." />
                        )}
                    </Card>
                </div>
            )}

            {nueva && <NuevaConversacion usuarios={usuarios} onCerrar={() => setNueva(false)} />}
        </AppShell>
    );
}
