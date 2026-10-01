const fechaHora = new Intl.DateTimeFormat('es-PE', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/Lima' });

export function formatearFechaHora(iso: string | null): string {
    return iso ? fechaHora.format(new Date(iso)) : '—';
}
