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

const diaLima = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Lima' });

/** Hoy en Lima como `AAAA-MM-DD`. */
export function hoyEnLima(): string {
    return diaLima.format(new Date());
}

/** Días de calendario entre hoy en Lima y `dia` (`AAAA-MM-DD`): negativo si ya pasó. */
export function diasHasta(dia: string, hoy: Date = new Date()): number {
    return Math.round((Date.parse(`${dia}T12:00:00Z`) - Date.parse(`${diaLima.format(hoy)}T12:00:00Z`)) / 86_400_000);
}

/** «Vence hoy», «Vence mañana», «Vence en 3 días», «Venció ayer», «Venció hace 4 días». */
export function textoVencimiento(dia: string, hoy?: Date): string {
    const n = diasHasta(dia, hoy);
    if (n === 0) return 'Vence hoy';
    if (n === 1) return 'Vence mañana';
    if (n > 1) return `Vence en ${n} días`;
    return n === -1 ? 'Venció ayer' : `Venció hace ${-n} días`;
}

/** «Hoy», «Ayer», «Hace 4 días»: cuánto lleva esperando desde un instante. */
export function textoEspera(iso: string, hoy?: Date): string {
    const n = -diasHasta(diaLima.format(new Date(iso)), hoy);
    if (n <= 0) return 'Hoy';
    return n === 1 ? 'Ayer' : `Hace ${n} días`;
}
