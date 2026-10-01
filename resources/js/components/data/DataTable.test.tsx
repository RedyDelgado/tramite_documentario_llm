import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, test, vi } from 'vitest';
import { DataTable, type Columna } from './DataTable';

type Fila = { id: number; nombre: string };
const columnas: Columna<Fila>[] = [{ clave: 'nombre', titulo: 'Nombre', ordenable: true, celda: (f) => f.nombre }];

test('sin filas muestra el estado vacío', () => {
    render(<DataTable titulo="Áreas" columnas={columnas} filas={[]} claveFila={(f) => f.id} vacio={<p>Aún no hay áreas</p>} />);
    expect(screen.getByText('Aún no hay áreas')).toBeInTheDocument();
});

test('elige fila con clic y con Enter, y marca la seleccionada', async () => {
    const elegir = vi.fn();
    const filas = [
        { id: 1, nombre: 'Dirección' },
        { id: 2, nombre: 'Mesa de partes' },
    ];
    render(<DataTable titulo="Áreas" columnas={columnas} filas={filas} claveFila={(f) => f.id} seleccionada={2} onElegirFila={elegir} />);

    await userEvent.click(screen.getByText('Dirección'));
    expect(elegir).toHaveBeenLastCalledWith(filas[0]);

    screen.getByText('Mesa de partes').closest('tr')!.focus();
    await userEvent.keyboard('{Enter}');
    expect(elegir).toHaveBeenLastCalledWith(filas[1]);

    expect(screen.getByText('Mesa de partes').closest('tr')).toHaveAttribute('aria-selected', 'true');
});

test('la columna ordenada expone aria-sort y el clic pide reordenar', async () => {
    const ordenar = vi.fn();
    render(
        <DataTable
            titulo="Áreas"
            columnas={columnas}
            filas={[{ id: 1, nombre: 'Dirección' }]}
            claveFila={(f) => f.id}
            orden={{ clave: 'nombre', dir: 'desc' }}
            onOrdenar={ordenar}
        />,
    );

    expect(screen.getByRole('columnheader', { name: /nombre/i })).toHaveAttribute('aria-sort', 'descending');
    await userEvent.click(screen.getByRole('button', { name: /nombre/i }));
    expect(ordenar).toHaveBeenCalledWith('nombre');
});
