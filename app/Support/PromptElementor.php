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

    /** Configuración MCP de los cuatro agentes para esta conexión (ver ConfigAgentes). */
    public static function configAgentes(\App\Models\Conexion $c): array
    {
        return ConfigAgentes::todos($c->proyecto, $c);
    }

    /**
     * Encabezado de autorización ("Basic …") desde el prompt de Elementor o desde usuario + contraseña de
     * aplicación. Se usa solo para entregarlo a Windows; nunca se guarda en el panel.
     */
    public static function credencial(?string $prompt, ?string $usuario = null, ?string $clave = null): ?string
    {
        $prompt = (string) $prompt;
        if (preg_match('/Authorization["\']?\s*[:=]\s*["\']?Basic\s+([A-Za-z0-9+\/=]+)/i', $prompt, $m)) {
            return 'Basic ' . $m[1];
        }
        $usuario = $usuario ?: (self::extraer($prompt)['usuario_wp'] ?? null);
        if (! $clave && preg_match('/\b((?:[A-Za-z0-9]{4} ){5}[A-Za-z0-9]{4})\b/', $prompt, $m)) {
            $clave = $m[1];
        }

        return ($usuario && $clave) ? 'Basic ' . base64_encode("{$usuario}:{$clave}") : null;
    }

    /** Nombre sugerido del servidor MCP para un proyecto: elementor-<proyecto>. */
    public static function nombreSugerido(string $proyecto): string
    {
        return 'elementor-' . Str::slug($proyecto);
    }
}
