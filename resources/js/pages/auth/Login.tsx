import { IcoAlerta } from '@/components/ui/iconos';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button, botonClases } from '@/components/ui/Button';
import { Select } from '@/components/ui/Select';
import { cn } from '@/lib/cn';
import type { Opcion } from '@/types';

type Props = { google: boolean; rolesDesarrollo: Opcion<string>[]; personasDesarrollo: Opcion<number>[] };

export default function Login({ google, rolesDesarrollo, personasDesarrollo }: Props) {
    const { props, flash } = usePage();
    // Aquí no hay AppShell ni Toaster: el rechazo de Google se muestra en la tarjeta.
    const error = flash.toast?.tipo === 'error' ? flash.toast.mensaje : null;
    const [entrando, setEntrando] = useState<string | null>(null);

    const [persona, setPersona] = useState('');

    const entrar = (rol: string) =>
        router.post('/dev/entrar', { rol }, { onStart: () => setEntrando(rol), onFinish: () => setEntrando(null) });
    const entrarComo = () =>
        router.post('/dev/entrar', { usuario_id: Number(persona) }, { onStart: () => setEntrando('persona'), onFinish: () => setEntrando(null) });

    return (
        <>
            <Head title="Iniciar sesión" />
            <main className="flex min-h-screen items-center justify-center bg-app p-4">
                <section className="w-full max-w-sm rounded-hoja bg-surface p-8 shadow-flotante">
                    <h1 className="text-xl font-bold tracking-tight text-fg">{props.app.nombre}</h1>
                    <p className="mt-1 text-base text-fg-muted">Ingresa con tu cuenta institucional de Google.</p>

                    {error && (
                        <p role="alert" className="mt-4 flex items-start gap-2 text-base text-danger">
                            <IcoAlerta className="shrink-0" />
                            {error}
                        </p>
                    )}

                    {/* Enlace y no visita de Inertia: Google exige navegar a su página. */}
                    {google ? (
                        <a href="/auth/google" className={cn(botonClases({ variante: 'primario' }), 'mt-6 w-full')}>
                            Continuar con Google
                        </a>
                    ) : (
                        <>
                            <Button variante="primario" className="mt-6 w-full" disabled>
                                Continuar con Google
                            </Button>
                            <p className="mt-2 text-sm text-fg-muted">El ingreso con Google aún no está configurado.</p>
                        </>
                    )}

                    {rolesDesarrollo.length > 0 && (
                        <div className="mt-6 border-t border-separador pt-5">
                            <p className="mb-2 text-sm font-semibold text-fg-muted">Solo desarrollo: entrar como</p>
                            <div className="grid grid-cols-2 gap-2">
                                {rolesDesarrollo.map((rol) => (
                                    <Button key={rol.value} cargando={entrando === rol.value} disabled={entrando !== null} onClick={() => entrar(rol.value)}>
                                        {rol.label}
                                    </Button>
                                ))}
                            </div>
                            {personasDesarrollo.length > 0 && (
                                <div className="mt-3 flex gap-2">
                                    <Select
                                        aria-label="Persona"
                                        className="min-w-0 flex-1"
                                        vacia="O elige una persona"
                                        opciones={personasDesarrollo}
                                        value={persona}
                                        onChange={(e) => setPersona(e.target.value)}
                                    />
                                    <Button cargando={entrando === 'persona'} disabled={!persona || entrando !== null} onClick={entrarComo}>
                                        Entrar
                                    </Button>
                                </div>
                            )}
                        </div>
                    )}
                </section>
            </main>
        </>
    );
}
