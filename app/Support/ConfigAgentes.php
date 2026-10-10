<?php

namespace App\Support;

use App\Models\Conexion;
use App\Models\Proyecto;

/**
 * Configuración MCP del proyecto en el formato de cada agente (verificado en su documentación):
 *  - Claude Code: .mcp.json            → headers con ${VAR}; pide aprobar el archivo la primera vez.
 *  - Codex:       .codex/config.toml   → env_http_headers; la carpeta debe estar marcada como de confianza.
 *  - Cursor:      .cursor/mcp.json     → headers con ${env:VAR}.
 *  - OpenCode:    opencode.json        → headers con {env:VAR}.
 * Incluye figwright y, si el proyecto tiene conexión, su servidor de Elementor. La credencial se referencia
 * por la variable de Windows ALMA_<PROYECTO>_AUTH; el secreto nunca se escribe.
 */
class ConfigAgentes
{
    /** Paquete de figwright con la versión fija del panel (ver config/alma.php). */
    public static function figwright(): string
    {
        return '@figwright/mcp@' . config('alma.figwright', '0.6.0');
    }

    /** @return array<string, array{titulo: string, archivo: string, codigo: string}> */
    public static function todos(Proyecto $p, ?Conexion $c = null): array
    {
        $c ??= $p->conexion;

        return [
            'claude'   => ['titulo' => 'Claude Code', 'archivo' => '.mcp.json', 'codigo' => self::claude($p, $c)],
            'codex'    => ['titulo' => 'Codex', 'archivo' => '.codex/config.toml', 'codigo' => self::codex($p, $c)],
            'cursor'   => ['titulo' => 'Cursor', 'archivo' => '.cursor/mcp.json', 'codigo' => self::cursor($p, $c)],
            'opencode' => ['titulo' => 'OpenCode', 'archivo' => 'opencode.json', 'codigo' => self::opencode($p, $c)],
        ];
    }

    public static function urlSitio(Conexion $c): string
    {
        return $c->endpoint ?: rtrim($c->sitio_url, '/') . '/wp-json/…';
    }

    public static function claude(Proyecto $p, ?Conexion $c): string
    {
        $servidores = ['figwright' => ['type' => 'stdio', 'command' => 'cmd', 'args' => ['/c', 'npx', '-y', self::figwright()]]];
        if ($c) {
            $servidores[$c->nombre_mcp] = ['type' => 'http', 'url' => self::urlSitio($c),
                'headers' => ['Authorization' => '${' . PromptElementor::variableAuth($p->nombre) . '}']];
        }

        return self::json(['mcpServers' => $servidores]);
    }

    public static function cursor(Proyecto $p, ?Conexion $c): string
    {
        $servidores = ['figwright' => ['type' => 'stdio', 'command' => 'npx', 'args' => ['-y', self::figwright()]]];
        if ($c) {
            $servidores[$c->nombre_mcp] = ['url' => self::urlSitio($c),
                'headers' => ['Authorization' => '${env:' . PromptElementor::variableAuth($p->nombre) . '}']];
        }

        return self::json(['mcpServers' => $servidores]);
    }

    public static function opencode(Proyecto $p, ?Conexion $c): string
    {
        $mcp = ['figwright' => ['type' => 'local', 'command' => ['npx', '-y', self::figwright()]]];
        if ($c) {
            $mcp[$c->nombre_mcp] = ['type' => 'remote', 'url' => self::urlSitio($c),
                'headers' => ['Authorization' => '{env:' . PromptElementor::variableAuth($p->nombre) . '}']];
        }

        return self::json(['$schema' => 'https://opencode.ai/config.json', 'mcp' => $mcp]);
    }

    public static function codex(Proyecto $p, ?Conexion $c): string
    {
        $toml = "# Generado por el panel Asistente Alma. Codex lo lee si la carpeta está marcada como de confianza.\n"
            . "[mcp_servers.figwright]\ncommand = \"cmd\"\nargs = [\"/c\", \"npx\", \"-y\", \"" . self::figwright() . "\"]\n";
        if ($c) {
            $toml .= "\n[mcp_servers.{$c->nombre_mcp}]\nurl = \"" . self::urlSitio($c) . "\"\n"
                . 'env_http_headers = { "Authorization" = "' . PromptElementor::variableAuth($p->nombre) . "\" }\n";
        }

        return $toml;
    }

    private static function json(array $datos): string
    {
        return json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
