#!/usr/bin/env node
// ============================================================
//  GeoUTC — diagnostico-geoutc.js
//  Ejecutar en la terminal de VS Code:  node diagnostico-geoutc.js
//  Requiere Node.js 18+ (usa fetch nativo)
// ============================================================

const BASE = 'http://localhost';          // ← Cambia si tu servidor corre en otro puerto, ej: http://localhost:8080
const API  = `${BASE}/api/api.php`;

const RESET  = '\x1b[0m';
const RED    = '\x1b[31m';
const GREEN  = '\x1b[32m';
const YELLOW = '\x1b[33m';
const CYAN   = '\x1b[36m';
const BOLD   = '\x1b[1m';

let passed = 0, failed = 0, warnings = 0;

function ok(msg)   { console.log(`  ${GREEN}✔${RESET} ${msg}`); passed++; }
function fail(msg) { console.log(`  ${RED}✘ ${msg}${RESET}`);  failed++; }
function warn(msg) { console.log(`  ${YELLOW}⚠ ${msg}${RESET}`); warnings++; }
function info(msg) { console.log(`  ${CYAN}ℹ ${msg}${RESET}`); }
function title(msg){ console.log(`\n${BOLD}${CYAN}▶ ${msg}${RESET}`); }

// ── Helper fetch con timeout ─────────────────────────────────
async function get(action, params = {}) {
    const qs  = new URLSearchParams({ action, ...params }).toString();
    const url = `${API}?${qs}`;
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 5000);
    try {
        const r = await fetch(url, { signal: ctrl.signal });
        clearTimeout(timer);
        const text = await r.text();
        let json;
        try { json = JSON.parse(text); } catch { return { __raw: text, __status: r.status }; }
        return { ...json, __status: r.status };
    } catch (e) {
        clearTimeout(timer);
        return { __error: e.message };
    }
}

async function post(action, body = {}) {
    const url  = `${API}?action=${action}`;
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 5000);
    try {
        const r = await fetch(url, {
            method : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify(body),
            signal : ctrl.signal,
        });
        clearTimeout(timer);
        const text = await r.text();
        let json;
        try { json = JSON.parse(text); } catch { return { __raw: text, __status: r.status }; }
        return { ...json, __status: r.status };
    } catch (e) {
        clearTimeout(timer);
        return { __error: e.message };
    }
}

// ── Tests ────────────────────────────────────────────────────
async function testConexion() {
    title('1. Conectividad al servidor');
    const d = await get('usuario');

    if (d.__error) {
        fail(`No se pudo conectar a ${BASE} — ¿está corriendo el servidor PHP?`);
        info(`Error: ${d.__error}`);
        return false;
    }
    if (d.__raw) {
        fail(`La respuesta no es JSON válido. Respuesta cruda:\n\n${d.__raw.slice(0, 400)}`);
        info('Posible error PHP — revisa los logs de Apache/PHP');
        return false;
    }
    if (d.__status !== 200) {
        fail(`HTTP ${d.__status} en endpoint /usuario`);
        return false;
    }
    ok(`Servidor responde en ${BASE}`);
    return true;
}

async function testUsuario() {
    title('2. Endpoint: usuario');
    const d = await get('usuario');

    if (d.__error || d.__raw) { fail('No se pudo obtener datos del usuario'); return; }

    if (!d.id || d.id === 0) {
        fail('usuario.id es 0 — initUser() no inicializó la sesión correctamente');
        info('BUG CONFIRMADO: $uid = (int)$_SESSION[\'user_id\'] se ejecuta antes de que la sesión exista');
        info('Aplica el fix en api.php: cambia la línea $uid a:  $uid = (int)($_SESSION[\'user_id\'] ?? initUser());');
    } else {
        ok(`Usuario ID=${d.id}  nombre="${d.nombre}"  puntos=${d.puntos}`);
    }

    if (typeof d.puntos !== 'number') warn('puntos no es número entero');
    if (typeof d.progreso !== 'number') warn('progreso no es número entero');
}

async function testLugares() {
    title('3. Endpoint: lugares');
    const d = await get('lugares');

    if (!d.lugares) { fail('No devuelve array "lugares"'); return; }
    if (!d.lugares.length) { warn('El array de lugares está vacío — verifica el seed en conexion.php'); return; }

    ok(`${d.lugares.length} lugar(es) encontrado(s)`);

    d.lugares.forEach(l => {
        if (!l.latitud || !l.longitud) warn(`Lugar "${l.nombre}" sin coordenadas`);
        if (!l.icono)                  warn(`Lugar "${l.nombre}" sin icono`);
    });

    if (!Array.isArray(d.visitados)) fail('"visitados" no es un array');
    else ok(`Array visitados OK (${d.visitados.length} visitados)`);
}

