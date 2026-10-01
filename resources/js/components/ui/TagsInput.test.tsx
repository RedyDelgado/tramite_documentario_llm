import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { expect, test } from 'vitest';
import { TagsInput } from './TagsInput';

function Prueba({ inicial = [] as string[] }) {
    const [valor, setValor] = useState(inicial);
    return (
        <>
            <TagsInput id="t" valor={valor} onCambiar={setValor} />
            <output>{valor.join('|')}</output>
        </>
    );
}

test('Enter y coma agregan, sin duplicados aunque cambien mayúsculas', async () => {
    render(<Prueba />);
    const campo = screen.getByRole('textbox');

    await userEvent.type(campo, 'oficio{Enter}convenio,OFICIO{Enter}');

    expect(screen.getByRole('status')).toHaveTextContent('oficio|convenio');
});

test('pegar una lista separada por comas agrega cada término', async () => {
    render(<Prueba inicial={['oficio']} />);
    const campo = screen.getByRole('textbox');

    campo.focus();
    await userEvent.paste('convenio, sílabo ,oficio,');

    expect(screen.getByRole('status')).toHaveTextContent('oficio|convenio|sílabo');
    expect(campo).toHaveValue('');
});

test('Retroceso en vacío quita el último y el botón quita uno concreto', async () => {
    render(<Prueba inicial={['oficio', 'convenio', 'sílabo']} />);

    await userEvent.type(screen.getByRole('textbox'), '{Backspace}');
    expect(screen.getByRole('status')).toHaveTextContent('oficio|convenio');

    await userEvent.click(screen.getByRole('button', { name: 'Quitar oficio' }));
    expect(screen.getByRole('status')).toHaveTextContent(/^convenio$/);
});
