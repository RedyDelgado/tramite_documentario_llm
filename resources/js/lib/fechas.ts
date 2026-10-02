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
