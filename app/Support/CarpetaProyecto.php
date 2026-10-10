<?php

namespace App\Support;

use App\Models\Proyecto;
use Illuminate\Support\Str;

/**
 * Carpeta de cada proyecto en Windows, fuera del repo del panel (así los agentes no heredan sus reglas):
 *
 *   <ALMA_PROYECTOS_DIR>/<Proyecto>/
 *     AGENTS.md, CLAUDE.md (@AGENTS.md)                 → contexto para Codex, Cursor, OpenCode y Claude Code
 *     .mcp.json, .codex/config.toml, .cursor/mcp.json, opencode.json → MCP de figwright y del sitio
 *     Design/v1, Design/v2…                              → cada lectura del Figma (lectura.json + capturas/)
 *
 * Los archivos del agente se regeneran al crear el proyecto, al cambiar su conexión y al adoptar un diseño.
 */
class CarpetaProyecto
{
    /** Carpeta base vista desde WSL, o null si no está configurada o no existe. */
    public static function base(): ?string
    {
        $base = rtrim((string) config('alma.proyectos_dir'), '/');

        return ($base !== '' && is_dir($base)) ? $base : null;
    }

    public static function disponible(): bool
    {
        return self::base() !== null;
    }

    /** Nombre de carpeta válido en Windows (quita <>:"/\|?* y puntos o espacios finales). */
    public static function nombreCarpeta(string $proyecto): string
    {
        $limpio = trim(preg_replace('/[<>:"\/\\\\|?*\x00-\x1F]+/u', ' ', $proyecto));
        $limpio = rtrim(preg_replace('/\s+/', ' ', $limpio), ' .');

        return $limpio !== '' ? $limpio : Str::slug($proyecto);
    }

    public static function ruta(Proyecto $p): ?string
    {
        $base = self::base();

        return ($base && $p->carpeta) ? $base . '/' . $p->carpeta : null;
    }

    /** /mnt/c/Users/x/... → C:\Users\x\... (para mostrar y para abrir desde Windows). */
    public static function aWindows(?string $rutaWsl): ?string
    {
        if (! $rutaWsl) {
            return null;
        }
        if (preg_match('#^/mnt/([a-z])(/.*)?$#i', $rutaWsl, $m)) {
            return strtoupper($m[1]) . ':' . str_replace('/', '\\', $m[2] ?? '\\');
        }

        return $rutaWsl;
    }

    public static function rutaWindows(Proyecto $p): ?string
    {
        return self::aWindows(self::ruta($p));
    }

    /** Crea la carpeta (si hace falta) y escribe los archivos del agente. Devuelve los archivos escritos. */
    public static function preparar(Proyecto $p): array
    {
        if (! self::disponible()) {
            return [];
        }
        if (! $p->carpeta) {
            $p->forceFill(['carpeta' => self::nombreCarpeta($p->nombre)])->saveQuietly();
        }
        $raiz = self::ruta($p);
        foreach (['', '/Design', '/.codex', '/.cursor', '/.claude/agents', '/.opencode/agents', '/.alma/roles'] as $sub) {
            if (! is_dir($raiz . $sub)) {
                mkdir($raiz . $sub, 0775, true);
            }
        }

        $p->loadMissing('conexion');
        $archivos = [
            'AGENTS.md'          => PromptGuia::agentsMd($p),
            'CLAUDE.md'          => "@AGENTS.md\n",
            '.mcp.json'          => ConfigAgentes::claude($p, $p->conexion),
            '.codex/config.toml' => ConfigAgentes::codex($p, $p->conexion),
            '.cursor/mcp.json'   => ConfigAgentes::cursor($p, $p->conexion),
            'opencode.json'      => ConfigAgentes::opencode($p, $p->conexion),
        ] + RolesAgentes::archivos($p);
        foreach ($archivos as $nombre => $contenido) {
            file_put_contents($raiz . '/' . $nombre, $contenido);
        }

        return array_keys($archivos);
    }

    /** Carpeta Design/vN del proyecto (vista desde WSL), creándola si hace falta. */
    public static function carpetaVersion(Proyecto $p, int $version): ?string
    {
        if (! self::disponible()) {
            return null;
        }
        if (! $p->carpeta) {
            self::preparar($p);
        }
        $dir = self::ruta($p) . "/Design/v{$version}";
        foreach (['/capturas', '/secciones'] as $sub) {
            if (! is_dir($dir . $sub)) {
                mkdir($dir . $sub, 0775, true);
            }
        }

        return $dir;
    }
}
