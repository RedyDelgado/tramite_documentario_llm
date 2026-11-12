const fechaHora = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Lima' });

export function formatearFechaHora(iso: string | null): string {
    return iso ? fechaHora.format(new Date(iso)) : '—';
}

const fecha = new Intl.DateTimeFormat('es-PE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Lima' });

/** Fecha sin hora (`AAAA-MM-DD`); el mediodía de Lima evita que la zona la corra un día. */
export function formatearFecha(dia: string | null): string {
    return dia ? fecha.format(new Date(`${dia}T12:00:00-05:00`)) : '—';
}

/** «10 días hábiles», «Sin plazo». */
export function formatearPlazo(dias: number | null, tipo: 'habiles' | 'calendario'): string {
    if (dias === null) return 'Sin plazo';
    return `${dias} ${dias === 1 ? 'día' : 'días'} ${tipo === 'habiles' ? (dias === 1 ? 'hábil' : 'hábiles') : 'calendario'}`;
}

/** Hoy en Lima como `AAAA-MM-DD`. */
export function hoyEnLima(): string {
    return new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' });
}

// Días de calendario entre dos `AAAA-MM-DD`; en UTC para que el horario no reste ni sume un día.
const diasEntre = (desde: string, hasta: string) => Math.round((Date.parse(`${hasta}T00:00:00Z`) - Date.parse(`${desde}T00:00:00Z`)) / 86_400_000);

/** «Vence hoy», «Vence en 3 días», «Venció hace 2 días»: el plazo en palabras, contado en días de calendario desde hoy en Lima. */
export function textoVencimiento(fechaLimite: string, hoy: string = hoyEnLima()): string {
    const dias = diasEntre(hoy, fechaLimite);
    if (dias === 0) return 'Vence hoy';
    if (dias === 1) return 'Vence mañana';
    if (dias > 1) return `Vence en ${dias} días`;
    return dias === -1 ? 'Venció ayer' : `Venció hace ${-dias} días`;
}
