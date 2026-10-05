<?php

namespace App\Support;

use App\Http\Controllers\FlujoController;
use App\Models\Pagina;
use App\Models\Proyecto;

/**
 * Arma la guía visual (colores, tipografías, espaciado, plan de secciones) y el prompt
 * que recibe el asistente para construir. Solo usa los tokens marcados como incluidos.
 */
class PromptGuia
{
    /** @return array{colores: \Illuminate\Support\Collection, tipografias: \Illuminate\Support\Collection, espaciados: \Illuminate\Support\Collection, otros: \Illuminate\Support\Collection, plan: array} */
    public static function guia(Proyecto $proyecto, ?Pagina $pagina = null): array
    {
        $tokens = $proyecto->tokens()->where('incluido', true)->orderBy('id')->get();

        $plan = $pagina && $pagina->secciones()->exists()
            ? $pagina->secciones()->orderBy('id')->get()->map(fn ($s) => [$s->nombre, $s->widget_plan ?? '—', $s->min_estimado])->all()
            : FlujoController::PLAN_ESTANDAR;

        return [
            'colores'     => $tokens->where('tipo', 'color')->filter(fn ($t) => self::esColor($t->valor))->values(),
            'tipografias' => $tokens->where('tipo', 'tipografia')->values(),
            'espaciados'  => $tokens->where('tipo', 'espaciado')->values(),
            'otros'       => $tokens->where('tipo', 'otro')->values(),
            'plan'        => $plan,
        ];
    }

    public static function prompt(Proyecto $proyecto, ?Pagina $pagina = null): string
    {
        $g = self::guia($proyecto, $pagina);
        $mcp = $proyecto->conexion?->nombre_mcp ?? PromptElementor::nombreSugerido($proyecto->nombre);
        $nombrePagina = $pagina?->nombre ?? '<página>';

        $lineas = [];
        $lineas[] = "Construye la página \"{$nombrePagina}\" del proyecto \"{$proyecto->nombre}\" en WordPress con Elementor, usando el servidor MCP `{$mcp}`.";
        $lineas[] = 'Sitio: ' . ($proyecto->sitio_wp ?: '[falta la URL del sitio]');
        $lineas[] = 'Diseño en Figma: ' . ($proyecto->archivo_figma ?: '[falta el archivo de Figma]');
        $lineas[] = '';
        $lineas[] = 'Sistema de diseño (crea estas variables globales antes de maquetar y no uses valores sueltos):';
        foreach ($g['colores'] as $t) {
            $lineas[] = "- Color {$t->nota}: {$t->valor}";
        }
        foreach ($g['tipografias'] as $t) {
            $lineas[] = "- Tipografía {$t->nota}: {$t->valor}";
        }
        if ($g['espaciados']->isNotEmpty()) {
            $lineas[] = '- Escala de espaciado: ' . $g['espaciados']->pluck('valor')->implode(' · ');
        }
        foreach ($g['otros'] as $t) {
            $lineas[] = "- {$t->nota}: {$t->valor}";
        }
        if ($g['colores']->isEmpty() && $g['tipografias']->isEmpty()) {
            $lineas[] = '- (aún no hay tokens: léelos del Figma y regístralos con `php artisan registro:figma` antes de empezar)';
        }
        $lineas[] = '';
        $lineas[] = 'Plan de secciones, una por vez y en este orden:';
        foreach ($g['plan'] as $i => [$nombre, $widget, $min]) {
            $lineas[] = ($i + 1) . ". {$nombre} — {$widget}" . ($min ? " (~{$min} min)" : '');
        }
        $lineas[] = '';
        $lineas[] = 'Reglas:';
        $lineas[] = '- Guarda todo como borrador; nada se publica sin aprobación en el panel.';
        $lineas[] = '- Usa contenedores y widgets nativos de Elementor; nada de HTML incrustado.';
        $lineas[] = '- Antes de empezar revisa las correcciones pendientes: `php artisan registro:cola`.';
        $lineas[] = "- Al terminar cada sección regístrala: `php artisan registro:add \"{$proyecto->nombre}\" \"{$nombrePagina}\" \"<sección>\" <min> --asistente=<min> --dev=<min>`.";

        return implode("\n", $lineas);
    }

    public static function esColor(string $valor): bool
    {
        return (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', trim($valor));
    }

    /** Nombre de fuente seguro para usarlo en un atributo style. */
    public static function fuente(string $valor): string
    {
        return trim(preg_replace('/[^\pL\pN\s-]/u', '', explode('(', $valor)[0]));
    }
}
