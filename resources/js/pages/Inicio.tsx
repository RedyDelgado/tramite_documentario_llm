import { Board20Regular } from '@fluentui/react-icons';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';

export default function Inicio() {
    return (
        <AppShell>
            <PageHeader titulo="Inicio" descripcion="Resumen de expedientes, semáforos y pendientes." />
            <Card>
                <EmptyState
                    icono={<Board20Regular />}
                    titulo="El panel de KPIs llega en la fase 2"
                    descripcion="Aquí se verán los expedientes por estado y semáforo, los pendientes por área y los tiempos de atención."
                />
            </Card>
        </AppShell>
    );
}
