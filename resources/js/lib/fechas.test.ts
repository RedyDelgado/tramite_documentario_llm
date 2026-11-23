import { expect, test } from 'vitest';
import { formatearMomento } from './fechas';

test('en la lista de mensajes, lo de hoy en Lima muestra la hora y lo anterior el día', () => {
    // 23:30 en Lima del 5 de octubre ya es 6 de octubre en UTC: «hoy» se cuenta en Lima.
    const ahora = new Date('2026-10-06T04:30:00Z');

    expect(formatearMomento('2026-10-05T15:05:00Z', ahora)).toMatch(/10:05/);
    expect(formatearMomento('2026-10-04T15:05:00Z', ahora)).toMatch(/4.*oct/);
});
