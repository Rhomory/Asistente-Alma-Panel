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

    private static ?array $comando = null;

    /** Dónde monta WSL las unidades de Windows (las pruebas lo apuntan a una carpeta temporal). */
    public static string $mnt = '/mnt';

    /**
     * Cómo lanzar figwright: con node sobre la instalación de %USERPROFILE%\.alma\mcp si está en la versión fija
     * (arranca en ~0,5 s y sin red), o con npx si no (3-5 s por arranque). La instala el instalador de Windows.
     *
     * @return array{0: string, 1: list<string>} [comando, argumentos]; con npx, sin el "cmd /c" que pide cada agente
     */
    public static function comandoFigwright(): array
    {
        return self::$comando ??= self::localFigwright() ?? ['npx', ['-y', self::figwright()]];
    }

    /** Para las pruebas: vuelve a buscar la instalación local. */
    public static function olvidarComando(): void
    {
        self::$comando = null;
    }

    /** node + index.mjs de .alma\mcp, si el paquete instalado es la versión fija. */
    private static function localFigwright(): ?array
    {
        $alma = self::carpetaAlma();
        if (! $alma) {
            return null;
        }
        [$almaWin, $almaWsl] = $alma;
        $pkg = json_decode((string) @file_get_contents($almaWsl . '/mcp/node_modules/@figwright/mcp/package.json'), true);
        $bin = $pkg['bin'] ?? null;
        $bin = is_array($bin) ? ($bin['figwright-mcp'] ?? null) : $bin;
        if (! is_string($bin) || ($pkg['version'] ?? null) !== config('alma.figwright', '0.6.0')) {
            return null;
        }
        $relativo = str_replace('/', '\\', preg_replace('#^\./#', '', $bin));

        return ['node', [$almaWin . '\mcp\node_modules\@figwright\mcp\\' . $relativo]];
    }

    /**
     * Carpeta %USERPROFILE%\.alma vista desde Windows y desde WSL, deducida de ALMA_PUENTE
     * (C:\Users\<usuario>\.alma\alma.ps1). null si el puente no está configurado.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function carpetaAlma(): ?array
    {
        if (! preg_match('#^([A-Za-z]):[\\\\/](.+)[\\\\/][^\\\\/]+$#', (string) config('alma.puente'), $m)) {
            return null;
        }
        $resto = str_replace('/', '\\', $m[2]);

        return [strtoupper($m[1]) . ':\\' . $resto, self::$mnt . '/' . strtolower($m[1]) . '/' . str_replace('\\', '/', $resto)];
    }

    /**
     * Shell de OpenCode en Windows. Sin esto usa pwsh o, si no está, cmd.exe, donde los comandos del puente
     * ("$HOME\.alma\alma.ps1", $env:TEMP) no funcionan. pwsh si está instalado (mejor UTF-8); si no, powershell.
     */
    public static function shellOpenCode(): string
    {
        $alma = self::carpetaAlma();
        $home = $alma ? dirname($alma[1]) : null;
        $pwsh = [self::$mnt . '/c/Program Files/PowerShell/7/pwsh.exe'];
        if ($home) {
            $pwsh[] = $home . '/AppData/Local/Microsoft/WindowsApps/pwsh.exe';
        }
        foreach ($pwsh as $ruta) {
            if (file_exists($ruta)) {
                return 'pwsh';
            }
        }

        return 'powershell';
    }

    /** Nombres de los servidores MCP del opencode.json global (%USERPROFILE%\.config\opencode), en forma v1 o v2. */
    private static function mcpGlobalesOpenCode(): array
    {
        $alma = self::carpetaAlma();
        $conf = $alma ? json_decode((string) @file_get_contents(dirname($alma[1]) . '/.config/opencode/opencode.json'), true) : null;
        $mcp = is_array($conf['mcp'] ?? null) ? $conf['mcp'] : [];
        $nombres = array_keys(is_array($mcp['servers'] ?? null) ? $mcp['servers'] + $mcp : $mcp);

        return array_values(array_filter($nombres, fn ($n) => $n !== 'servers' && preg_match('/^[\w.-]+$/', $n)));
    }

    /** Claude Code y Codex en Windows necesitan "cmd /c" delante de npx (node no). */
    private static function comandoConCmd(): array
    {
        [$cmd, $args] = self::comandoFigwright();

        return $cmd === 'npx' ? ['cmd', ['/c', 'npx', ...$args]] : [$cmd, $args];
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
        [$cmd, $args] = self::comandoConCmd();
        $servidores = ['figwright' => ['type' => 'stdio', 'command' => $cmd, 'args' => $args]];
        if ($c) {
            $servidores[$c->nombre_mcp] = ['type' => 'http', 'url' => self::urlSitio($c),
                'headers' => ['Authorization' => '${' . PromptElementor::variableAuth($p->nombre) . '}']];
        }

        return self::json(['mcpServers' => $servidores]);
    }

    public static function cursor(Proyecto $p, ?Conexion $c): string
    {
        [$cmd, $args] = self::comandoFigwright();
        $servidores = ['figwright' => ['type' => 'stdio', 'command' => $cmd, 'args' => $args]];
        if ($c) {
            $servidores[$c->nombre_mcp] = ['url' => self::urlSitio($c),
                'headers' => ['Authorization' => '${env:' . PromptElementor::variableAuth($p->nombre) . '}']];
        }

        return self::json(['mcpServers' => $servidores]);
    }

    /** Herramientas de figwright que usa el flujo (todas de lectura). Las otras ~100 no se ofrecen al modelo. */
    public const FIGWRIGHT_LECTURA = [
        'ping', 'list_files', 'use_file', 'get_metadata', 'get_pages', 'get_selection', 'get_design_context',
        'get_variable_defs', 'get_styles', 'scan_nodes_by_types', 'search_nodes', 'get_nodes_info', 'save_screenshots', 'get_screenshot',
    ];

    /**
     * OpenCode 2 (formato nativo v2):
     *  - codemode false: los modelos gratuitos llaman mejor las herramientas directas que escribiendo JS en "execute".
     *  - permissions: oculta las herramientas de escritura de figwright (menos contexto) y las skills ajenas al flujo.
     *  - oauth false: si la credencial falta, falla con 401 claro en vez de intentar OAuth.
     */
    public static function opencode(Proyecto $p, ?Conexion $c): string
    {
        [$cmd, $args] = self::comandoFigwright();
        $mcp = ['figwright' => ['type' => 'local', 'command' => [$cmd, ...$args], 'codemode' => false]];
        if ($c) {
            $mcp[$c->nombre_mcp] = ['type' => 'remote', 'url' => self::urlSitio($c), 'oauth' => false, 'codemode' => false,
                'headers' => ['Authorization' => '{env:' . PromptElementor::variableAuth($p->nombre) . '}']];
        }

        // MCP globales del equipo que no son del flujo (Pencil, etc.): sus herramientas no se ofrecen aquí.
        // (en la acción, OpenCode cambia por "_" lo que no sea letra, número o "_").
        $permisos = array_map(fn ($s) => ['action' => preg_replace('/\W/', '_', $s) . '_*', 'resource' => '*', 'effect' => 'deny'],
            array_values(array_diff(self::mcpGlobalesOpenCode(), ['figwright', $c?->nombre_mcp])));
        $permisos[] = ['action' => 'figwright_*', 'resource' => '*', 'effect' => 'deny'];
        foreach (self::FIGWRIGHT_LECTURA as $herramienta) {
            $permisos[] = ['action' => "figwright_{$herramienta}", 'resource' => '*', 'effect' => 'allow'];
        }
        $permisos[] = ['action' => 'skill', 'resource' => '*', 'effect' => 'deny'];
        $permisos[] = ['action' => 'skill', 'resource' => 'alma-figma', 'effect' => 'allow'];

        // Forma nativa v2 (mcp.servers): con la forma v1 (mcp.<nombre>) OpenCode 2 descarta "codemode".
        return self::json(['$schema' => 'https://opencode.ai/config.json', 'shell' => self::shellOpenCode(), 'mcp' => ['servers' => $mcp], 'permissions' => $permisos]);
    }

    public static function codex(Proyecto $p, ?Conexion $c): string
    {
        [$cmd, $args] = self::comandoConCmd();
        $toml = "# Generado por el panel Asistente Alma. Codex lo lee si la carpeta está marcada como de confianza.\n"
            . "[mcp_servers.figwright]\ncommand = " . self::tomlTexto($cmd)
            . "\nargs = [" . implode(', ', array_map(self::tomlTexto(...), $args)) . "]\n";
        if ($c) {
            $toml .= "\n[mcp_servers.{$c->nombre_mcp}]\nurl = \"" . self::urlSitio($c) . "\"\n"
                . 'env_http_headers = { "Authorization" = "' . PromptElementor::variableAuth($p->nombre) . "\" }\n";
        }

        return $toml;
    }

    /** Cadena TOML: las cadenas JSON son válidas en TOML (mismas comillas y escapes). */
    private static function tomlTexto(string $v): string
    {
        return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function json(array $datos): string
    {
        return json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
