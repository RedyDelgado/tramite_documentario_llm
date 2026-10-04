import { IcoBuscar, IcoFlechaAbajo16, IcoPanelLateral, IcoSalir } from '@/components/ui/iconos';
import { Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { IconButton } from '@/components/ui/IconButton';
import { Menu } from '@/components/ui/Menu';
import { Toaster } from '@/components/ui/Toaster';
import { Tooltip } from '@/components/ui/Tooltip';
import { campoClases } from '@/components/ui/campo';
import { cn } from '@/lib/cn';
import { esActiva, navegacionVisible, PERMISOS_EXPEDIENTES, tieneAlguno, type ItemNav } from '@/lib/navegacion';

const CLAVE_COLAPSADA = 'navegacion-colapsada';

const ROLES: Record<string, string> = {
    superadmin: 'Superadmin',
    director: 'Director',
    administrativo: 'Mesa de partes',
    coordinador: 'Coordinador',
    otros: 'Personal',
};

// Sin preferencia guardada, en ventanas angostas la barra lateral arranca colapsada para dejar sitio al contenido.
function leerColapsada(): boolean {
    try {
        const guardada = localStorage.getItem(CLAVE_COLAPSADA);
        if (guardada !== null) return guardada === '1';
    } catch {
        // Sin almacenamiento se decide por el ancho.
    }

    return typeof window !== 'undefined' && window.innerWidth < 1024;
}

// Avatar con nombre y primer apellido: «Dr. Julio César Paredes Loayza» → «JP» (sin títulos como «Dr.» o «Lic.»).
function iniciales(nombre: string): string {
    const partes = nombre.split(/\s+/).filter((p) => p !== '' && !p.endsWith('.'));
    const apellido = partes.length >= 3 ? partes[partes.length - 2] : partes[partes.length - 1];

    return ((partes[0]?.[0] ?? '') + (partes.length > 1 ? (apellido?.[0] ?? '') : '')).toUpperCase();
}

function ItemNavegacion({ item, activa, colapsada }: { item: ItemNav; activa: boolean; colapsada: boolean }) {
    // Cápsula translúcida sobre el fondo de marca: blanco al 15 % la selección, al 10 % el hover.
    const clases = cn(
        'flex h-9 items-center gap-3 rounded-full text-base transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-on-primary',
        colapsada ? 'mx-auto w-10 justify-center' : 'mx-3 px-3',
        activa ? 'bg-barra-activo font-semibold text-on-primary' : 'text-barra-suave hover:bg-barra-hover hover:text-on-primary',
    );
    const contenido = (
        <>
            <span className="shrink-0">{item.icono}</span>
            <span className={colapsada ? 'sr-only' : 'truncate'}>{item.etiqueta}</span>
        </>
    );
    const enlace = item.externo ? (
        <a href={item.href} className={clases} aria-current={activa ? 'page' : undefined}>
            {contenido}
        </a>
    ) : (
        <Link href={item.href} className={clases} aria-current={activa ? 'page' : undefined}>
            {contenido}
        </Link>
    );

    return colapsada ? <Tooltip texto={item.etiqueta}>{enlace}</Tooltip> : enlace;
}

/** Marco de toda página autenticada: barra lateral de marca con la persona y sus opciones, y barra de búsqueda translúcida. */
export function AppShell({ children }: { children: ReactNode }) {
    const { props, url } = usePage();
    const [colapsada, setColapsada] = useState(leerColapsada);
    const grupos = navegacionVisible(props);
    const usuario = props.auth.user;
    const rol = props.auth.roles.map((r) => ROLES[r] ?? r).join(' · ');

    const alternar = () => {
        setColapsada((c) => {
            try {
                localStorage.setItem(CLAVE_COLAPSADA, c ? '0' : '1');
            } catch {
                // Sin almacenamiento la preferencia solo dura la sesión.
            }
            return !c;
        });
    };

    const avatar = usuario && (
        <span aria-hidden className="flex size-9 shrink-0 items-center justify-center rounded-full bg-on-primary text-base font-bold text-primary-700 shadow-card">
            {iniciales(usuario.name)}
        </span>
    );

    return (
        <div className="min-h-screen bg-app">
            <nav
                id="navegacion"
                aria-label="Navegación principal"
                className={cn(
                    'fixed inset-y-0 left-0 z-40 flex flex-col overflow-x-hidden bg-[image:var(--barra-fondo)] text-on-primary transition-[width]',
                    colapsada ? 'w-16' : 'w-64',
                )}
            >
                <Link
                    href="/"
                    className={cn('flex h-14 shrink-0 items-center gap-2 font-titulos text-md font-bold tracking-tight', colapsada ? 'justify-center' : 'px-6')}
                >
                    <span aria-hidden className="flex size-7 items-center justify-center rounded-control bg-barra-activo text-sm">
                        TD
                    </span>
                    <span className={colapsada ? 'sr-only' : 'truncate'}>{props.app.nombre}</span>
                </Link>

                {usuario && (
                    <div className={cn('mb-3', colapsada ? 'flex justify-center' : 'mx-3')}>
                        <Menu
                            disparador={
                                <button
                                    type="button"
                                    aria-label={`${usuario.name}: opciones de la cuenta`}
                                    className={cn(
                                        'flex w-full cursor-pointer items-center gap-3 rounded-card text-left transition-colors hover:bg-barra-hover focus-visible:outline-2 focus-visible:outline-on-primary',
                                        colapsada ? 'justify-center p-1' : 'bg-barra-hover p-2.5',
                                    )}
                                >
                                    {avatar}
                                    {!colapsada && (
                                        <>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-base font-semibold">{usuario.name}</span>
                                                <span className="block truncate text-sm text-barra-suave">{rol}</span>
                                            </span>
                                            <IcoFlechaAbajo16 className="shrink-0 text-barra-suave" />
                                        </>
                                    )}
                                </button>
                            }
                            encabezado={
                                <>
                                    <p className="text-base font-semibold text-fg">{usuario.name}</p>
                                    <p className="text-sm text-fg-muted">{usuario.email}</p>
                                </>
                            }
                            items={[{ etiqueta: 'Cerrar sesión', icono: <IcoSalir />, onSelect: () => router.post('/logout') }]}
                        />
                    </div>
                )}

                <div className="flex-1 overflow-y-auto pb-4">
                    {grupos.map((grupo, i) => (
                        <div key={grupo.titulo ?? i} className={cn(i > 0 && 'mt-5')}>
                            {grupo.titulo &&
                                (colapsada ? (
                                    <div role="separator" className="mx-4 mb-2 h-px bg-barra-activo" />
                                ) : (
                                    <p className="px-6 pb-1.5 text-sm font-semibold tracking-wide text-barra-suave uppercase">{grupo.titulo}</p>
                                ))}
                            <ul className="flex flex-col gap-0.5">
                                {grupo.items.map((item) => (
                                    <li key={item.href}>
                                        <ItemNavegacion
                                            item={item}
                                            activa={[item.href, ...(item.tambien ?? [])].some((h) => esActiva(h, url))}
                                            colapsada={colapsada}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </nav>

            <header
                className={cn(
                    'fixed top-0 right-0 z-30 flex h-14 items-center gap-3 border-b border-separador bg-material px-4 backdrop-blur-xl backdrop-saturate-150 transition-[left]',
                    colapsada ? 'left-16' : 'left-64',
                )}
            >
                <IconButton
                    icono={<IcoPanelLateral />}
                    etiqueta={colapsada ? 'Mostrar la barra lateral' : 'Ocultar la barra lateral'}
                    aria-expanded={!colapsada}
                    aria-controls="navegacion"
                    onClick={alternar}
                />
                {tieneAlguno(props.auth.can, PERMISOS_EXPEDIENTES) && (
                    <form
                        role="search"
                        className="w-full max-w-lg"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const q = new FormData(e.currentTarget).get('q')?.toString().trim();
                            router.get('/expedientes', q ? { q } : {});
                        }}
                    >
                        {/* Campo de búsqueda relleno, sin borde, como el de las barras de herramientas de Apple. */}
                        <div className="relative">
                            <IcoBuscar className="pointer-events-none absolute inset-y-0 left-3 my-auto text-fg-muted" />
                            <input
                                key={url}
                                name="q"
                                type="search"
                                aria-label="Buscar expedientes"
                                placeholder="Buscar por asunto, remitente, código o texto"
                                defaultValue={new URLSearchParams(url.split('?')[1] ?? '').get('q') ?? ''}
                                className={cn(campoClases, 'h-9 border-transparent bg-relleno pl-9 hover:border-transparent focus:bg-surface')}
                            />
                        </div>
                    </form>
                )}
            </header>

            <main className={cn('pt-14 transition-[padding]', colapsada ? 'pl-16' : 'pl-64')}>
                <div className="mx-auto max-w-[96rem] px-8 py-6">{children}</div>
            </main>

            <Toaster />
        </div>
    );
}
