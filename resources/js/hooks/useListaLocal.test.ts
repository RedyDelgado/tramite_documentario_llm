import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { coincide, POR_PAGINA, useListaLocal } from './useListaLocal';

type Fila = { id: number; nombre: string; activa: boolean };
const filas: Fila[] = Array.from({ length: 23 }, (_, i) => ({ id: i + 1, nombre: i === 4 ? 'Coordinación de Enfermería' : `Área ${i + 1}`, activa: i % 2 === 0 }));

describe('useListaLocal', () => {
    it('busca sin tildes ni mayúsculas y por palabras sueltas', () => {
        expect(coincide('enfermeria coordinacion', 'Coordinación de Enfermería')).toBe(true);
        expect(coincide('derecho', 'Coordinación de Enfermería')).toBe(false);
        expect(coincide('', 'cualquiera')).toBe(true);
    });

    it('pagina de a 10 y al filtrar vuelve a la primera página', () => {
        const { result } = renderHook(() =>
            useListaLocal<Fila, { q?: string; estado?: string }>(filas, {
                filtrar: (f, { q, estado }) => coincide(q, f.nombre) && (!estado || f.activa === (estado === 'activas')),
            }),
        );
        expect(POR_PAGINA).toBe(10);
        expect(result.current.filas).toHaveLength(10);
        expect(result.current.paginacion).toMatchObject({ pagina: 1, total: 23 });

        act(() => result.current.paginacion.onPagina(3));
        expect(result.current.filas.map((f) => f.id)).toEqual([21, 22, 23]);

        act(() => result.current.cambiar({ q: 'ENFERMERÍA' }));
        expect(result.current.paginacion).toMatchObject({ pagina: 1, total: 1 });
        expect(result.current.filas[0].id).toBe(5);

        act(() => result.current.cambiar({ q: '', estado: 'inactivas' }));
        expect(result.current.paginacion.total).toBe(11);
    });

    it('ordena por la columna elegida y alterna la dirección', () => {
        const { result } = renderHook(() => useListaLocal<Fila>(filas, { ordenes: { id: (f) => f.id }, orden: { clave: 'id', dir: 'asc' } }));
        act(() => result.current.ordenar('id'));
        expect(result.current.filas[0].id).toBe(23);
    });
});
