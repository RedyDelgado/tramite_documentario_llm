import { render, screen } from '@testing-library/react';
import { expect, test } from 'vitest';
import type { AtencionArea, ExpedienteDetalle } from '@/types';
import { PasosExpediente, siguientePaso } from './PasosExpediente';

const SIN_PERMISOS: ExpedienteDetalle['permisos'] = {
    derivar: false, tomar: false, atender: false, atender_correo: false, atender_areas: [], comentar: false,
    solicitar_cierre: false, resolver_cierre: false, custodiar: false, agrupar: false, redactar: false, sugerir: false,
};

const area = (nombre: string, atendida = false): AtencionArea => ({
    area_id: nombre.length, area: nombre, responsable_id: null, responsable: null, plazo_dias: 5, fecha_limite: '2026-11-20',
    estado: atendida ? { valor: 'atendido', etiqueta: 'Atendido' } : { valor: 'derivado', etiqueta: 'Por tomar' },
    atendido_at: null, atendido_por: null, nota: null,
});

// Solo los campos que lee el componente; el resto del detalle no influye en el siguiente paso.
const expediente = (estado: ExpedienteDetalle['estado']['valor'], cambios: Partial<ExpedienteDetalle> = {}) =>
    ({ estado: { valor: estado, etiqueta: estado }, atenciones: [], correos: [], permisos: SIN_PERMISOS, cierre_solicitado_at: null, puede_registrar: false, ...cambios }) as ExpedienteDetalle;

test('dice la acción a quien puede hacerla y lo que falta a quien no', () => {
    expect(siguientePaso(expediente('registrado', { permisos: { ...SIN_PERMISOS, derivar: true } }))).toMatch(/«Derivar»/);
    expect(siguientePaso(expediente('registrado'))).toBe('Falta derivarlo a un área.');
});

test('mientras se espera, nombra las áreas que aún no responden', () => {
    const e = expediente('derivado', { atenciones: [area('Coordinación Académica', true), area('Enfermería'), area('Dirección')] });

    expect(siguientePaso(e)).toBe('Esperando la respuesta de Enfermería y Dirección. Cuando contesten el aviso por correo, la respuesta aparecerá aquí.');
});

test('una respuesta de un área pendiente pide revisarla; la de un área ya atendida no', () => {
    const permisos = { ...SIN_PERMISOS, atender_correo: true };
    const correo = (de: string) => ({ id: 1, de_nombre: null, de_email: 'a@b.pe', para: [], asunto: 'RE', fecha: '', cuerpo: null, es_reenvio: false, documentos: [], respuesta: { area: de, ia: null, motivo: null } });
    const atenciones = [area('Coordinación Académica', true), area('Enfermería')];

    expect(siguientePaso(expediente('derivado', { permisos, atenciones, correos: [correo('Enfermería')] }))).toMatch(/«Dar por atendido»/);
    expect(siguientePaso(expediente('derivado', { permisos, atenciones, correos: [correo('Coordinación Académica')] }))).toMatch(/^Esperando la respuesta de Enfermería/);
});

test('marca el paso actual y explica los estados fuera del recorrido', () => {
    const { rerender } = render(<PasosExpediente expediente={expediente('atendido', { cierre_solicitado_at: '2026-11-11' })} />);

    expect(screen.getByText('Atendido').closest('li')).toHaveAttribute('aria-current', 'step');
    expect(screen.getByText(/falta que lo apruebe la dirección/)).toBeInTheDocument();

    rerender(<PasosExpediente expediente={expediente('no_tramite')} />);
    expect(screen.queryByRole('list')).not.toBeInTheDocument();
    expect(screen.getByText(/No se borró/)).toBeInTheDocument();
});
