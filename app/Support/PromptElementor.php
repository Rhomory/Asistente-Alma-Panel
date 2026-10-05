<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lee el prompt de conexión que genera Elementor (Elementor › Elementor MCP › Generate Prompt)
 * y devuelve solo lo que el panel puede guardar: sitio, endpoint, usuario y nombre del servidor.
 * La contraseña de aplicación y cualquier token se reemplazan por [oculto] antes de guardar.
 */
class PromptElementor
{
    /** @return array{texto: string, ocultos: int} */
    public static function sanear(string $prompt): array
    {
        $ocultos = 0;
        $reglas = [
            // Contraseña de aplicación de WordPress: 6 bloques de 4 caracteres.
            '/\b(?:[A-Za-z0-9]{4} ){5}[A-Za-z0-9]{4}\b/' => '[oculto]',
            // Cabeceras Authorization: Basic/Bearer …
            '/(Authorization["\']?\s*[:=]\s*["\']?(?:Basic|Bearer)\s+)[A-Za-z0-9+\/=._-]+/i' => '$1[oculto]',
            // usuario:contraseña@ dentro de una URL
            '#(https?://)[^/\s:@]+:[^/\s@]+@#i' => '$1[oculto]@',
            // claves con nombre sensible: password, token, secret, api_key…
            '/(["\']?[\w-]*(?:password|passwd|token|secret|api[_-]?key)[\w-]*["\']?\s*[:=]\s*["\']?)(?!\[oculto\])[^"\'\s,}]+/i' => '$1[oculto]',
            // cadenas largas tipo base64 que hayan quedado sueltas
            '/\b[A-Za-z0-9+]{40,}={0,2}/' => '[oculto]',
        ];
        foreach ($reglas as $patron => $reemplazo) {
            $prompt = preg_replace($patron, $reemplazo, $prompt, -1, $n);
            $ocultos += $n;
        }

        return ['texto' => trim($prompt), 'ocultos' => $ocultos];
    }

    /** @return array{sitio_url: ?string, endpoint: ?string, usuario_wp: ?string, nombre_mcp: ?string} */
    public static function extraer(string $prompt): array
    {
        $endpoint = preg_match('#https?://[^\s"\'<>]+/wp-json/[^\s"\'<>]*#i', $prompt, $m) ? rtrim($m[0], '.,;)') : null;
        $sitio = null;
        if ($endpoint) {
            $p = parse_url($endpoint);
            $sitio = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        } elseif (preg_match('#https?://[^\s"\'<>/]+#i', $prompt, $m)) {
            $sitio = $m[0];
        }

        $usuario = preg_match('/["\']?(?:wp[_-]?)?user(?:name)?["\']?\s*[:=]\s*["\']?([\w.@-]+)/i', $prompt, $m) ? $m[1] : null;

        $nombre = null;
        if (preg_match('/"mcpServers"\s*:\s*\{\s*"([\w.-]+)"/', $prompt, $m)) {
            $nombre = $m[1];
        } elseif (preg_match('/claude\s+mcp\s+add(?:-json)?((?:\s+\S+)+)/i', $prompt, $m)) {
            // primer argumento que no sea opción ni valor de opción
            $partes = preg_split('/\s+/', trim($m[1]));
            for ($i = 0; $i < count($partes); $i++) {
                if (str_starts_with($partes[$i], '-')) {
                    if (! str_contains($partes[$i], '=')) {
                        $i++;
                    }
                    continue;
                }
                $nombre = preg_match('/^[\w.-]+$/', $partes[$i]) ? $partes[$i] : null;
                break;
            }
        }

        return ['sitio_url' => $sitio, 'endpoint' => $endpoint, 'usuario_wp' => $usuario, 'nombre_mcp' => $nombre];
    }

    /** Variable de entorno de Windows donde vive el encabezado de autorización del sitio (nunca el panel). */
    public static function variableAuth(string $proyecto): string
    {
        return 'ALMA_' . strtoupper(str_replace('-', '_', Str::slug($proyecto))) . '_AUTH';
    }

    /**
     * Configuración MCP lista para pegar en cada agente: el servidor del sitio con el nombre del proyecto
     * y figwright. La credencial se referencia por variable de entorno; el secreto nunca sale del equipo.
     *
     * @return array<string, array{titulo: string, archivo: string, codigo: string}>
     */
    public static function configAgentes(\App\Models\Conexion $c): array
    {
        $nombre = $c->nombre_mcp;
        $url = $c->endpoint ?: rtrim($c->sitio_url, '/') . '/wp-json/…';
        $var = self::variableAuth($c->proyecto->nombre);

        $claude = json_encode(['mcpServers' => [$nombre => [
            'type' => 'http', 'url' => $url, 'headers' => ['Authorization' => '${' . $var . '}'],
        ]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $codex = "[mcp_servers.figwright]\ncommand = \"cmd\"\nargs = [\"/c\", \"npx\", \"-y\", \"@figwright/mcp@latest\"]\n\n"
            . "[mcp_servers.{$nombre}]\nurl = \"{$url}\"\nenv_http_headers = { \"Authorization\" = \"{$var}\" }";

        $opencode = json_encode([
            '$schema' => 'https://opencode.ai/config.json',
            'mcp' => [
                'figwright' => ['type' => 'local', 'command' => ['npx', '-y', '@figwright/mcp@latest']],
                $nombre => ['type' => 'remote', 'url' => $url, 'headers' => ['Authorization' => '{env:' . $var . '}']],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'claude'   => ['titulo' => 'Claude Code', 'archivo' => '.mcp.json en la carpeta del cliente (figwright va aparte, a nivel usuario)', 'codigo' => $claude],
            'codex'    => ['titulo' => 'Codex', 'archivo' => '~/.codex/config.toml', 'codigo' => $codex],
            'opencode' => ['titulo' => 'OpenCode', 'archivo' => 'opencode.json en la carpeta del cliente', 'codigo' => $opencode],
        ];
    }

    /** Nombre sugerido del servidor MCP para un proyecto: elementor-<proyecto>. */
    public static function nombreSugerido(string $proyecto): string
    {
        return 'elementor-' . Str::slug($proyecto);
    }
}
