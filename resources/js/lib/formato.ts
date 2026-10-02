const UNIDADES = ['B', 'KB', 'MB', 'GB'];

export function formatearBytes(bytes: number): string {
    let valor = bytes;
    let i = 0;
    while (valor >= 1024 && i < UNIDADES.length - 1) {
        valor /= 1024;
        i++;
    }
    return `${valor.toLocaleString('es-PE', { maximumFractionDigits: i === 0 ? 0 : 1 })} ${UNIDADES[i]}`;
}
