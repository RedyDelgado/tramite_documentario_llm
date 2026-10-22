import '../css/app.css';
import { createInertiaApp, router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';
import { avisar } from '@/components/ui/Toaster';

const nombre = import.meta.env.VITE_APP_NAME || 'Trámite Documentario';
const paginas = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

createInertiaApp({
    title: (titulo) => (titulo ? `${titulo} · ${nombre}` : nombre),
    resolve: (pagina) => {
        const cargar = paginas[`./pages/${pagina}.tsx`];
        if (!cargar) throw new Error(`Página Inertia inexistente: ${pagina}`);
        return cargar().then((m) => m.default);
    },
    setup({ el, App, props }) {
        if (el) createRoot(el).render(<App {...props} />);
    },
    progress: { color: 'var(--primary-600)', delay: 200 },
});

// Una respuesta que no es de Inertia (sesión vencida, error o tiempo agotado) se abría como un modal en blanco: va como aviso.
router.on('httpException', (evento) => {
    const { status } = evento.detail.response;
    evento.preventDefault();
    console.error('Respuesta HTTP no esperada', status);
    avisar({
        tipo: 'error',
        mensaje:
            status === 419
                ? 'La sesión venció. Vuelve a entrar y repite la acción.'
                : status === 403
                  ? 'No tienes permiso para esta acción.'
                  : status >= 502 && status <= 504
                    ? 'El servidor tardó demasiado en responder. Revisa si se guardó antes de repetirlo.'
                    : `Ocurrió un error en el servidor (${status}). Revisa si se guardó antes de repetirlo.`,
    });
    if (status === 419) window.location.reload();
});
router.on('networkError', (evento) => {
    evento.preventDefault();
    avisar({ tipo: 'error', mensaje: 'Sin conexión con el servidor. Revisa la red y vuelve a intentarlo.' });
});
