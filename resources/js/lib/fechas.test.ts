import { describe, expect, it } from 'vitest';
import { diasHasta, textoEspera, textoVencimiento } from './fechas';

// 23:30 en Lima del 5 de octubre ya es 6 de octubre en UTC: los días deben contarse en Lima.
const hoy = new Date('2026-10-06T04:30:00Z');

describe('fechas relativas en Lima', () => {
    it('cuenta los días desde el día de Lima, no el de UTC', () => {
        expect(diasHasta('2026-10-05', hoy)).toBe(0);
        expect(diasHasta('2026-10-08', hoy)).toBe(3);
    });

    it('dice cuánto falta o cuánto pasó del vencimiento', () => {
        expect(textoVencimiento('2026-10-05', hoy)).toBe('Vence hoy');
        expect(textoVencimiento('2026-10-06', hoy)).toBe('Vence mañana');
        expect(textoVencimiento('2026-10-09', hoy)).toBe('Vence en 4 días');
        expect(textoVencimiento('2026-10-04', hoy)).toBe('Venció ayer');
        expect(textoVencimiento('2026-10-01', hoy)).toBe('Venció hace 4 días');
        // Cruza de mes sin correrse un día.
        expect(textoVencimiento('2026-11-01', hoy)).toBe('Vence en 27 días');
    });

    it('dice cuánto lleva esperando', () => {
        expect(textoEspera('2026-10-05T14:00:00Z', hoy)).toBe('Hoy');
        expect(textoEspera('2026-10-04T14:00:00Z', hoy)).toBe('Ayer');
        expect(textoEspera('2026-10-01T14:00:00Z', hoy)).toBe('Hace 4 días');
    });
});
