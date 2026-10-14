// Genera los mosaicos de chanchitos del fondo (ver resources/css/app.css):
// varios por mosaico, con posición, tamaño, giro y opacidad "al azar" (semilla
// fija, siempre el mismo dibujo). Cada chanchito que toca un borde se dibuja
// también del lado opuesto, así el mosaico se repite sin cortes.
//
//   node resources/images/generar-chanchitos.mjs variantes.json vista-previa.svg
//
// variantes.json trae las cuatro url("data:...") para pegar en app.css
// (--piggy-bg y --piggy-card, en claro y oscuro).
import { writeFileSync } from 'node:fs';

const PALE = '#bbf7d0';
const DETAIL = '#86efac';

// El dibujo, en un viewBox de 128x104 centrado en el origen al usarlo.
const symbol = `<symbol id='p' viewBox='0 0 128 104'>
<g fill='${PALE}'><circle cx='64' cy='12' r='9'/><rect x='32' y='72' width='14' height='22' rx='6'/><rect x='78' y='72' width='14' height='22' rx='6'/><ellipse cx='62' cy='56' rx='45' ry='33'/><rect x='97' y='45' width='20' height='22' rx='9'/><path d='M38 32 L44 12 Q46 8 50 11 L60 26 Z'/></g>
<path d='M18 52 Q6 50 9 41 Q12 34 18 39' fill='none' stroke='${PALE}' stroke-width='4.5' stroke-linecap='round'/>
<g fill='${DETAIL}'><rect x='52' y='28' width='24' height='5' rx='2.5'/><circle cx='87' cy='47' r='3.6'/><circle cx='104' cy='56' r='2.4'/><circle cx='111' cy='56' r='2.4'/><rect x='61' y='8' width='6' height='8' rx='2'/></g>
</symbol>`;

function rng(seed) {
    return () => {
        seed = (seed * 1664525 + 1013904223) % 4294967296;
        return seed / 4294967296;
    };
}

function tile({ w, h, count, minSize, maxSize, seed }) {
    const rand = rng(seed);
    const placed = [];
    let tries = 0;
    while (placed.length < count && tries++ < 5000) {
        const size = minSize + rand() * (maxSize - minSize);
        const x = rand() * w;
        const y = rand() * h;
        // Distancia mínima medida "dando la vuelta" (el mosaico se repite).
        const far = placed.every((q) => {
            const dx = Math.min(Math.abs(q.x - x), w - Math.abs(q.x - x));
            const dy = Math.min(Math.abs(q.y - y), h - Math.abs(q.y - y));
            return Math.hypot(dx, dy) > (q.size + size) * 0.8;
        });
        if (!far) continue;
        placed.push({ x, y, size, rot: -28 + rand() * 56, flip: rand() < 0.35, op: 0.55 + rand() * 0.45 });
    }

    const uses = [];
    for (const p of placed) {
        const hw = p.size / 2;
        const hh = (p.size * 104) / 128 / 2;
        for (const ox of [-w, 0, w]) {
            for (const oy of [-h, 0, h]) {
                const cx = p.x + ox;
                const cy = p.y + oy;
                if (cx + p.size < 0 || cx - p.size > w || cy + p.size < 0 || cy - p.size > h) continue;
                uses.push(
                    `<use href='#p' x='${(-hw).toFixed(1)}' y='${(-hh).toFixed(1)}' width='${p.size.toFixed(1)}' height='${(hh * 2).toFixed(1)}' opacity='${p.op.toFixed(2)}' transform='translate(${cx.toFixed(1)} ${cy.toFixed(1)}) rotate(${p.rot.toFixed(1)})${p.flip ? ' scale(-1 1)' : ''}'/>`
                );
            }
        }
    }
    return { placed: placed.length, body: uses.join('') };
}

const svg = (w, h, body, opacity) =>
    `<svg xmlns='http://www.w3.org/2000/svg' width='${w}' height='${h}' viewBox='0 0 ${w} ${h}' opacity='${opacity}'><defs>${symbol}</defs>${body}</svg>`;

const uri = (s) =>
    `url("data:image/svg+xml,${s.replace(/\n/g, '').replace(/#/g, '%23').replace(/</g, '%3C').replace(/>/g, '%3E')}")`;

// Fondo general: mosaico grande, chanchitos medianos.
const BG = { w: 420, h: 380, count: 9, minSize: 40, maxSize: 92, seed: 7 };
// Tarjetas: mosaico chico (de proporción distinta, para que no "rime" con el
// fondo), chanchitos chicos.
const CARD = { w: 260, h: 170, count: 6, minSize: 20, maxSize: 38, seed: 23 };

const bg = tile(BG);
const card = tile(CARD);

const out = {
    'light-bg': uri(svg(BG.w, BG.h, bg.body, 0.5)),
    'dark-bg': uri(svg(BG.w, BG.h, bg.body, 0.05)),
    'light-card': uri(svg(CARD.w, CARD.h, card.body, 0.6)),
    'dark-card': uri(svg(CARD.w, CARD.h, card.body, 0.07)),
};

writeFileSync(process.argv[2], JSON.stringify(out));
writeFileSync(process.argv[3], svg(BG.w * 2, BG.h * 2, bg.body + `<g transform='translate(${BG.w} 0)'>${bg.body}</g><g transform='translate(0 ${BG.h})'>${bg.body}</g><g transform='translate(${BG.w} ${BG.h})'>${bg.body}</g>`, 1));
console.log('fondo', bg.placed, 'tarjeta', card.placed, Object.fromEntries(Object.entries(out).map(([k, v]) => [k, v.length])));
