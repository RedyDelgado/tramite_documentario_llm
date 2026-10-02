import { Edit20Regular } from '@fluentui/react-icons';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Formulario, { type Umbrales } from './Form';

const porcentaje = (v: number) => `${Math.round(v * 100)} %`;

/** Umbrales vigentes del semáforo y de la IA; se editan en un modal. */
export default function UmbralesIndex({ umbrales: u }: { umbrales: Umbrales }) {
    const [editando, setEditando] = useState(false);

    return (
        <AppShell>
            <PageHeader
                titulo="Umbrales"
                descripcion="Rigen desde el siguiente cálculo, sin desplegar."
                acciones={
                    <Button variante="primario" icono={<Edit20Regular />} onClick={() => setEditando(true)}>
                        Editar
                    </Button>
                }
            />
            <div className="grid items-start gap-4 lg:grid-cols-2">
                <Card titulo="Semáforo: pasa a amarillo">
                    <DetalleLista
                        items={[
                            { etiqueta: 'Plazo restante', valor: `Menos del ${u.porcentaje_amarillo} %` },
                            { etiqueta: 'Sin movimiento', valor: `Más de ${u.dias_sin_movimiento} días` },
                        ]}
                    />
                </Card>
                <Card titulo="Clasificación con IA">
                    <DetalleLista
                        items={[
                            { etiqueta: 'Modo', valor: u.ia_modo === 'activo' ? 'Activo: propone al derivar' : 'Sombra: mide sin mostrar' },
                            { etiqueta: 'Propone desde', valor: porcentaje(u.ia_umbral_sugerencia) },
                            { etiqueta: 'Confianza alta desde', valor: porcentaje(u.ia_umbral_alta) },
                        ]}
                    />
                </Card>
            </div>
            {editando && <Formulario umbrales={u} onCerrar={() => setEditando(false)} />}
        </AppShell>
    );
}
