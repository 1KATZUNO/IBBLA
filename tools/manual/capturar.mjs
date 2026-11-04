#!/usr/bin/env node
/**
 * Capturas del manual de usuario (public/images/manual/*.jpg), con los numeros rojos sobre cada boton.
 *
 * Vuelve a sacar todas las capturas cuando cambia la interfaz: lee tools/manual/capturas.json (ruta, rol,
 * tamano de pantalla, pasos previos y marcas) y guarda cada imagen. Si una marca no encuentra su boton
 * avisa con ADVERTENCIA para que actualices el selector o el texto en capturas.json.
 *
 * Requisitos
 *   - Node 18 o mas nuevo y Google Chrome (o Edge) instalado.
 *   - Una vez:  cd tools/manual && npm install        (instala puppeteer-core en tools/manual/node_modules)
 *   - La app corriendo con datos de prueba y los usuarios demo:
 *       php artisan migrate --seed && php artisan db:seed --class=VolumenDevSeeder
 *       php artisan db:seed --class=UsuariosDemoSeeder
 *       npm run build           (las capturas deben salir con los estilos finales)
 *       php artisan serve --port=8114
 *
 * Uso (desde tools/manual)
 *   node capturar.mjs                      todas las capturas
 *   node capturar.mjs --solo personas      solo las que empiezan con ese texto (separadas por coma)
 *   node capturar.mjs --base http://127.0.0.1:8000 --chrome "C:/ruta/chrome.exe"
 *   node capturar.mjs --lista              muestra las capturas sin sacar nada
 *
 * Formato de cada captura en capturas.json
 *   archivo   nombre del JPG que se guarda en public/images/manual
 *   rol       clave de "usuarios" (admin, tesorero, asistente, servidor, miembro)
 *   ruta      ruta de la app ("/personas")
 *   ancho, alto   tamano de la ventana (por omision 1366 x 768)
 *   completa  true = pagina completa (por omision solo lo que se ve)
 *   pasos     acciones antes de la foto: {"clic": "selector"}, {"clicTexto": "Nueva persona"},
 *             {"escribe": ["selector", "texto"]}, {"tecla": "Enter"}, {"desplaza": "selector"},
 *             {"scroll": 400} (scroll del contenido), {"espera": 1500}, {"js": "codigo"}
 *   marcas    numeros rojos con recuadro: {"n": 1, "selector": "#x"} o {"n": 1, "texto": "Nuevo", "en": "a,button"};
 *             "lado": "izq|der|arriba|abajo" mueve el numero; "sinCaja": true dibuja solo el numero
 *   recorte   selector del elemento que se guarda (por omision toda la ventana)
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

let puppeteer;
try {
    puppeteer = (await import('puppeteer-core')).default;
} catch {
    console.error('Falta puppeteer-core. Ejecuta una vez:  cd tools/manual && npm install');
    process.exit(1);
}

const aqui = path.dirname(fileURLToPath(import.meta.url));
const args = process.argv.slice(2);
const opcion = (nombre) => {
    const i = args.indexOf(`--${nombre}`);
    return i >= 0 ? args[i + 1] : null;
};

const config = JSON.parse(fs.readFileSync(path.join(aqui, 'capturas.json'), 'utf8'));
const base = opcion('base') || config.base || 'http://127.0.0.1:8114';
const salida = path.resolve(aqui, config.salida || '../../public/images/manual');
const solo = (opcion('solo') || '').split(',').map((s) => s.trim()).filter(Boolean);

const capturas = config.capturas.filter((c) => solo.length === 0 || solo.some((s) => c.archivo.startsWith(s)));

if (args.includes('--lista')) {
    capturas.forEach((c) => console.log(`${c.archivo}  (${c.rol} ${c.ruta})`));
    process.exit(0);
}

function buscarChrome() {
    const candidatos = [
        opcion('chrome'),
        process.env.CHROME_PATH,
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ].filter(Boolean);
    const ruta = candidatos.find((c) => fs.existsSync(c));
    if (!ruta) {
        console.error('No encuentro Chrome. Indica la ruta con --chrome o la variable CHROME_PATH.');
        process.exit(1);
    }
    return ruta;
}

const dormir = (ms) => new Promise((r) => setTimeout(r, ms));
const navegador = await puppeteer.launch({
    executablePath: buscarChrome(),
    headless: true,
    args: ['--no-sandbox', '--hide-scrollbars', '--font-render-hinting=none'],
});

const sesiones = {};
async function paginaDe(rol) {
    if (sesiones[rol]) return sesiones[rol];
    const usuario = config.usuarios[rol];
    if (!usuario) throw new Error(`El rol "${rol}" no esta en "usuarios" de capturas.json`);
    const contexto = await navegador.createBrowserContext();
    const pagina = await contexto.newPage();
    pagina.on('pageerror', (e) => console.warn('  ADVERTENCIA error de JavaScript:', String(e).slice(0, 160)));
    await pagina.setViewport({ width: 1366, height: 768 });
    await pagina.goto(`${base}/login`, { waitUntil: 'networkidle2', timeout: 90000 });
    await pagina.type('#email', usuario.email);
    await pagina.type('#password', usuario.clave);
    await Promise.all([
        pagina.waitForNavigation({ waitUntil: 'networkidle2', timeout: 90000 }),
        pagina.click('#loginForm button[type=submit]'),
    ]);
    return (sesiones[rol] = pagina);
}

/** Dibuja numeros rojos (con recuadro) sobre los elementos indicados. */
async function marcar(pagina, marcas) {
    return pagina.evaluate((marcas) => {
        document.querySelectorAll('.__marca').forEach((m) => m.remove());
        const faltan = [];
        for (const m of marcas) {
            let el = null;
            if (m.selector) el = document.querySelector(m.selector);
            if (!el && m.texto) {
                const t = m.texto.toLowerCase();
                const lista = [...document.querySelectorAll(m.en || 'a,button,label,th,summary,h1,h2,h3,span,input,select,textarea')];
                el = lista.find((c) => {
                    const visible = c.offsetParent !== null || getComputedStyle(c).position === 'fixed';
                    const texto = `${c.innerText || ''} ${c.value || ''} ${c.getAttribute('aria-label') || ''} ${c.title || ''} ${c.placeholder || ''}`.toLowerCase();
                    return visible && texto.includes(t);
                });
            }
            if (!el) { faltan.push(m.selector || m.texto); continue; }
            const r = el.getBoundingClientRect();
            const sx = window.scrollX, sy = window.scrollY;
            if (!m.sinCaja) {
                const caja = document.createElement('div');
                caja.className = '__marca';
                caja.style.cssText = `position:absolute;left:${r.left + sx - 3}px;top:${r.top + sy - 3}px;width:${r.width + 6}px;height:${r.height + 6}px;border:2px solid #dc2626;border-radius:8px;box-sizing:border-box;z-index:2147483646;pointer-events:none`;
                document.body.appendChild(caja);
            }
            const lado = m.lado || 'izq';
            const x = lado === 'der' ? r.right + sx - 6 : lado === 'abajo' || lado === 'arriba' ? r.left + sx + r.width / 2 - 12 : r.left + sx - 15;
            const y = lado === 'abajo' ? r.bottom + sy - 6 : lado === 'arriba' ? r.top + sy - 18 : r.top + sy - 15;
            const num = document.createElement('div');
            num.className = '__marca';
            num.textContent = String(m.n);
            num.style.cssText = `position:absolute;left:${x}px;top:${y}px;width:24px;height:24px;border-radius:9999px;background:#dc2626;color:#fff;font:700 13px/24px Inter,Arial,sans-serif;text-align:center;box-shadow:0 0 0 2px #fff,0 2px 6px rgba(0,0,0,.35);z-index:2147483647;pointer-events:none`;
            document.body.appendChild(num);
        }
        return faltan;
    }, marcas);
}

