import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { expect, test } from 'vitest';
import { Combobox } from './Combobox';

const OPCIONES = [
    { value: 1, label: 'Oficina de Gestión Académica' },
    { value: 2, label: 'Dirección General de Administración' },
];

function Prueba() {
    const [valor, setValor] = useState<number | null>(null);
    const [creado, setCreado] = useState('');
    return (
        <>
            <Combobox id="c" opciones={OPCIONES} value={valor} onChange={setValor} onCrear={setCreado} />
            <output>{`${valor ?? ''}|${creado}`}</output>
        </>
    );
}

test('filtra sin tildes por palabras en cualquier orden y elige con el teclado', async () => {
    render(<Prueba />);

    await userEvent.type(screen.getByRole('combobox'), 'academica gestion');

    expect(screen.getAllByRole('option').map((o) => o.textContent)).toEqual(['Oficina de Gestión Académica', 'Crear «academica gestion»']);
    await userEvent.keyboard('{Enter}');
    expect(screen.getByRole('status')).toHaveTextContent('1|');
    expect(screen.getByRole('combobox')).toHaveValue('Oficina de Gestión Académica');
});

test('sin coincidencia exacta ofrece crear lo escrito', async () => {
    render(<Prueba />);

    await userEvent.type(screen.getByRole('combobox'), 'Colegio San Martín');
    await userEvent.click(screen.getByRole('option', { name: 'Crear «Colegio San Martín»' }));

    expect(screen.getByRole('status')).toHaveTextContent('|Colegio San Martín');
});

test('la rueda sobre la lista no llega al documento, donde un modal abierto la bloquearía', async () => {
    const bloqueos: Event[] = [];
    const bloquear = (e: Event) => bloqueos.push(e);
    document.addEventListener('wheel', bloquear);
    document.addEventListener('touchmove', bloquear);
    render(<Prueba />);

    await userEvent.click(screen.getByRole('combobox'));
    fireEvent.wheel(screen.getByRole('listbox'), { deltaY: 100 });
    fireEvent.touchMove(screen.getByRole('listbox'));

    document.removeEventListener('wheel', bloquear);
    document.removeEventListener('touchmove', bloquear);
    expect(bloqueos).toHaveLength(0);
});

test('un clic en la lista fuera de las opciones (la barra de desplazamiento) no la cierra', async () => {
    render(<Prueba />);

    await userEvent.click(screen.getByRole('combobox'));
    await userEvent.pointer({ keys: '[MouseLeft]', target: screen.getByRole('listbox') });

    expect(screen.getByRole('combobox')).toHaveFocus();
    expect(screen.getByRole('listbox')).toBeInTheDocument();
});
