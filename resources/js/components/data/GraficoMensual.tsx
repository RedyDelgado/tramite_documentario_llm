import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export type Serie = { clave: string; nombre: string };
type Fila = { mes: string } & Record<string, number | string>;

// Escala primary y neutros (5.3): los colores semánticos son solo para semáforos.
const COLORES = ['var(--primary-600)', 'var(--primary-300)'];

// «2026-10» → «oct. 2026», en el idioma y la zona de la institución.
const etiquetaMes = (mes: string) =>
    new Date(`${mes}-15T12:00:00`).toLocaleDateString('es-PE', { month: 'short', year: 'numeric', timeZone: 'America/Lima' });

/** Barras agrupadas por mes; el resumen en texto acompaña al gráfico para lectores de pantalla. */
export function GraficoMensual({ filas, series, titulo, alto = 'normal' }: { filas: Fila[]; series: Serie[]; titulo: string; alto?: 'normal' | 'compacto' }) {
    const resumen = filas.map((f) => `${etiquetaMes(f.mes)}: ${series.map((s) => `${s.nombre} ${f[s.clave]}`).join(', ')}`).join('; ');

    return (
        <figure className={alto === 'compacto' ? 'h-44' : 'h-64'} aria-label={titulo}>
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={filas} margin={{ top: 8, right: 8, bottom: 0, left: -16 }} barGap={2}>
                    <CartesianGrid vertical={false} stroke="var(--border)" />
                    <XAxis dataKey="mes" tickFormatter={etiquetaMes} tick={{ fill: 'var(--text-secondary)', fontSize: 12 }} axisLine={{ stroke: 'var(--border)' }} tickLine={false} />
                    <YAxis allowDecimals={false} tick={{ fill: 'var(--text-secondary)', fontSize: 12 }} axisLine={false} tickLine={false} />
                    <Tooltip
                        labelFormatter={(mes) => etiquetaMes(String(mes))}
                        cursor={{ fill: 'var(--surface-subtle)' }}
                        contentStyle={{ borderRadius: 'var(--radio-control)', borderColor: 'var(--border)', fontSize: 14 }}
                    />
                    <Legend itemSorter={null} iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12, color: 'var(--text-primary)' }} />
                    {series.map((s, i) => (
                        <Bar key={s.clave} dataKey={s.clave} name={s.nombre} fill={COLORES[i % COLORES.length]} radius={[4, 4, 0, 0]} />
                    ))}
                </BarChart>
            </ResponsiveContainer>
            <figcaption className="sr-only">{resumen}</figcaption>
        </figure>
    );
}
