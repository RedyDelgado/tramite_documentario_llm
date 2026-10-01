import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import { expect, test } from 'vitest';

const RAIZ = join(__dirname, '..', '..');
const HEX = /#(?:[0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})\b/gi;

function archivos(dir: string): string[] {
    return readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
        const ruta = join(dir, e.name);
        return e.isDirectory() ? archivos(ruta) : [ruta];
    });
}

// 5.3: un HEX fuera de tokens.css es un color que se salta la paleta.
test('ningún HEX fuera de tokens.css', () => {
    const infractores = archivos(RAIZ)
        .filter((f) => /\.(tsx?|css)$/.test(f) && !f.endsWith('tokens.css'))
        .flatMap((f) => (readFileSync(f, 'utf8').match(HEX) ?? []).map((hex) => `${relative(RAIZ, f)}: ${hex}`));

    expect(infractores).toEqual([]);
});
