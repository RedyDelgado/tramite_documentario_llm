import { IcoEditar } from '@/components/ui/iconos';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import Formulario, { type Umbrales } from './Form';

const porcentaje = (v: number) => `${Math.round(v * 100)} %`;

/** Umbrales vigentes del semáforo y de la IA; se editan en un modal. El menú los llama «Semáforo e IA», que dice qué se ajusta. */
export default function UmbralesIndex({ umbrales: u }: { umbrales: Umbrales }) {
    const [editando, setEditando] = useState(false);

    return (
        <AppShell>
            <PageHeader
                titulo="Semáforo e IA"
                descripcion="Cuándo un trámite pasa a «Por vencer» y desde qué seguridad la IA propone un área. Los cambios rigen de inmediato."
                acciones={
                    <Button variante="primario" icono={<IcoEditar />} onClick={() => setEditando(true)}>
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
                            { etiqueta: 'Modo', valor: u.ia_modo === 'activo' ? 'Activo: su propuesta aparece al derivar' : 'Sombra: propone en silencio para medir cuánto acierta' },
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
