<?php

namespace App\Support;

/**
 * Habla con Windows desde WSL a través del puente alma.ps1 (interop de WSL): abrir la carpeta de un proyecto,
 * una terminal o Cursor, y guardar la credencial de un sitio como variable de entorno del usuario.
 * Los secretos viajan por la entrada estándar del proceso, nunca en la línea de comandos ni en la base.
 * En las pruebas no ejecuta nada: guarda las llamadas en self::$llamadas.
 */
class Windows
{
    /** @var array<int, array{accion: string, args: array, env: array}> */
    public static array $llamadas = [];

    public static function disponible(): bool
    {
        return app()->environment('testing') || (config('alma.puente') && is_file('/mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe'));
    }

    public static function abrirCarpeta(string $rutaWindows): bool
    {
        return self::puente('abrir-carpeta', [$rutaWindows]);
    }

    public static function abrirTerminal(string $rutaWindows): bool
    {
        return self::puente('abrir-terminal', [$rutaWindows]);
    }

    public static function abrirCursor(string $rutaWindows): bool
    {
        return self::puente('abrir-cursor', [$rutaWindows]);
    }

    /** Guarda el valor (ej. "Basic …") en la variable de entorno de usuario $nombre. */
    public static function guardarCredencial(string $nombre, string $valor): bool
    {
        return self::puente('guardar-credencial', [$nombre], ['ALMA_VALOR' => $valor]);
    }

    private static function puente(string $accion, array $args, array $env = []): bool
    {
        if (app()->environment('testing')) {
            self::$llamadas[] = ['accion' => $accion, 'args' => $args, 'env' => $env];

            return true;
        }
        if (! self::disponible()) {
            return false;
        }

        $cmd = array_merge(
            ['/mnt/c/Windows/System32/WindowsPowerShell/v1.0/powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', config('alma.puente'), $accion],
            $args
        );
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tubos);
        if (! is_resource($proc)) {
            return false;
        }
        // Los secretos van por la entrada estándar: ni en la línea de comandos ni en archivos.
        // (WSLENV no siempre propaga variables a PowerShell, por eso no se usa.)
        if (isset($env['ALMA_VALOR'])) {
            fwrite($tubos[0], $env['ALMA_VALOR'] . "\n");
        }
        fclose($tubos[0]);
        stream_get_contents($tubos[1]);
        stream_get_contents($tubos[2]);

        return proc_close($proc) === 0;
    }
}
