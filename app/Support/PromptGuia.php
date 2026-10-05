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
        $lineas[] = '- Si trabajas desde Windows, los comandos del panel van por el puente: `powershell -NoProfile -File "$HOME\\.claude\\skills\\alma-figma\\alma.ps1" <comando>` (en lugar de `php artisan`).';

        return implode("\n", $lineas);
    }

    /**
     * CLAUDE.md para la carpeta del cliente: Claude Code lo carga al abrir esa carpeta,
     * así el asistente empieza sabiendo qué proyecto es, cómo hablar con el panel y el flujo.
     * El sistema de diseño no se copia aquí: se pide actualizado con guia:prompt.
     */
    public static function claudeMd(Proyecto $proyecto): string
    {
        $nombre = $proyecto->nombre;
        $mcp = $proyecto->conexion?->nombre_mcp ?? PromptElementor::nombreSugerido($nombre);
        $alma = 'powershell -NoProfile -File "$HOME\\.claude\\skills\\alma-figma\\alma.ps1"';
        $paginas = $proyecto->paginas()->where('incluida', true)->orderBy('id')->pluck('nombre');
        $sitio = $proyecto->sitio_wp ?: "[falta la URL del sitio]";
        $figma = $proyecto->archivo_figma ?: "[falta el archivo de Figma]";
        $lista = $paginas->isEmpty() ? "aún ninguna (léelas del Figma)" : $paginas->implode(", ");
        $fecha = now("America/Lima")->format("d/m/Y");
        $urlPanel = rtrim(config("app.url"), "/") . "/proyectos/{$proyecto->id}";

        return <<<MD
        # {$nombre} — contexto para el asistente

        Generado por el panel Asistente Alma el {$fecha}. Vuelve a descargarlo si cambian el sitio, el Figma o la conexión.

        ## El proyecto
        - Sitio WordPress (staging): {$sitio}
        - Archivo de Figma: {$figma}
        - Servidor MCP de Elementor de este sitio: `{$mcp}` (registrado solo en esta carpeta; compruébalo con `claude mcp list`).
        - Páginas en alcance: {$lista}
        - Panel de supervisión: {$urlPanel} (lo mira el desarrollador mientras construyes).

        ## Cómo hablar con el panel
        El panel corre en WSL Ubuntu. Desde esta carpeta (Windows) sus comandos van por el puente:

        ```powershell
        {$alma} <comando> [argumentos]
        ```

        | Para | Comando |
        |---|---|
        | Guía actualizada (tokens, plan, reglas) de una página | `guia:prompt "{$nombre}" "<Página>"` |
        | Correcciones pendientes | `registro:cola` |
        | Cargar páginas y tokens leídos del Figma | `registro:figma "{$nombre}" --archivo=<ruta.json>` (skill `alma-figma`) |
        | Registrar una sección terminada | `registro:add "{$nombre}" "<Página>" "<Sección>" <min> --asistente=<min> --dev=<min>` |
        | Comprobar el sitio | `conexion:comprobar "{$nombre}"` |

        ## Flujo de trabajo
        1. Al empezar: revisa `registro:cola`. Las correcciones pendientes van primero.
        2. Si el proyecto aún no tiene páginas o tokens en el panel, lee el Figma con la skill `alma-figma`.
        3. Antes de construir una página, pide su guía con `guia:prompt` y síguela al pie de la letra.
        4. Construye **una sección a la vez** con el MCP `{$mcp}`, siempre en borrador. Al terminar cada una, regístrala con `registro:add`. El desarrollador la aprueba o pide corrección en el panel.
        5. Nunca publiques ni borres contenido del sitio. Nada se publica sin aprobación en el panel.

        ## Reglas
        - Lee el Figma con figwright en modo económico: inventario con `detail: "minimal"`, `full` solo para la sección que estás construyendo; nunca `get_node` ni `get_document` para inventario.
        - Usa variables globales de Elementor para colores y tipografías; nada de valores sueltos ni HTML incrustado.
        - No escribas contraseñas, tokens ni la contraseña de aplicación en archivos, mensajes o registros.
        MD;
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
