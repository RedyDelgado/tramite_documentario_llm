import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { Spinner } from '@/components/ui/Spinner';
import { IcoAgregar, IcoCorreo, IcoDescargar } from '@/components/ui/iconos';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';

type Lectura = { fecha: string; procesados: number; fallidos: number; error: string | null } | null;

/** BuzonService::estado. */
type Buzon = {
    cuentas: { id: number; cuenta: string; principal: boolean; descargados: number; ultima_lectura: Lectura }[];
    driver: 'gmail' | 'directorio';
    activo: boolean;
    desde: string;
    en_curso: boolean;
    prueba: { descargados: number; ultima_lectura: Lectura } | null;
    inicio_operacion: string | null;
    cliente_configurado: boolean;
    redireccion: string;
};

function UltimaLectura({ lectura }: { lectura: Lectura }) {
    if (!lectura) return <span>aún no se leyó</span>;
    if (lectura.error) return <span className="text-danger">no se pudo leer: {lectura.error}</span>;

    return (
        <span>
            última lectura {formatearFechaHora(lectura.fecha)}
            {lectura.fallidos > 0 && <span className="text-danger"> · {lectura.fallidos} con error</span>}
        </span>
    );
}

export default function BuzonIndex({ buzon: b }: { buzon: Buzon }) {
    const [enviando, setEnviando] = useState<string | null>(null);
    const [desde, setDesde] = useState(b.desde);
    const post = (ruta: string, datos: Record<string, string | boolean>, cerrar?: () => void) =>
        router.post(ruta, datos, { preserveScroll: true, onStart: () => setEnviando(ruta), onFinish: () => (setEnviando(null), cerrar?.()) });
    const total = b.cuentas.reduce((n, c) => n + c.descargados, 0) + (b.prueba?.descargados ?? 0);

    // Mientras descarga en segundo plano, el total se actualiza solo.
    useEffect(() => {
        if (!b.en_curso) return;
        const intervalo = setInterval(() => router.reload({ only: ['buzon'] }), 5000);

        return () => clearInterval(intervalo);
    }, [b.en_curso]);

    return (
        <AppShell>
            <PageHeader
                titulo="Buzón central"
                descripcion="Las cuentas de correo de las que el sistema descarga: cada correo de trámite entra como expediente «Por revisar»."
            />
            <div className="flex max-w-3xl flex-col gap-4">
                <Card titulo="Descargar correos">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-end gap-2">
                            <label className="flex flex-col gap-1 text-base font-medium text-fg">
                                Desde
                                <Input type="date" className="w-44" value={desde} onChange={(e) => setDesde(e.target.value)} />
                            </label>
                            <Button
                                variante="primario"
                                icono={<IcoDescargar />}
                                disabled={!desde}
                                cargando={enviando === '/buzon/descargar'}
                                onClick={() => post('/buzon/descargar', { desde })}
                            >
                                Descargar correos
                            </Button>
                        </div>
                        <p className="text-sm text-fg-muted">
                            Trae de todas las cuentas lo recibido desde esa fecha que aún no está en el sistema, en segundo plano. Lo ya descargado no se repite.
                            {b.inicio_operacion && ` Lo anterior al ${formatearFecha(b.inicio_operacion)} entra como histórico.`}
                        </p>
                        <p className="flex items-center gap-2 text-base text-fg">
                            {b.en_curso && <Spinner />}
                            {b.en_curso && 'Descargando… '}
                            {total} {total === 1 ? 'correo descargado' : 'correos descargados'} en total
                        </p>
                        <div className="flex flex-wrap items-center justify-between gap-2 border-t border-separador pt-4">
                            <span className="flex items-center gap-2 text-base text-fg">
                                Descarga automática {b.activo ? <Badge tono="ok">Encendida · cada minuto</Badge> : <Badge>Apagada</Badge>}
                            </span>
                            <BotonConfirmado
                                variante="secundario"
                                titulo={b.activo ? '¿Apagar la descarga automática?' : '¿Encender la descarga automática?'}
                                descripcion={
                                    b.activo
                                        ? 'El sistema deja de leer las cuentas cada minuto; podrás seguir descargando con el botón.'
                                        : 'El sistema leerá las cuentas cada minuto y registrará los correos nuevos.'
                                }
                                confirmar={b.activo ? 'Apagar' : 'Encender'}
                                cargando={enviando === '/buzon/activar'}
                                onConfirmar={(cerrar) => post('/buzon/activar', { activo: !b.activo }, cerrar)}
                            >
                                {b.activo ? 'Apagar' : 'Encender'}
                            </BotonConfirmado>
                        </div>
                    </div>
                </Card>

                <Card
                    titulo="Cuentas de correo"
                    acciones={
                        b.cliente_configurado && (
                            <a href="/buzon/conectar" className={botonClases({ variante: b.cuentas.length ? 'secundario' : 'primario' })}>
                                <IcoAgregar />
                                Agregar cuenta de Google
                            </a>
                        )
                    }
                >
                    {!b.cliente_configurado && (
                        <p className="mb-3 text-base text-danger">Falta el cliente de Google (GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en el .env).</p>
                    )}
                    {b.cuentas.length === 0 ? (
                        <div className="flex flex-col gap-2 text-base text-fg-muted">
                            {b.driver === 'directorio' && (
                                <p>
                                    <Badge tono="aviso">Modo de prueba: carpeta local</Badge>
                                </p>
                            )}
                            <p>Agrega las cuentas a las que llegan los trámites. Puedes agregar varias: todas se descargan en el mismo sistema.</p>
                            <p className="text-sm">
                                Antes, esta dirección debe estar en «URI de redirección autorizados» del cliente de Google: <code className="select-all">{b.redireccion}</code>
                            </p>
                        </div>
                    ) : (
                        <ul className="flex flex-col divide-y divide-separador">
                            {b.cuentas.map((c) => (
                                <li key={c.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-2 text-base font-semibold text-fg">
                                            <IcoCorreo className="text-primary-600" />
                                            {c.cuenta}
                                            {c.principal && <Badge tono="ok">Principal · envía los documentos</Badge>}
                                        </p>
                                        <p className="text-sm text-fg-muted">
                                            {c.descargados} descargados · <UltimaLectura lectura={c.ultima_lectura} />
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        {!c.principal && (
                                            <BotonConfirmado
                                                variante="secundario"
                                                titulo="¿Hacerla principal?"
                                                descripcion={`Los documentos aprobados saldrán desde ${c.cuenta}.`}
                                                confirmar="Hacer principal"
                                                cargando={enviando === `/buzon/cuentas/${c.id}/principal`}
                                                onConfirmar={(cerrar) => post(`/buzon/cuentas/${c.id}/principal`, {}, cerrar)}
                                            >
                                                Hacer principal
                                            </BotonConfirmado>
                                        )}
                                        <BotonConfirmado
                                            variante="secundario"
                                            peligro
                                            titulo="¿Quitar esta cuenta?"
                                            descripcion={`El sistema deja de leer ${c.cuenta}${c.principal ? ' y de enviar desde ella' : ''}. Lo ya descargado se conserva.`}
                                            confirmar="Quitar"
                                            cargando={enviando === `/buzon/cuentas/${c.id}/quitar`}
                                            onConfirmar={(cerrar) => post(`/buzon/cuentas/${c.id}/quitar`, {}, cerrar)}
                                        >
                                            Quitar
                                        </BotonConfirmado>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AppShell>
    );
}
