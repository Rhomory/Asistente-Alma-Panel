#!/usr/bin/env node
// Asistente Alma: instalador y comandos de uso diario, sin clonar ni hacer doble clic en archivos.
//
//   npx github:Rhomory/Asistente-Alma-Panel              → instala o completa todo (panel, puente, figwright, plugin, skill)
//   npx github:Rhomory/Asistente-Alma-Panel actualizar   → trae la última versión del panel y reinstala lo de Windows
//   npx github:Rhomory/Asistente-Alma-Panel panel        → enciende el panel y lo abre en el navegador
//   npx github:Rhomory/Asistente-Alma-Panel diagnostico  → revisa todo el entorno
//
// Tras la primera instalación queda el comando `alma` en Windows (alma panel, alma diagnostico, alma registro:cola "Cota"…).
// Se ejecuta en Windows; el panel vive en WSL (Ubuntu) y se maneja con wsl.exe. Solo usa módulos de Node.

import { spawnSync, spawn } from 'node:child_process';
import { existsSync, mkdirSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { createInterface } from 'node:readline/promises';
import { join } from 'node:path';
import os from 'node:os';

const REPO = 'https://github.com/Rhomory/Asistente-Alma-Panel.git';
const args = process.argv.slice(2);
const opcion = (nombre, porDefecto = null) => {
    const i = args.findIndex(a => a === `--${nombre}` || a.startsWith(`--${nombre}=`));
    if (i < 0) return porDefecto;
    const a = args[i];
    return a.includes('=') ? a.split('=').slice(1).join('=') : (args[i + 1] && !args[i + 1].startsWith('--') ? args[i + 1] : true);
};
const SI = Boolean(opcion('si') || opcion('yes'));
const comando = args.some(a => a === '--help' || a === '-h') ? 'ayuda' : (args.find(a => !a.startsWith('--')) || 'instalar').toLowerCase();

// ── Salida ───────────────────────────────────────────────
const c = (n, t) => (process.stdout.isTTY ? `\x1b[${n}m${t}\x1b[0m` : t);
const titulo = t => console.log('\n' + c('1;36', t));
const ok = t => console.log(`  ${c(32, '✔')} ${t}`);
const aviso = t => console.log(`  ${c(33, '!')} ${t}`);
const falla = t => console.log(`  ${c(31, '✖')} ${t}`);
const nota = t => console.log(`    ${c(2, t)}`);
const salir = (t, codigo = 1) => { falla(t); process.exit(codigo); };

async function preguntar(texto) {
    if (SI) return true;
    if (!process.stdin.isTTY) return false;
    const rl = createInterface({ input: process.stdin, output: process.stdout });
    const r = (await rl.question(`  ${c(36, '?')} ${texto} (S/n) `)).trim().toLowerCase();
    rl.close();
    return r === '' || r.startsWith('s') || r.startsWith('y');
}

// ── Procesos ─────────────────────────────────────────────
function correr(cmd, argumentos, { heredar = false, entrada } = {}) {
    const r = spawnSync(cmd, argumentos, { stdio: heredar ? 'inherit' : 'pipe', input: entrada, windowsHide: true });
    return { ok: r.status === 0, codigo: r.status, out: r.stdout ? r.stdout.toString() : '', err: r.stderr ? r.stderr.toString() : '' };
}
let DISTRO = opcion('distro', null);
// Comandos de bash dentro de la distro. Se evita usar comillas dobles en los comandos para no pelear con wsl.exe.
const wsl = (script, opciones = {}) => correr('wsl.exe', ['-d', DISTRO, '--', 'bash', '-lc', script], opciones);
const enPanel = (script, opciones = {}) => wsl(`cd '${PANEL}' && ${script}`, opciones);

// ── Datos del entorno ────────────────────────────────────
let PANEL = null;
let HOME_WSL = null;

function distros() {
    const r = spawnSync('wsl.exe', ['-l', '-q'], { windowsHide: true });
    if (r.status !== 0 || !r.stdout) return null;
    // wsl -l escribe en UTF-16
    return r.stdout.toString('utf16le').replace(/\0/g, '').split(/\r?\n/).map(s => s.trim()).filter(Boolean);
}

function prepararEntorno({ exigirPanel = true } = {}) {
    if (process.platform !== 'win32') {
        salir('Este comando se ejecuta en Windows (PowerShell o CMD). En Ubuntu usa directamente php artisan dentro del panel.');
    }
    const lista = distros();
    if (!lista) salir('WSL no está instalado. En PowerShell como administrador: wsl --install -d Ubuntu (y reinicia).');
    DISTRO ??= lista.find(d => /^ubuntu/i.test(d)) || lista[0];
    if (!lista.includes(DISTRO)) salir(`No encuentro la distro "${DISTRO}". Disponibles: ${lista.join(', ')}.`);
    HOME_WSL = wsl('echo $HOME').out.trim();
    PANEL = opcion('panel', null) || `${HOME_WSL}/proyectos/asistente-alma-panel`;
    if (exigirPanel && !wsl(`test -f '${PANEL}/artisan'`).ok) {
        salir(`No encuentro el panel en ${PANEL} (${DISTRO}). Instálalo con: npx github:Rhomory/Asistente-Alma-Panel`);
    }
}

const rutaWindows = rutaWsl => `\\\\wsl.localhost\\${DISTRO}${rutaWsl.replace(/\//g, '\\')}`;
const leerEnv = clave => enPanel(`grep -E '^${clave}=' .env 2>/dev/null | tail -1 | cut -d= -f2-`).out.trim().replace(/^['"]|['"]$/g, '');

// ── Pasos ────────────────────────────────────────────────
function pasoRequisitos() {
    titulo('1. Requisitos de Windows');
    const [mayor, menor] = process.versions.node.split('.').map(Number);
    if (mayor > 20 || (mayor === 20 && menor >= 19)) ok(`Node ${process.versions.node}`);
    else salir(`Node ${process.versions.node}: se necesita 20.19 o superior (lo usan los MCP). Descárgalo de nodejs.org.`);
    ok(`WSL con la distro ${DISTRO}`);
    const agentes = { 'Claude Code': 'claude', Codex: 'codex', OpenCode: 'opencode', Cursor: 'cursor' };
    const hay = Object.entries(agentes).filter(([, exe]) => correr('where.exe', [exe]).ok).map(([n]) => n);
    if (hay.length) ok(`Agentes encontrados: ${hay.join(', ')}`);
    else {
        aviso('No encontré ningún agente (Claude Code, Codex, OpenCode o Cursor).');
        nota('Claude Code: npm i -g @anthropic-ai/claude-code   ·   Codex: npm i -g @openai/codex   ·   OpenCode: npm i -g opencode-ai');
    }
    return hay;
}

async function pasoPanel() {
    titulo(`2. Panel en ${DISTRO}`);
    if (wsl(`test -f '${PANEL}/artisan'`).ok) {
        ok(`Panel en ${PANEL}`);
        return;
    }
    if (!wsl('command -v git').ok) await instalarApt(['git']);
    if (!(await preguntar(`Clonar el panel en ${PANEL}?`))) salir('Sin el panel no se puede seguir.');
    const r = wsl(`mkdir -p '${PANEL.replace(/\/[^/]+$/, '')}' && git clone ${REPO} '${PANEL}'`, { heredar: true });
    if (!r.ok) salir('No se pudo clonar el repositorio. Revisa tu conexión y vuelve a intentar.');
    ok(`Panel clonado en ${PANEL}`);
}

async function instalarApt(paquetes) {
    aviso(`Faltan paquetes de Ubuntu: ${paquetes.join(' ')}`);
    const orden = `sudo apt-get update && sudo apt-get install -y ${paquetes.join(' ')}`;
    nota(orden);
    if (!(await preguntar('Instalarlos ahora? (Ubuntu te pedirá tu contraseña)'))) salir(`Instálalos con: ${orden}`);
    if (!wsl(orden, { heredar: true }).ok) salir('No se pudieron instalar los paquetes. Revisa el mensaje de apt.');
    ok('Paquetes instalados');
}

async function pasoPaquetes() {
    titulo('3. PHP y herramientas en Ubuntu');
    const faltan = [];
    const version = wsl("php -r 'echo PHP_MAJOR_VERSION, chr(46), PHP_MINOR_VERSION;'");
    const prefijo = version.ok ? `php${version.out.trim()}` : 'php';
    const [pMayor, pMenor] = version.out.trim().split('.').map(Number);
    if (!version.ok) faltan.push('php-cli');
    else if (pMayor < 8 || (pMayor === 8 && pMenor < 3)) salir(`PHP ${version.out.trim()}: se necesita 8.3 o superior.`);
    else ok(`PHP ${version.out.trim()}`);
    const modulos = version.ok ? wsl('php -m').out.toLowerCase().split(/\r?\n/) : [];
    for (const [modulo, paquete] of [['pdo_sqlite', 'sqlite3'], ['mbstring', 'mbstring'], ['curl', 'curl'], ['dom', 'xml'], ['zip', 'zip']]) {
        if (!modulos.includes(modulo)) faltan.push(`${prefijo}-${paquete}`);
    }
    for (const herramienta of ['composer', 'git']) {
        if (!wsl(`command -v ${herramienta}`).ok) faltan.push(herramienta);
    }
    if (faltan.length) await instalarApt([...new Set(faltan)]);
    else ok('Extensiones de PHP, Composer y git');
}

function pasoPreparar() {
    titulo('4. Dependencias y base de datos del panel');
    const pasos = [
        ['Dependencias (composer install)', 'composer install --no-interaction --no-progress'],
        ['Archivo .env', '[ -f .env ] || cp .env.example .env'],
        ['Clave de la aplicación', 'grep -q ^APP_KEY=base64 .env || php artisan key:generate --force'],
        ['Base de datos', 'touch database/database.sqlite && php artisan migrate --force'],
    ];
    for (const [nombre, script] of pasos) {
        const r = enPanel(script);
        if (r.ok) ok(nombre);
        else { falla(nombre); console.log(r.out + r.err); process.exit(1); }
    }
}

function pasoWindows() {
    titulo('5. Puente, skill y figwright en Windows');
    const instalador = rutaWindows(`${PANEL}/scripts/instalar-windows.ps1`);
    const r = correr('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', instalador, '-RegistrarFigwright'], { heredar: true });
    if (!r.ok) aviso('El instalador de Windows terminó con avisos: revisa lo marcado arriba.');
}

async function pasoPlugin() {
    titulo('6. Plugin de figwright para Figma');
    const version = leerEnv('ALMA_FIGWRIGHT') || '0.6.0';
    const destino = join(os.homedir(), '.alma', 'figwright-plugin', `v${version}`);
    const manifiesto = buscarManifiesto(destino);
    if (manifiesto) {
        ok(`Plugin ${version} ya descargado`);
    } else {
        const url = `https://github.com/awdr74100/figwright/releases/download/v${version}/figwright-plugin-v${version}.zip`;
        try {
            const res = await fetch(url);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            mkdirSync(destino, { recursive: true });
            const zip = join(destino, 'plugin.zip');
            writeFileSync(zip, Buffer.from(await res.arrayBuffer()));
            const x = correr('powershell.exe', ['-NoProfile', '-Command', `Expand-Archive -Force -LiteralPath '${zip}' -DestinationPath '${destino}'; Remove-Item -LiteralPath '${zip}'`]);
            if (!x.ok) throw new Error('no se pudo descomprimir');
            ok(`Plugin ${version} descargado en ${destino}`);
        } catch (e) {
            aviso(`No pude descargar el plugin (${e.message}). Bájalo de https://github.com/awdr74100/figwright/releases (v${version}).`);
            return;
        }
    }
    const ruta = buscarManifiesto(destino);
    nota('En Figma de escritorio: Plugins › Development › Import plugin from manifest… y elige:');
    nota(ruta || join(destino, 'manifest.json'));
    nota('Si ya lo tenías importado, reemplaza los archivos de esa carpeta y reinicia Figma.');
}

function buscarManifiesto(dir, profundidad = 0) {
    if (!existsSync(dir) || profundidad > 3) return null;
    for (const nombre of readdirSync(dir)) {
        const ruta = join(dir, nombre);
        if (nombre === 'manifest.json') return ruta;
        if (statSync(ruta).isDirectory()) {
            const r = buscarManifiesto(ruta, profundidad + 1);
            if (r) return r;
        }
    }
    return null;
}

// ── Comandos ─────────────────────────────────────────────
async function instalar() {
    console.log(c('1', 'Asistente Alma · instalación'));
    prepararEntorno({ exigirPanel: false });
    pasoRequisitos();
    await pasoPanel();
    await pasoPaquetes();
    pasoPreparar();
    pasoWindows();
    await pasoPlugin();
    titulo('Listo');
    nota('Abre una terminal nueva (para que Windows vea el comando alma) y enciende el panel con:  alma panel');
    nota('Revisa el entorno cuando quieras con:  alma diagnostico');
    if (await preguntar('Encender el panel ahora?')) await panel();
}

async function actualizar() {
    console.log(c('1', 'Asistente Alma · actualización'));
    prepararEntorno();
    titulo('Código del panel');
    const r = enPanel('git pull --ff-only', { heredar: true });
    if (!r.ok) salir('No se pudo actualizar con git pull (¿hay cambios locales sin subir?).');
    await pasoPaquetes();
    pasoPreparar();
    pasoWindows();
    await pasoPlugin();
    titulo('Actualizado');
    nota('En cada proyecto existente pulsa "Actualizar archivos" para que reciba el AGENTS.md y los roles nuevos.');
}

async function panel() {
    if (!PANEL) prepararEntorno();
    const puerto = leerEnv('SERVER_PORT') || '8000';
    const url = `http://127.0.0.1:${puerto}`;
    const responde = async () => { try { return (await fetch(`${url}/estado/version`)).ok; } catch { return false; } };
    if (await responde()) {
        ok(`El panel ya está encendido en ${url}`);
    } else {
        // Ventana propia para el servidor: se cierra y el panel se apaga.
        spawn('cmd.exe', ['/c', 'start', 'Asistente Alma - panel', 'wsl.exe', '-d', DISTRO, '--cd', PANEL, '--', 'php', 'artisan', 'serve', `--port=${puerto}`], { detached: true, stdio: 'ignore' }).unref();
        process.stdout.write('  Encendiendo el panel');
        let listo = false;
        for (let i = 0; i < 40 && !listo; i++) { await new Promise(r => setTimeout(r, 500)); process.stdout.write('.'); listo = await responde(); }
        console.log('');
        if (!listo) salir('El panel no respondió. Mira la ventana "Asistente Alma - panel" para ver el error.');
        ok(`Panel encendido en ${url} (déjalo abierto mientras trabajas)`);
    }
    spawn('cmd.exe', ['/c', 'start', '', url], { detached: true, stdio: 'ignore' }).unref();
}

function puente(argumentos) {
    const alma = join(os.homedir(), '.alma', 'alma.ps1');
    if (!existsSync(alma)) salir('Falta el puente. Instala primero con: npx github:Rhomory/Asistente-Alma-Panel');
    const r = correr('powershell.exe', ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', alma, ...argumentos], { heredar: true });
    process.exit(r.codigo ?? 1);
}

function ayuda() {
    console.log(`Asistente Alma

  npx github:Rhomory/Asistente-Alma-Panel [comando] [opciones]

  instalar      Instala o completa todo (por defecto): panel en WSL, PHP, puente, skill, figwright y su plugin
  actualizar    Trae la última versión del panel y reinstala lo de Windows
  panel         Enciende el panel y lo abre en el navegador
  diagnostico   Revisa distro, panel, PHP, figwright, skills y versiones
  <comando>     Cualquier comando del panel, ej. registro:cola "Cota"

  Opciones: --distro Ubuntu  --panel /home/usuario/ruta  --si (responde que sí a todo)

  Tras instalar, en una terminal nueva basta con:  alma panel  ·  alma diagnostico  ·  alma registro:cola "Cota"`);
}

const comandos = { instalar, actualizar, panel, diagnostico: () => puente(['diagnostico']), ayuda, '-h': ayuda, '--help': ayuda };
try {
    if (comandos[comando]) await comandos[comando]();
    else puente(args.filter(a => !/^--(si|yes|distro|panel)/.test(a)));
} catch (e) {
    salir(e.message);
}
