import { Link } from '@inertiajs/react';
import type { KeyboardEvent, ReactNode } from 'react';
import { Button, botonClases } from '@/components/ui/Button';
import { AppShell } from './AppShell';
import { PageHeader } from './PageHeader';

type Props = {
    titulo: string;
    descripcion?: string;
    volverA: string;
    onEnviar: () => void;
    procesando: boolean;
    textoGuardar?: string;
    children: ReactNode;
};

/**
 * Plantilla de todo formulario de alta o edición. Enter en un campo no guarda (evita envíos
 * accidentales); Ctrl/Cmd + Enter sí. La validación la hace Laravel y llega como `errors`.
 */
export function FormPage({ titulo, descripcion, volverA, onEnviar, procesando, textoGuardar = 'Guardar', children }: Props) {
    const alTeclear = (e: KeyboardEvent<HTMLFormElement>) => {
        if (e.key !== 'Enter') return;
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            e.currentTarget.requestSubmit();
        } else if (e.target instanceof HTMLInputElement) {
            e.preventDefault();
        }
    };

    return (
        <AppShell>
            <PageHeader titulo={titulo} descripcion={descripcion} />
            <form
                noValidate
                onKeyDown={alTeclear}
                onSubmit={(e) => {
                    e.preventDefault();
                    onEnviar();
                }}
                className="flex max-w-4xl flex-col gap-4"
            >
                {children}
                <div className="flex items-center justify-end gap-2">
                    <span className="mr-auto text-sm text-fg-muted">Ctrl + Enter para guardar</span>
                    <Link href={volverA} className={botonClases()}>
                        Cancelar
                    </Link>
                    <Button type="submit" variante="primario" cargando={procesando}>
                        {textoGuardar}
                    </Button>
                </div>
            </form>
        </AppShell>
    );
}
