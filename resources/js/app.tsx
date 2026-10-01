import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

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