async function testComentarios() {
    title('4. Endpoint: comentarios (el que fallaba)');
    const d = await get('comentarios');

    if (d.__error || d.__raw) { fail('Error al obtener comentarios'); return; }

    if (!Array.isArray(d.comentarios)) {
        fail('"comentarios" no es un array — posible error SQL o $uid=0');
        info('Respuesta recibida: ' + JSON.stringify(d).slice(0, 200));
        return;
    }
    ok(`Array comentarios OK — ${d.comentarios.length} comentario(s)`);

    if (!Array.isArray(d.lugares)) fail('"lugares" no está en la respuesta de comentarios');
    else ok(`Array lugares dentro de comentarios OK (${d.lugares.length})`);

    // Verificar estructura de cada comentario
    if (d.comentarios.length > 0) {
        const c = d.comentarios[0];
        const campos = ['id','texto','fecha','usuario_nombre','lugar_nombre'];
        campos.forEach(campo => {
            if (c[campo] === undefined) warn(`Comentario sin campo "${campo}"`);
        });
        ok(`Estructura del primer comentario correcta`);
    } else {
        warn('No hay comentarios en la BD todavía — publica uno para probar');
    }
}

async function testPublicarComentario() {
    title('5. Endpoint POST: comentar');
    const lugares = await get('lugares');
    if (!lugares.lugares?.length) { warn('Sin lugares para comentar — omitiendo test'); return; }

    const lugar_id = lugares.lugares[0].id;
    const d = await post('comentar', { lugar_id, texto: '[Test diagnóstico - puedes borrar]' });

    if (d.__error || d.__raw) { fail('Error al publicar comentario'); return; }

    if (d.success) {
        ok(`Comentario publicado OK, id=${d.id}`);
    } else {
        fail(`No se pudo publicar: ${d.mensaje || JSON.stringify(d)}`);
        if (!d.id && d.__status === 200) {
            info('El endpoint respondió 200 pero success=false — revisa $uid en api.php');
        }
    }
}

async function testRecompensas() {
    title('6. Endpoint: recompensas');
    const d = await get('recompensas');

    if (!d.recompensas) { fail('No devuelve array "recompensas"'); return; }
    ok(`${d.recompensas.length} recompensa(s) disponible(s)`);
    if (typeof d.puntos_usuario !== 'number') warn('"puntos_usuario" no está en la respuesta');
    else ok(`puntos_usuario=${d.puntos_usuario}`);
}

async function testMisRecompensas() {
    title('7. Endpoint: mis-recompensas');
    const d = await get('mis-recompensas');

    if (!Array.isArray(d.canjeadas)) { fail('"canjeadas" no es array'); return; }
    ok(`mis-recompensas OK — ${d.canjeadas.length} canjeada(s)`);
}

async function testAccionInvalida() {
    title('8. Endpoint inválido (debe retornar 404)');
    const d = await get('ruta_inexistente');
    if (d.__status === 404 && d.error) ok('Respuesta 404 correcta para rutas desconocidas');
    else warn(`Esperado 404, recibido HTTP ${d.__status}`);
}

// ── Resumen ──────────────────────────────────────────────────
async function main() {
    console.log(`\n${BOLD}╔══════════════════════════════════════════╗${RESET}`);
    console.log(`${BOLD}║   GeoUTC — Script de Diagnóstico         ║${RESET}`);
    console.log(`${BOLD}╚══════════════════════════════════════════╝${RESET}`);
    console.log(`  Servidor: ${BASE}`);
    console.log(`  API:      ${API}`);

    const conectado = await testConexion();
    if (!conectado) {
        console.log(`\n${RED}${BOLD}El servidor no está disponible. Inicia XAMPP/WAMP/Laragon primero.${RESET}\n`);
        process.exit(1);
    }

    await testUsuario();
    await testLugares();
    await testComentarios();
    await testPublicarComentario();
    await testRecompensas();
    await testMisRecompensas();
    await testAccionInvalida();

    console.log(`\n${BOLD}══════════════ RESUMEN ══════════════${RESET}`);
    console.log(`  ${GREEN}✔ Pasaron: ${passed}${RESET}`);
    if (warnings) console.log(`  ${YELLOW}⚠ Avisos:  ${warnings}${RESET}`);
    if (failed)   console.log(`  ${RED}✘ Fallaron: ${failed}${RESET}`);
    console.log('');

    if (failed === 0 && warnings === 0) {
        console.log(`${GREEN}${BOLD}  ✅ Todo en orden — la app debería funcionar correctamente.${RESET}\n`);
    } else if (failed === 0) {
        console.log(`${YELLOW}${BOLD}  ⚠  Sin errores críticos, revisa los avisos arriba.${RESET}\n`);
    } else {
        console.log(`${RED}${BOLD}  ❌ Se encontraron errores — revisa los items marcados con ✘.${RESET}\n`);
    }
}

main().catch(e => {
    console.error(`\n${RED}Error fatal en el script: ${e.message}${RESET}\n`);
    process.exit(1);
});