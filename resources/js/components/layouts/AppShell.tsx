import { Navigation20Regular, PersonCircle20Regular, Search20Regular, SignOut20Regular } from '@fluentui/react-icons';
import { Link, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { IconButton } from '@/components/ui/IconButton';
import { Input } from '@/components/ui/Input';
import { Menu } from '@/components/ui/Menu';
import { Toaster } from '@/components/ui/Toaster';
import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';
import { esActiva, navegacionVisible, type ItemNav } from '@/lib/navegacion';

const CLAVE_COLAPSADA = 'navegacion-colapsada';

function leerColapsada(): boolean {
    try {
        return localStorage.getItem(CLAVE_COLAPSADA) === '1';
    } catch {
        return false;
    }
}

function ItemNavegacion({ item, activa, colapsada }: { item: ItemNav; activa: boolean; colapsada: boolean }) {
    const clases = cn(
        'relative flex h-9 items-center gap-3 text-base transition-colors',
        colapsada ? 'justify-center' : 'px-4',
        activa
            ? 'bg-primary-50 font-semibold text-primary-700 before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-full before:bg-primary-600'
            : 'text-fg hover:bg-surface-subtle',
    );
    const contenido = (
        <>
            <span className={cn('shrink-0', activa ? 'text-primary-600' : 'text-fg-muted')}>{item.icono}</span>
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

/** Marco de toda página autenticada: barra superior de 48 px, navegación lateral colapsable y avisos. */
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
            <header className="fixed inset-x-0 top-0 z-40 flex h-12 items-center gap-2 border-b border-border bg-surface px-2">
                <IconButton
                    icono={<Navigation20Regular />}
                    etiqueta={colapsada ? 'Expandir navegación' : 'Contraer navegación'}
                    aria-expanded={!colapsada}
                    aria-controls="navegacion"
                    onClick={alternar}
                />
                <Link href="/" className="shrink-0 rounded-control px-1 text-md font-semibold text-fg">
                    {props.app.nombre}
                </Link>
                <div className="mx-auto hidden w-full max-w-xl md:block">
                    <Input
                        iconoInicio={<Search20Regular />}
                        aria-label="Búsqueda global"
                        placeholder="Buscar expedientes (disponible en la fase 1)"
                        disabled
                    />
                </div>
                {usuario && (
                    <div className="ml-auto md:ml-0">
                        <Menu
                            disparador={
                                <button
                                    type="button"
                                    className="flex h-8 cursor-pointer items-center gap-2 rounded-control px-2 text-base text-fg hover:bg-primary-50"
                                >
                                    <PersonCircle20Regular className="text-fg-muted" />
                                    <span className="hidden max-w-48 truncate sm:inline">{usuario.name}</span>
                                </button>
                            }
                            encabezado={
                                <>
                                    <p className="text-base font-semibold text-fg">{usuario.name}</p>
                                    <p className="text-sm text-fg-muted">{usuario.email}</p>
                                </>
                            }
                            items={[{ etiqueta: 'Cerrar sesión', icono: <SignOut20Regular />, onSelect: () => router.post('/logout') }]}
                        />
                    </div>
                )}
            </header>

            <nav
                id="navegacion"
                aria-label="Navegación principal"
                className={cn(
                    'fixed top-12 bottom-0 left-0 z-30 overflow-x-hidden overflow-y-auto border-r border-border bg-surface py-2 transition-[width]',
                    colapsada ? 'w-12' : 'w-60',
                )}
            >
                {grupos.map((grupo, i) => (
                    <div key={grupo.titulo ?? i} className={cn(i > 0 && 'mt-2')}>
                        {grupo.titulo &&
                            (colapsada ? (
                                <div role="separator" className="mx-3 my-2 h-px bg-border" />
                            ) : (
                                <p className="px-4 pt-2 pb-1 text-sm font-semibold text-fg-muted">{grupo.titulo}</p>
                            ))}
                        <ul>
                            {grupo.items.map((item) => (
                                <li key={item.href}>
                                    <ItemNavegacion item={item} activa={esActiva(item.href, url)} colapsada={colapsada} />
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            <main className={cn('pt-12 transition-[padding]', colapsada ? 'pl-12' : 'pl-60')}>
                <div className="p-6">{children}</div>
            </main>

            <Toaster />
        </div>
    );
}