fs.mkdirSync(salida, { recursive: true });
let fallos = 0;

for (const c of capturas) {
    try {
        const pagina = await paginaDe(c.rol);
        await pagina.setViewport({ width: c.ancho || 1366, height: c.alto || 768, deviceScaleFactor: 1 });
        await pagina.goto(`${base}${c.ruta}`, { waitUntil: 'networkidle2', timeout: 90000 });

        for (const p of c.pasos || []) {
            if (p.clic) await pagina.click(p.clic);
            else if (p.clicTexto) {
                await pagina.evaluate((t) => {
                    const el = [...document.querySelectorAll('a,button,summary,label')].find((e) => (e.innerText || e.value || '').toLowerCase().includes(t.toLowerCase()) && e.offsetParent !== null);
                    if (el) el.click();
                }, p.clicTexto);
            } else if (p.escribe) await pagina.type(p.escribe[0], p.escribe[1], { delay: 25 });
            else if (p.tecla) await pagina.keyboard.press(p.tecla);
            else if (p.desplaza) await pagina.evaluate((s) => document.querySelector(s)?.scrollIntoView({ block: 'center' }), p.desplaza);
            else if (p.scroll !== undefined) await pagina.evaluate((y) => { const m = document.querySelector('main'); if (m) m.scrollTop = y; }, p.scroll);
            else if (p.js) await pagina.evaluate(p.js);
            await dormir(p.espera ?? 300);
        }

        await dormir(c.espera ?? 500);
        const faltan = c.marcas ? await marcar(pagina, c.marcas) : [];
        faltan.forEach((f) => console.warn(`  ADVERTENCIA ${c.archivo}: no encontre "${f}" para su marca`));

        const destino = path.join(salida, c.archivo);
        fs.mkdirSync(path.dirname(destino), { recursive: true });
        const opciones = { path: destino, type: 'jpeg', quality: c.calidad || config.calidad || 72, fullPage: !!c.completa };
        if (c.recorte) {
            const el = await pagina.$(c.recorte);
            const caja = el ? await el.boundingBox() : null;
            if (caja) Object.assign(opciones, { clip: { x: caja.x, y: caja.y, width: caja.width, height: caja.height }, fullPage: false });
        }
        await pagina.screenshot(opciones);
        console.log(`ok  ${c.archivo}  ${Math.round(fs.statSync(destino).size / 1024)} KB`);
    } catch (e) {
        fallos++;
        console.error(`ERROR ${c.archivo}: ${String(e).slice(0, 200)}`);
    }
}

await navegador.close();
process.exit(fallos ? 1 : 0);
