import { IcoCorrecto, IcoIa } from '@/components/ui/iconos';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import type { CorreoDetalle } from '@/types';

type Props = { correoId: number; respuesta: NonNullable<CorreoDetalle['respuesta']>; puedeAtender: boolean };

/** Un correo que llegó mientras un área atendía: la IA propone si responde a lo pedido y una persona del área lo confirma. */
export function RespuestaCorreo({ correoId, respuesta, puedeAtender }: Props) {
    const [enviando, setEnviando] = useState(false);

    return (
        <div className="mt-3 flex flex-wrap items-center gap-2 rounded-card bg-surface-subtle px-4 py-3">
            <IcoIa className="text-primary-600" />
            <span className="text-base font-medium text-fg">Respuesta{respuesta.area ? ` de ${respuesta.area}` : ''}</span>
            {respuesta.ia === 'responde' && <Badge tono="ok">La IA: responde a lo pedido</Badge>}
            {respuesta.ia === 'no_relacionado' && <Badge tono="aviso">La IA: no parece responder a lo pedido</Badge>}
            {respuesta.ia === null && <Badge>Sin revisión de la IA</Badge>}
            {puedeAtender && (
                <span className="ml-auto">
                    <BotonConfirmado
                        tamano="sm"
                        variante="primario"
                        icono={<IcoCorrecto />}
                        titulo="¿Dar por atendido con esta respuesta?"
                        descripcion="Tu área queda atendida con este correo como respuesta; el expediente queda atendido cuando lo atiendan todas sus áreas."
                        confirmar="Dar por atendido"
                        cargando={enviando}
                        onConfirmar={(cerrar) =>
                            router.post(`/correos/${correoId}/atender`, {}, { preserveScroll: true, onStart: () => setEnviando(true), onFinish: () => (setEnviando(false), cerrar()) })
                        }
                    >
                        Dar por atendido con esta respuesta
                    </BotonConfirmado>
                </span>
            )}
            {respuesta.motivo && <p className="w-full text-sm text-fg-muted">{respuesta.motivo}</p>}
        </div>
    );
}
