import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/Button';

export default function Login() {
    const { app } = usePage().props;
    const [entrando, setEntrando] = useState(false);

    const entrarDesarrollo = () =>
        router.post('/dev/entrar', {}, { onStart: () => setEntrando(true), onFinish: () => setEntrando(false) });

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

                    {app.local && (
                        <div className="mt-6 border-t border-border pt-4">
                            <Button className="w-full" cargando={entrando} onClick={entrarDesarrollo}>
                                Entrar como superadmin (solo desarrollo)
                            </Button>
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
