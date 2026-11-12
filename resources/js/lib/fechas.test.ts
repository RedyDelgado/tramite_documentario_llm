import { expect, test } from 'vitest';
import { textoVencimiento } from './fechas';

test('dice el plazo en palabras, contando días de calendario desde hoy', () => {
    const hoy = '2026-11-18';

    expect(textoVencimiento('2026-11-18', hoy)).toBe('Vence hoy');
    expect(textoVencimiento('2026-11-19', hoy)).toBe('Vence mañana');
    expect(textoVencimiento('2026-11-20', hoy)).toBe('Vence en 2 días');
    expect(textoVencimiento('2026-11-17', hoy)).toBe('Venció ayer');
    expect(textoVencimiento('2026-11-14', hoy)).toBe('Venció hace 4 días');
    // Cruza de mes sin correrse un día.
    expect(textoVencimiento('2026-12-01', hoy)).toBe('Vence en 13 días');
});
