<?php

namespace App\Console\Commands\Concerns;

/**
 * Lee el JSON de una lectura de Figma desde --json, --archivo o --stdin. Acepta archivos con BOM o en UTF-16
 * (lo que escriben Set-Content/Out-File de PowerShell 5.1) y deja en $errorJson un motivo concreto.
 */
trait LeeJson
{
    protected ?string $errorJson = null;

    protected function leerJson(): ?array
    {
        $this->errorJson = null;
        if ($ruta = $this->option('archivo')) {
            if (! is_file($ruta)) {
                $this->errorJson = "No encuentro el archivo {$ruta}. Escríbelo primero (por ejemplo Design\\lectura-borrador.json en la carpeta del proyecto) y pasa esa ruta.";

                return null;
            }
            $crudo = (string) file_get_contents($ruta);
        } else {
            $crudo = match (true) {
                (bool) $this->option('json')  => (string) $this->option('json'),
                (bool) $this->option('stdin') => (string) stream_get_contents(STDIN),
                default                       => '',
            };
        }
        if (trim($crudo) === '') {
            $this->errorJson = 'Falta el JSON: usa --archivo=<ruta.json> (recomendado), --json o --stdin.';

            return null;
        }

        $datos = json_decode(self::aUtf8($crudo), true);
        if (! is_array($datos)) {
            $this->errorJson = 'El JSON no es válido (' . json_last_error_msg() . '). Revisa comas, comillas y llaves.';

            return null;
        }

        return $datos;
    }

    private static function aUtf8(string $s): string
    {
        return match (true) {
            str_starts_with($s, "\xFF\xFE")     => mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16LE'),
            str_starts_with($s, "\xFE\xFF")     => mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE'),
            str_starts_with($s, "\xEF\xBB\xBF") => substr($s, 3),
            strlen($s) > 1 && $s[1] === "\0"    => mb_convert_encoding($s, 'UTF-8', 'UTF-16LE'),   // UTF-16LE sin BOM
            default                             => $s,
        };
    }
}
