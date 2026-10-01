import { Badge } from '@/components/ui/Badge';

/** Muestra hasta `max` términos y resume el resto como «+n». */
export function ListaEtiquetas({ lista, max }: { lista: string[]; max?: number }) {
    if (lista.length === 0) return <span className="text-fg-muted">—</span>;

    const visibles = max ? lista.slice(0, max) : lista;
    const resto = lista.length - visibles.length;

    return (
        <span className="flex flex-wrap gap-1">
            {visibles.map((t) => (
                <Badge key={t} tono="marca">
                    {t}
                </Badge>
            ))}
            {resto > 0 && <Badge>+{resto}</Badge>}
        </span>
    );
}
