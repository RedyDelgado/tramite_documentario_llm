import { IcoFlechaDerecha16, IcoInfo } from '@/components/ui/iconos';
import { Pasos } from '@/components/ui/Pasos';
import type { EstadoExpediente, ExpedienteDetalle } from '@/types';

const PASOS = ['Recibido', 'Registrado', 'Derivado', 'Atendido', 'Cerrado'];

// Cerrado marca todos los pasos como hechos.
const PASO: Partial<Record<EstadoExpediente, number>> = { por_revisar: 0, registrado: 1, derivado: 2, en_atencion: 2, atendido: 3, cerrado: PASOS.length };

// Estados fuera del recorrido: no avanzan, así que en lugar de los pasos se explica qué significan.
const FUERA: Partial<Record<EstadoExpediente, string>> = {
    no_tramite: 'Se archivó como «No es trámite»: no tiene número, plazo ni semáforo. No se borró; puede devolverse a revisión.',
    historico: 'Llegó antes de que el sistema empezara a operar: se guarda como referencia, sin plazo ni semáforo.',
    anulado: 'Registro anulado: conserva su número, que no se vuelve a usar.',
};

const lista = (nombres: string[]) => (nombres.length > 1 ? `${nombres.slice(0, -1).join(', ')} y ${nombres.at(-1)}` : nombres[0]);

/** Qué toca ahora: lo dice como acción si quien mira puede hacerlo y, si no, como lo que falta. */
export function siguientePaso(e: ExpedienteDetalle): string {
    const pendientes = e.atenciones.filter((a) => a.estado.valor !== 'atendido').map((a) => a.area);
    // Una respuesta cuenta si vino de un área aún pendiente o de un remitente que no se reconoce.
    const respuesta = e.correos.some((c) => c.respuesta && (!c.respuesta.area || pendientes.some((p) => c.respuesta?.area?.includes(p))));

    switch (e.estado.valor) {
        case 'por_revisar':
            return e.puede_registrar
                ? 'Revisa lo que llegó: si es un trámite, pulsa «Registrar» y recibirá su número; si no lo es, «No es trámite».'
                : 'Falta revisarlo y registrarlo como trámite en mesa de partes.';
        case 'registrado':
            return e.permisos.derivar ? 'Deriva el trámite a una o varias áreas con «Derivar»: el sistema les avisará por correo.' : 'Falta derivarlo a un área.';
        case 'derivado':
        case 'en_atencion':
            if (respuesta && (e.permisos.atender_correo || e.permisos.atender)) {
                return 'Llegó una respuesta de un área: revísala en Correos y, si atiende lo pedido, pulsa «Dar por atendido».';
            }
            return `Esperando la respuesta de ${pendientes.length ? lista(pendientes) : 'las áreas'}. Cuando contesten el aviso por correo, la respuesta aparecerá aquí.`;
        case 'atendido':
            if (e.cierre_solicitado_at) {
                return e.permisos.resolver_cierre ? 'Se pidió el cierre: apruébalo o recházalo con «Resolver el cierre».' : 'Se pidió el cierre: falta que lo apruebe la dirección.';
            }
            return e.permisos.solicitar_cierre ? 'Todas las áreas lo atendieron: pulsa «Solicitar cierre» para concluirlo.' : 'Todas las áreas lo atendieron: falta cerrarlo.';
        default:
            return 'Trámite concluido: todo su recorrido queda en el historial.';
    }
}

/** Dónde está el expediente en su recorrido y cuál es el siguiente paso. */
export function PasosExpediente({ expediente: e }: { expediente: ExpedienteDetalle }) {
    const fuera = FUERA[e.estado.valor];
    if (fuera) {
        return (
            <p className="flex items-start gap-2 text-base text-fg-muted">
                <IcoInfo aria-hidden className="mt-0.5 shrink-0" />
                {fuera}
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <Pasos etiqueta="Recorrido del trámite" pasos={PASOS} actual={PASO[e.estado.valor] ?? 0} />
            <p className="flex items-start gap-2 text-base text-fg">
                <IcoFlechaDerecha16 aria-hidden className="mt-1 shrink-0 text-primary-600" />
                <span>
                    <span className="font-semibold">Siguiente: </span>
                    {siguientePaso(e)}
                </span>
            </p>
        </div>
    );
}
