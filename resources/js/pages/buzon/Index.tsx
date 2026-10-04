import { router } from '@inertiajs/react';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { IcoCorreo, IcoDescargar } from '@/components/ui/iconos';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';

/** BuzonService::estado. */
type Buzon = {
    conectado: boolean;
    cuenta: string | null;
    driver: 'gmail' | 'directorio';
    activo: boolean;
    ultima_lectura: {
        fecha: string;
        procesados: number;
        fallidos: number;
        error: string | null;
    } | null;
    desde: string;
    inicio_operacion: string | null;
    cliente_configurado: boolean;
    redireccion: string;
};

export default function BuzonIndex({ buzon: b }: { buzon: Buzon }) {
    const [enviando, setEnviando] = useState<string | null>(null);
    const post = (ruta: string, datos: Record<string, boolean>, cerrar?: () => void) =>
        router.post(ruta, datos, {
            preserveScroll: true,
            onStart: () => setEnviando(ruta),
            onFinish: () => (setEnviando(null), cerrar?.()),
        });
    const lectura = b.ultima_lectura;

    return (
        <AppShell>
            <PageHeader
                titulo="Buzón central"
                descripcion="La cuenta de Google de la que el sistema descarga los correos: cada correo nuevo entra como expediente «Por revisar»."
            />
            <div className="flex max-w-3xl flex-col gap-4">
                <Card titulo="Conexión">
                    <div className="flex flex-col gap-4">
                        <DetalleLista
                            items={[
                                {
                                    etiqueta: 'Cuenta',
                                    valor: b.conectado ? (
                                        <Badge tono="ok" icono={<IcoCorreo />}>
                                            {b.cuenta}
                                        </Badge>
                                    ) : b.driver === 'directorio' ? (
                                        <Badge tono="aviso">Modo de prueba: carpeta local</Badge>
                                    ) : (
                                        <Badge>Configurada en el .env</Badge>
                                    ),
                                },
                                {
                                    etiqueta: 'Descarga automática',
                                    valor: b.activo ? <Badge tono="ok">Encendida · cada minuto</Badge> : <Badge>Apagada</Badge>,
                                },
                                {
                                    etiqueta: 'Lee desde',
                                    valor: formatearFecha(b.desde),
                                },
                                {
                                    etiqueta: 'Inicio de operación',
                                    valor: b.inicio_operacion
                                        ? `${formatearFecha(b.inicio_operacion)} (lo anterior entra como histórico)`
                                        : 'Sin fijar: todo entra como nuevo',
                                },
                            ]}
                        />
                        {!b.cliente_configurado && (
                            <p className="text-base text-danger">Falta el cliente de Google (GOOGLE_CLIENT_ID y GOOGLE_CLIENT_SECRET en el .env).</p>
                        )}
                        {!b.conectado && (
                            <p className="text-sm text-fg-muted">
                                Antes de conectar, esta dirección debe estar en «URI de redirección autorizados» del cliente de Google:{' '}
                                <code className="select-all">{b.redireccion}</code>
                            </p>
                        )}
                        <div className="flex flex-wrap gap-2">
                            {b.cliente_configurado && (
                                <a
                                    href="/buzon/conectar"
                                    className={botonClases({
                                        variante: b.conectado ? 'secundario' : 'primario',
                                    })}
                                >
                                    <IcoCorreo />
                                    {b.conectado ? 'Cambiar de cuenta' : 'Conectar con Google'}
                                </a>
                            )}
                            <BotonConfirmado
                                variante="secundario"
                                titulo={b.activo ? '¿Apagar la descarga automática?' : '¿Encender la descarga automática?'}
                                descripcion={
                                    b.activo
                                        ? 'El sistema deja de leer el buzón cada minuto; podrás seguir descargando a mano.'
                                        : 'El sistema leerá el buzón cada minuto y registrará los correos nuevos como «Por revisar».'
                                }
                                confirmar={b.activo ? 'Apagar' : 'Encender'}
                                cargando={enviando === '/buzon/activar'}
                                onConfirmar={(cerrar) => post('/buzon/activar', { activo: !b.activo }, cerrar)}
                            >
                                {b.activo ? 'Apagar descarga automática' : 'Encender descarga automática'}
                            </BotonConfirmado>
                            {b.conectado && (
                                <BotonConfirmado
                                    variante="secundario"
                                    titulo="¿Desconectar el buzón?"
                                    descripcion="El sistema deja de leer y de enviar desde esta cuenta. Lo ya descargado se conserva."
                                    confirmar="Desconectar"
                                    peligro
                                    cargando={enviando === '/buzon/desconectar'}
                                    onConfirmar={(cerrar) => post('/buzon/desconectar', {}, cerrar)}
                                >
                                    Desconectar
                                </BotonConfirmado>
                            )}
                        </div>
                    </div>
                </Card>

                <Card titulo="Descarga">
                    <div className="flex flex-col gap-4">
                        {lectura ? (
                            <DetalleLista
                                items={[
                                    {
                                        etiqueta: 'Última lectura',
                                        valor: formatearFechaHora(lectura.fecha),
                                    },
                                    {
                                        etiqueta: 'Correos descargados',
                                        valor: lectura.procesados,
                                    },
                                    {
                                        etiqueta: 'Con error',
                                        valor: lectura.fallidos ? <Badge tono="peligro">{lectura.fallidos}</Badge> : 0,
                                    },
                                    ...(lectura.error
                                        ? [
                                              {
                                                  etiqueta: 'No se pudo leer',
                                                  valor: <span className="text-danger">{lectura.error}</span>,
                                              },
                                          ]
                                        : []),
                                ]}
                            />
                        ) : (
                            <p className="text-base text-fg-muted">Aún no se leyó el buzón.</p>
                        )}
                        <div>
                            <Button
                                variante="primario"
                                icono={<IcoDescargar />}
                                cargando={enviando === '/buzon/descargar'}
                                onClick={() => post('/buzon/descargar', {})}
                            >
                                Descargar ahora
                            </Button>
                        </div>
                        <p className="text-sm text-fg-muted">
                            Lee hasta 50 correos por vez; lo ya descargado no se repite. Si hay más, vuelve a pulsar o enciende la descarga automática.
                        </p>
                    </div>
                </Card>
            </div>
        </AppShell>
    );
}
