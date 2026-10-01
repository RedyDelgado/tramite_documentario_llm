import { render } from '@testing-library/react';
import { expect, test } from 'vitest';
import { SemaforoBadge, type Semaforo } from './SemaforoBadge';

const ESPERADO: Record<Semaforo, string> = { verde: 'En plazo', amarillo: 'Por vencer', rojo: 'Vencido', gris: 'Pendiente' };

test.each(Object.entries(ESPERADO))('el semáforo %s lleva icono y texto, no solo color', (estado, texto) => {
    const { container } = render(<SemaforoBadge estado={estado as Semaforo} />);

    expect(container).toHaveTextContent(texto);
    expect(container.querySelector('svg')).not.toBeNull();
});

test('admite un texto propio, como «Sin responsable»', () => {
    const { container } = render(<SemaforoBadge estado="rojo" texto="Sin responsable" />);
    expect(container).toHaveTextContent('Sin responsable');
});
