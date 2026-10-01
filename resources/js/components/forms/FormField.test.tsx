import { render, screen } from '@testing-library/react';
import { expect, test } from 'vitest';
import { Input } from '@/components/ui/Input';
import { FormField } from './FormField';

test('asocia etiqueta, ayuda y error al control', () => {
    const { rerender } = render(
        <FormField etiqueta="Nombre" ayuda="Como figura en el oficio.">
            {(c) => <Input {...c} />}
        </FormField>,
    );

    const campo = screen.getByLabelText('Nombre');
    expect(campo).not.toHaveAttribute('aria-invalid');
    expect(campo).toHaveAccessibleDescription('Como figura en el oficio.');

    rerender(
        <FormField etiqueta="Nombre" ayuda="Como figura en el oficio." error="El nombre es obligatorio.">
            {(c) => <Input {...c} />}
        </FormField>,
    );

    expect(campo).toHaveAttribute('aria-invalid', 'true');
    expect(campo).toHaveAccessibleDescription('El nombre es obligatorio.');
});
