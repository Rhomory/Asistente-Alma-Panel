<?php

namespace App\Console\Commands\Concerns;

/** Lee el JSON de una lectura de Figma desde --json, --archivo o --stdin. */
trait LeeJson
{
    protected function leerJson(): ?array
    {
        $crudo = match (true) {
            (bool) $this->option('json')    => $this->option('json'),
            (bool) $this->option('archivo') => @file_get_contents($this->option('archivo')),
            (bool) $this->option('stdin')   => stream_get_contents(STDIN),
            default                         => null,
        };
        $datos = $crudo ? json_decode($crudo, true) : null;

        return is_array($datos) ? $datos : null;
    }
}
