import { IcoBuscar, IcoCampana, IcoExpedientes, IcoIa, IcoRespondido16, IcoFlechaAbajo16, IcoPanelLateral, IcoSalir } from '@/components/ui/iconos';
import { Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { AsistenteIa } from '@/components/domain/AsistenteIa';
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
    // Selección con fondo celeste, texto en negrita y una barra de marca pegada al borde de la barra lateral.
    const clases = cn(
        'relative flex h-10 items-center gap-3 rounded-control text-base transition-colors',
        colapsada ? 'mx-auto w-10 justify-center' : 'mx-3 px-3',
        activa
            ? 'bg-primary-50 font-bold text-fg before:absolute before:inset-y-2 before:-left-3 before:w-1 before:rounded-r-full before:bg-primary-600'
            : 'text-fg-muted hover:bg-relleno hover:text-fg',
    );
    const contenido = (
        <>
            <span className={cn('shrink-0', activa && 'text-primary-600')}>{item.icono}</span>
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

/** Marco de toda página autenticada: barra lateral y barra superior como paneles flotantes redondeados sobre el fondo gris. */
export function AppShell({ children }: { children: ReactNode }) {
    const { props, url } = usePage();
    const [colapsada, setColapsada] = useState(leerColapsada);
    const grupos = navegacionVisible(props);
    const usuario = props.auth.user;
    // El chat con la IA es para quien gestiona todo lo que entra; la Policy lo vuelve a comprobar.
    const asistente = props.app.ia && props.auth.can.includes('expedientes.derivar');
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
        <span aria-hidden className="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-bold text-on-primary">
            {iniciales(usuario.name)}
        </span>
    );

    return (
        <div className="min-h-screen bg-app">
            <nav
                id="navegacion"
                aria-label="Navegación principal"
                className={cn(
                    'fixed inset-y-3 left-3 z-40 flex flex-col overflow-x-hidden rounded-panel bg-surface shadow-card transition-[width]',
                    colapsada ? 'w-16' : 'w-64',
                )}
            >
                <Link
                    href="/"
                    aria-label={colapsada ? props.app.nombre : undefined}
                    className={cn('flex h-16 shrink-0 items-center gap-2.5 font-titulos text-md font-bold tracking-tight text-fg', colapsada ? 'justify-center' : 'px-5')}
                >
                    <span aria-hidden className="flex size-9 shrink-0 items-center justify-center rounded-full bg-[image:var(--degradado-marca)] text-on-primary">
                        <IcoExpedientes />
                    </span>
                    {!colapsada && <span className="truncate">{props.app.nombre}</span>}
                </Link>

                <div className={cn('min-h-0 overflow-y-auto pb-4', !asistente && 'flex-1')}>
                    {grupos.map((grupo, i) => (
                        <div key={grupo.titulo ?? i} className={cn(i > 0 && 'mt-5')}>
                            {grupo.titulo &&
                                (colapsada ? (
                                    <div role="separator" className="mx-4 mb-2 h-px bg-separador" />
                                ) : (
                                    <p className="px-6 pb-1.5 text-sm font-semibold tracking-wide text-fg-muted uppercase">{grupo.titulo}</p>
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
                {asistente &&
                    (colapsada ? (
                        <div className="mt-auto flex justify-center pb-4">
                            <IconButton icono={<IcoIa />} etiqueta="Abrir el asistente IA" onClick={alternar} />
                        </div>
                    ) : (
                        <div className="flex min-h-72 flex-1 flex-col pb-3">
                            <AsistenteIa />
                        </div>
                    ))}
            </nav>

            <div className={cn('pr-3 transition-[padding]', colapsada ? 'pl-[5.5rem]' : 'pl-[17.5rem]')}>
                {/* El «before» tapa la franja de 12 px sobre la barra para que el contenido no se vea pasar por encima. */}
                <header className="sticky top-3 z-30 mt-3 flex h-16 items-center gap-3 rounded-panel bg-material px-4 shadow-card backdrop-blur-xl backdrop-saturate-150 before:absolute before:inset-x-0 before:-top-3 before:h-3 before:bg-app">
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
                    {usuario && (
                        <div className="ml-auto flex items-center gap-2">
                            {props.auth.can.includes('expedientes.derivar') && (
                                <Menu
                                    disparador={
                                        <button
                                            type="button"
                                            aria-label={`Avisos: ${props.respuestasNuevas.length} respuestas nuevas`}
                                            className="relative flex size-10 cursor-pointer items-center justify-center rounded-full text-fg-muted transition-colors hover:bg-relleno hover:text-fg focus-visible:outline-2 focus-visible:outline-primary-600"
                                        >
                                            <IcoCampana />
                                            {props.respuestasNuevas.length > 0 && (
                                                <span className="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-sm leading-none font-bold text-on-primary">
                                                    {props.respuestasNuevas.length}
                                                </span>
                                            )}
                                        </button>
                                    }
                                    encabezado={
                                        <>
                                            <p className="text-base font-semibold text-fg">Respuestas nuevas</p>
                                            <p className="text-sm text-fg-muted">
                                                {props.respuestasNuevas.length ? 'Las áreas respondieron por correo; revísalas y márcalas atendidas.' : 'No hay respuestas por revisar.'}
                                            </p>
                                        </>
                                    }
                                    items={props.respuestasNuevas.map((e) => ({
                                        etiqueta: `${e.numero_registro ?? 'Sin número'} · ${e.asunto.length > 48 ? `${e.asunto.slice(0, 48)}…` : e.asunto}`,
                                        icono: <IcoRespondido16 />,
                                        onSelect: () => router.visit(`/expedientes/${e.id}`),
                                    }))}
                                />
                            )}
                            <Menu
                                disparador={
                                    <button
                                        type="button"
                                        aria-label={`${usuario.name}: opciones de la cuenta`}
                                        className="flex h-10 cursor-pointer items-center gap-2.5 rounded-full py-1 pr-2 pl-1 text-left transition-colors hover:bg-relleno focus-visible:outline-2 focus-visible:outline-primary-600"
                                    >
                                        {avatar}
                                        <span className="hidden min-w-0 sm:block">
                                            <span className="block max-w-56 truncate text-base font-semibold text-fg">{usuario.name}</span>
                                            <span className="block max-w-56 truncate text-sm text-fg-muted">{rol}</span>
                                        </span>
                                        <IcoFlechaAbajo16 className="shrink-0 text-fg-muted" />
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

                <main className="mx-auto max-w-[96rem] px-5 py-6">{children}</main>
            </div>

            <Toaster />
        </div>
    );
}
