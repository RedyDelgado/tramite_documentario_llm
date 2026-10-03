import { IcoBuscar, IcoPanelLateral, IcoSalir } from '@/components/ui/iconos';
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
    // Selección en cápsula redondeada, como la lista lateral de las apps de Apple (HIG, Sidebars).
    const clases = cn(
        'flex h-8 items-center gap-2.5 rounded-control text-base transition-colors',
        colapsada ? 'mx-auto w-9 justify-center' : 'mx-2 px-2.5',
        activa ? 'bg-primary-100 font-medium text-primary-900' : 'text-fg hover:bg-relleno',
    );
    const contenido = (
        <>
            <span className={cn('shrink-0', activa ? 'text-primary-600' : 'text-primary-600/80')}>{item.icono}</span>
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

/** Marco de toda página autenticada: barra y navegación translúcidas sobre el contenido (HIG, Materials), y avisos. */
export function AppShell({ children }: { children: ReactNode }) {
    const { props, url } = usePage();
    const [colapsada, setColapsada] = useState(leerColapsada);
    const grupos = navegacionVisible(props);
    const usuario = props.auth.user;

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

    return (
        <div className="min-h-screen bg-app">
            <header className="fixed inset-x-0 top-0 z-40 flex h-13 items-center gap-3 border-b border-separador bg-material px-3 backdrop-blur-xl backdrop-saturate-150">
                <IconButton
                    icono={<IcoPanelLateral />}
                    etiqueta={colapsada ? 'Mostrar la barra lateral' : 'Ocultar la barra lateral'}
                    aria-expanded={!colapsada}
                    aria-controls="navegacion"
                    onClick={alternar}
                />
                <Link href="/" className="shrink-0 rounded-control px-1 text-md font-semibold tracking-tight text-fg">
                    {props.app.nombre}
                </Link>
                {tieneAlguno(props.auth.can, PERMISOS_EXPEDIENTES) ? (
                    <form
                        role="search"
                        className="mx-auto hidden w-full max-w-lg md:block"
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
                ) : (
                    <div className="mx-auto" />
                )}
                {usuario && (
                    <div className="ml-auto md:ml-0">
                        <Menu
                            disparador={
                                <button
                                    type="button"
                                    className="flex h-9 cursor-pointer items-center gap-2 rounded-full py-1 pr-3 pl-1 text-base text-fg transition-colors hover:bg-relleno"
                                >
                                    <span aria-hidden className="flex size-7 items-center justify-center rounded-full bg-primary-600 text-sm font-semibold text-on-primary">
                                        {iniciales(usuario.name)}
                                    </span>
                                    <span className="hidden max-w-48 truncate sm:inline">{usuario.name}</span>
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
            </header>

            <nav
                id="navegacion"
                aria-label="Navegación principal"
                className={cn(
                    'fixed top-13 bottom-0 left-0 z-30 overflow-x-hidden overflow-y-auto border-r border-separador bg-material py-3 backdrop-blur-xl backdrop-saturate-150 transition-[width]',
                    colapsada ? 'w-14' : 'w-64',
                )}
            >
                {grupos.map((grupo, i) => (
                    <div key={grupo.titulo ?? i} className={cn(i > 0 && 'mt-4')}>
                        {grupo.titulo &&
                            (colapsada ? (
                                <div role="separator" className="mx-3 mb-2 h-px bg-separador" />
                            ) : (
                                <p className="px-5 pb-1 text-sm font-semibold text-fg-muted">{grupo.titulo}</p>
                            ))}
                        <ul className="flex flex-col gap-0.5">
                            {grupo.items.map((item) => (
                                <li key={item.href}>
                                    <ItemNavegacion item={item} activa={esActiva(item.href, url)} colapsada={colapsada} />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            <main className={cn('pt-13 transition-[padding]', colapsada ? 'pl-14' : 'pl-64')}>
                <div className="mx-auto max-w-[96rem] px-8 py-7">{children}</div>
            </main>

            <Toaster />
        </div>
    );
}
