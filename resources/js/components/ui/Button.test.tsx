import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { expect, test, vi } from 'vitest';
import { Button } from './Button';

test('cargando deshabilita, anuncia ocupado y no dispara el clic', async () => {
    const alClic = vi.fn();
    render(
        <Button cargando onClick={alClic}>
            Guardar
        </Button>,
    );

    const boton = screen.getByRole('button', { name: /guardar/i });
    expect(boton).toBeDisabled();
    expect(boton).toHaveAttribute('aria-busy', 'true');
    expect(screen.getByRole('status', { name: 'Cargando' })).toBeInTheDocument();

    await userEvent.click(boton);
    expect(alClic).not.toHaveBeenCalled();
});

test('por defecto es type=button para no enviar formularios por accidente', () => {
    render(<Button>Cancelar</Button>);
    expect(screen.getByRole('button')).toHaveAttribute('type', 'button');
});
