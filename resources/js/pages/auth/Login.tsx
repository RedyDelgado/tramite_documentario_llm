import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';

const ROLES: Record<string, string> = {
    superadmin: 'Superadmin',
    director: 'Director',
    administrativo: 'Administrativo',
    coordinador: 'Coordinador',
};

export default function Login({ rolesDesarrollo }: { rolesDesarrollo: string[] }) {
    const { app } = usePage().props;
    const [entrando, setEntrando] = useState<string | null>(null);

    const entrar = (rol: string) =>
        router.post('/dev/entrar', { rol }, { onStart: () => setEntrando(rol), onFinish: () => setEntrando(null) });

    return (
        <>
            <Head title="Iniciar sesión" />
            <main className="flex min-h-screen items-center justify-center bg-app p-4">
                <section className="w-full max-w-sm rounded-card border border-border bg-surface p-6 shadow-card">
                    <h1 className="text-lg font-semibold text-fg">{app.nombre}</h1>
                    <p className="mt-1 text-base text-fg-muted">Ingresa con tu cuenta institucional de Google.</p>

                    <Button variante="primario" className="mt-6 w-full" disabled>
                        Continuar con Google
                    </Button>
                    <p className="mt-2 text-sm text-fg-muted">El inicio de sesión con Google se habilita en la fase 2.</p>

                    {rolesDesarrollo.length > 0 && (
                        <div className="mt-6 border-t border-border pt-4">
                            <p className="mb-2 text-sm font-semibold text-fg-muted">Solo desarrollo: entrar como</p>
                            <div className="grid grid-cols-2 gap-2">
                                {rolesDesarrollo.map((rol) => (
                                    <Button key={rol} cargando={entrando === rol} disabled={entrando !== null} onClick={() => entrar(rol)}>
                                        {ROLES[rol] ?? rol}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
