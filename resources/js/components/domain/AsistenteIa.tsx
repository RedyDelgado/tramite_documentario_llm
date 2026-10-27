import { Link } from '@inertiajs/react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { IconButton } from '@/components/ui/IconButton';
import { IaPensando } from '@/components/ui/IaPensando';
import { IcoAgregar, IcoEnviar, IcoIa } from '@/components/ui/iconos';
import { Input } from '@/components/ui/Input';
import { cn } from '@/lib/cn';

type Fuente = { id: number; numero: string | null; asunto: string };
type Mensaje = { rol: 'usuario' | 'asistente'; texto: string; fuentes?: Fuente[] };

const CLAVE = 'asistente-ia';
// Lo que el modelo recuerda: los últimos intercambios. Más atrás no los ve, así que no hay buzón que se llene.
const MEMORIA = 6;

type Estado = { mensajes: Mensaje[]; pensando: boolean };

// La conversación vive fuera del componente: la barra se vuelve a montar en cada página y la consulta en curso no
// debe perderse al navegar. sessionStorage la conserva si se recarga la pestaña.
let estado: Estado = { mensajes: leer(), pensando: false };
const oyentes = new Set<() => void>();

function leer(): Mensaje[] {
    try {
        return JSON.parse(sessionStorage.getItem(CLAVE) ?? '[]') as Mensaje[];
    } catch {
        return [];
    }
}

function cambiar(parcial: Partial<Estado>) {
    estado = { ...estado, ...parcial };
    try {
        sessionStorage.setItem(CLAVE, JSON.stringify(estado.mensajes.slice(-20)));
    } catch {
        // Sin almacenamiento la conversación solo dura hasta recargar.
    }
    oyentes.forEach((o) => o());
}

const agregar = (m: Mensaje) => cambiar({ mensajes: [...estado.mensajes, m] });

// fetch y no un hook de Inertia: el pedido no depende de que el componente siga montado.
async function preguntar(texto: string) {
    const historial = estado.mensajes.slice(-MEMORIA).map(({ rol, texto }) => ({ rol, texto }));
    agregar({ rol: 'usuario', texto });
    cambiar({ pensando: true });
    try {
        const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
        const r = await fetch('/asistente-ia', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            body: JSON.stringify({ pregunta: texto, historial }),
        });
        const datos = await r.json().catch(() => ({}));
        // El servidor responde 422 con un mensaje cuando el modelo no está o no contesta.
        agregar(r.ok ? { rol: 'asistente', texto: datos.respuesta, fuentes: datos.fuentes } : { rol: 'asistente', texto: datos.message ?? 'No se pudo obtener la respuesta.' });
    } catch {
        agregar({ rol: 'asistente', texto: 'El modelo de IA no responde. Inténtalo en unos minutos.' });
    } finally {
        cambiar({ pensando: false });
    }
}

/** Chat con la IA local sobre lo que ingresó al sistema; responde solo con los expedientes que quien pregunta puede ver. */
export function AsistenteIa() {
    const { mensajes, pensando } = useSyncExternalStore(
        (o) => (oyentes.add(o), () => oyentes.delete(o)),
        () => estado,
    );
    const [pregunta, setPregunta] = useState('');
    const fin = useRef<HTMLDivElement>(null);

    useEffect(() => {
        fin.current?.scrollIntoView({ block: 'end' });
    }, [mensajes, pensando]);

    const enviar = () => {
        const texto = pregunta.trim();
        if (!texto || pensando) return;
        setPregunta('');
        void preguntar(texto);
    };

    return (
        <section aria-label="Asistente de IA" className="mx-3 flex min-h-0 flex-1 flex-col rounded-card bg-relleno">
            <header className="flex items-center gap-2 px-3 pt-3 pb-2">
                <IcoIa className="shrink-0 text-primary-600" />
                <h2 className="flex-1 text-base font-bold text-fg">Asistente IA</h2>
                {mensajes.length > 0 && <IconButton tamano="sm" icono={<IcoAgregar />} etiqueta="Nueva conversación" disabled={pensando} onClick={() => cambiar({ mensajes: [] })} />}
            </header>

            <div className="scroll-fino flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-3 pb-2" aria-live="polite">
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
                {mensajes.length > MEMORIA && (
                    <p className="text-center text-sm text-fg-muted">La IA recuerda solo los últimos mensajes. Para otro tema, empieza una conversación nueva con +.</p>
                )}
                {pensando && <IaPensando className="text-sm text-fg-muted">Buscando en los trámites</IaPensando>}
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
                <IconButton type="submit" icono={<IcoEnviar />} etiqueta="Enviar consulta" disabled={!pregunta.trim() || pensando} />
            </form>
        </section>
    );
}
