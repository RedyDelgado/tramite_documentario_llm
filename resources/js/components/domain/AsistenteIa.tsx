import { Link, useHttp } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { IconButton } from '@/components/ui/IconButton';
import { IaPensando } from '@/components/ui/IaPensando';
import { IcoAgregar, IcoEnviar, IcoIa } from '@/components/ui/iconos';
import { Input } from '@/components/ui/Input';
import { cn } from '@/lib/cn';

type Fuente = { id: number; numero: string | null; asunto: string };
type Mensaje = { rol: 'usuario' | 'asistente'; texto: string; fuentes?: Fuente[] };

const CLAVE = 'asistente-ia';

// La barra se vuelve a montar en cada página: la conversación sigue en la pestaña mientras dure la sesión.
function leer(): Mensaje[] {
    try {
        return JSON.parse(sessionStorage.getItem(CLAVE) ?? '[]') as Mensaje[];
    } catch {
        return [];
    }
}

/** Chat con la IA local sobre lo que ingresó al sistema; responde solo con los expedientes que quien pregunta puede ver. */
export function AsistenteIa() {
    const [mensajes, setMensajes] = useState<Mensaje[]>(leer);
    const [pregunta, setPregunta] = useState('');
    const fin = useRef<HTMLDivElement>(null);
    const pedido = useHttp<{ pregunta: string; historial: Mensaje[] }, { respuesta: string; fuentes: Fuente[] }>('post', '/asistente-ia', { pregunta: '', historial: [] });

    useEffect(() => {
        try {
            sessionStorage.setItem(CLAVE, JSON.stringify(mensajes.slice(-20)));
        } catch {
            // Sin almacenamiento la conversación solo dura en esta página.
        }
        fin.current?.scrollIntoView({ block: 'end' });
    }, [mensajes, pedido.processing]);

    const enviar = () => {
        const texto = pregunta.trim();
        if (!texto || pedido.processing) return;
        const historial = mensajes.slice(-6).map(({ rol, texto }) => ({ rol, texto }));
        const responder = (respuesta: string, fuentes: Fuente[] = []) => setMensajes((m) => [...m, { rol: 'asistente', texto: respuesta, fuentes }]);
        setMensajes((m) => [...m, { rol: 'usuario', texto }]);
        setPregunta('');
        pedido.transform(() => ({ pregunta: texto, historial }));
        // El servidor responde 422 con un mensaje cuando el modelo no está o no contesta.
        pedido
            .post('/asistente-ia', {
                onSuccess: (r) => responder(r.respuesta, r.fuentes),
                onError: (e) => responder(Object.values(e)[0] ?? 'No se pudo obtener la respuesta.'),
            })
            .catch(() => responder('El modelo de IA no responde. Inténtalo en unos minutos.'));
    };

    return (
        <section aria-label="Asistente de IA" className="mx-3 flex min-h-0 flex-1 flex-col rounded-card bg-relleno">
            <header className="flex items-center gap-2 px-3 pt-3 pb-2">
                <IcoIa className="shrink-0 text-primary-600" />
                <h2 className="flex-1 text-base font-bold text-fg">Asistente IA</h2>
                {mensajes.length > 0 && <IconButton tamano="sm" icono={<IcoAgregar />} etiqueta="Nueva conversación" onClick={() => setMensajes([])} />}
            </header>

            <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-3 pb-2" aria-live="polite">
                {mensajes.length === 0 && (
                    <p className="text-sm text-fg-muted">Pregunta por trámites pasados, respuestas de las áreas o lo que haya ingresado. Ej.: «¿Qué respondió Derecho sobre la carta circular 0047?»</p>
                )}
                {mensajes.map((m, i) => (
                    <div
                        key={i}
                        className={cn(
                            'max-w-[90%] rounded-card px-3 py-2 text-sm whitespace-pre-line shadow-card',
                            m.rol === 'usuario' ? 'self-end bg-primary-50 text-fg' : 'self-start bg-surface text-fg',
                        )}
                    >
                        {m.texto}
                        {m.fuentes && m.fuentes.length > 0 && (
                            <ul className="mt-2 flex flex-col gap-1 border-t border-separador pt-2">
                                {m.fuentes.map((f) => (
                                    <li key={f.id} className="truncate">
                                        <Link href={`/expedientes/${f.id}`} className="font-semibold text-primary-700 hover:underline">
                                            {f.numero ?? 'Sin número'}
                                        </Link>{' '}
                                        <span className="text-fg-muted">{f.asunto}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                ))}
                {pedido.processing && <IaPensando className="text-sm text-fg-muted">Buscando en los trámites</IaPensando>}
                <div ref={fin} />
            </div>

            <form
                className="flex items-center gap-1 p-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    enviar();
                }}
            >
                <Input aria-label="Pregunta para la IA" placeholder="Escribe tu consulta" className="min-w-0 flex-1" value={pregunta} maxLength={1000} onChange={(e) => setPregunta(e.target.value)} />
                <IconButton type="submit" icono={<IcoEnviar />} etiqueta="Enviar consulta" disabled={!pregunta.trim() || pedido.processing} />
            </form>
        </section>
    );
}
