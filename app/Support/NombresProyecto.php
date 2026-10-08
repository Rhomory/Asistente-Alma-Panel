<?php

namespace App\Support;

use App\Models\Proyecto;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Regla de nombres: cada proyecto tiene un nombre único y no se crean variantes de uno existente.
 *
 *  - "Cota", "cota" y "Cotá" son el mismo proyecto (no distingue mayúsculas, tildes ni signos).
 *  - "Cota v2", "cotav1", "Cota copia" o "Cota final" son variantes de "Cota": se bloquean salvo que
 *    se fuercen, porque comparten servidor MCP (elementor-cota…), credencial y carpeta parecida, y el
 *    agente puede terminar trabajando en el proyecto equivocado. Un cambio de diseño va como Design\vN.
 */
class NombresProyecto
{
    /** Sufijos que convierten un nombre en "otra versión" del mismo proyecto. */
    private const SUFIJOS = 'v\s?\d+|version\s?\d+|ver\s?\d+|\d+|copia|copy|final|nuevo|nueva|new|bis|alt|test|prueba|demo|backup|respaldo|old|antiguo|borrador|draft';

    /** Forma comparable: minúsculas, sin tildes ni signos, espacios simples. */
    public static function clave(string $nombre): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($nombre))));
    }

    /** El nombre sin sufijos de versión y sin espacios ("Cota v2" → "cota", "Clínica Sur copia" → "clinicasur"). */
    public static function base(string $nombre): string
    {
        $clave = self::clave($nombre);
        $base = $clave;
        do {
            $antes = $base;
            $base = trim(preg_replace('/(?:^|\s)(?:' . self::SUFIJOS . ')$/', '', $base));
            $base = preg_replace('/(?<=[a-z])v?\d+$/', '', $base); // "cotav1", "cota2"
        } while ($base !== $antes && $base !== '');

        return str_replace(' ', '', $base !== '' ? $base : $clave);
    }

    /** El proyecto con ese mismo nombre (sin distinguir mayúsculas ni tildes), si existe. */
    public static function buscar(string $nombre, ?int $excepto = null): ?Proyecto
    {
        $clave = self::clave($nombre);

        return Proyecto::query()->when($excepto, fn ($q) => $q->whereKeyNot($excepto))->get(['id', 'nombre'])
            ->first(fn ($p) => self::clave($p->nombre) === $clave)
            ?->fresh();
    }

    /** Proyectos de los que $nombre sería una variante (misma base, distinto nombre). */
    public static function variantesDe(string $nombre, ?int $excepto = null): array
    {
        $clave = self::clave($nombre);
        $base = self::base($nombre);

        return Proyecto::query()->when($excepto, fn ($q) => $q->whereKeyNot($excepto))->orderBy('nombre')->pluck('nombre')
            ->filter(fn ($n) => self::clave($n) !== $clave && self::base($n) === $base)
            ->values()->all();
    }

    public static function riesgo(): string
    {
        return 'Una variante comparte servidor MCP, credencial y carpeta parecidos con el original: el agente puede leer '
            . 'o construir en el proyecto equivocado y el avance queda repartido en dos registros. '
            . 'Si el Figma cambió, no crees otro proyecto: vuelve a leerlo y queda como una versión nueva (Design\\v2, v3…).';
    }

    /**
     * Para los comandos de consola: devuelve el proyecto existente o lo crea, salvo que el nombre sea una
     * variante de otro (entonces avisa y devuelve null, a menos que venga --forzar-variante).
     */
    public static function paraConsola(Command $cmd, string $nombre): ?Proyecto
    {
        $nombre = trim($nombre);
        if ($existente = self::buscar($nombre)) {
            return $existente;
        }

        $similares = self::variantesDe($nombre);
        if ($similares && ! ($cmd->hasOption('forzar-variante') && $cmd->option('forzar-variante'))) {
            $cmd->error("\"{$nombre}\" parece una variante de: " . implode(', ', $similares) . '. No se creó ningún proyecto.');
            $cmd->line(self::riesgo());
            $cmd->line('Si es un proyecto distinto de verdad, confírmalo con el usuario y repite el comando con --forzar-variante.');

            return null;
        }

        return Proyecto::create(['nombre' => $nombre]);
    }
}
