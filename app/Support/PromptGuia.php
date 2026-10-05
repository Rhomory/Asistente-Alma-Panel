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
            ? $pagina->secciones()->orderBy('id')->get()->map(fn ($s) => [$s->nombre, $s->widget_plan ?? '—', $s->min_estimado, $s->figma_id])->all()
            : array_map(fn ($fila) => array_pad($fila, 4, null), FlujoController::PLAN_ESTANDAR);

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
        $lineas[] = 'Diseño en Figma: ' . ($proyecto->archivo_figma ?: '[falta el archivo de Figma]')
            . ($pagina?->figma_id ? " · marco de la página: {$pagina->figma_id}" : '');
        if ($pagina) {
            $lineas[] = 'URL de la página: ' . ($pagina->urlSitio() ?? '[falta la URL del sitio]');
        }
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
        $conIds = false;
        foreach ($g['plan'] as $i => [$nombre, $widget, $min, $figmaId]) {
            $conIds = $conIds || (bool) $figmaId;
            $lineas[] = ($i + 1) . ". {$nombre} — {$widget}" . ($min ? " (~{$min} min)" : '') . ($figmaId ? " · Figma {$figmaId}" : '');
        }
        if ($conIds) {
            $lineas[] = 'Para leer cada sección usa su ID guardado: `get_design_context` con ese nodeId y `detail: "full"`. No vuelvas a recorrer el archivo para buscarla.';
        }
        $lineas[] = '';
        $lineas[] = 'Reglas:';
        $lineas[] = "- Trabaja solo en el proyecto \"{$proyecto->nombre}\": usa su nombre en cada comando del panel e ignora los demás proyectos.";
        $lineas[] = '- Guarda todo como borrador; nada se publica sin aprobación en el panel.';
        $lineas[] = '- Usa contenedores y widgets nativos de Elementor; nada de HTML incrustado.';
        $lineas[] = "- Antes de empezar revisa las correcciones pendientes: `php artisan registro:cola \"{$proyecto->nombre}\"`.";
        $lineas[] = "- Al terminar cada sección regístrala: `php artisan registro:add \"{$proyecto->nombre}\" \"{$nombrePagina}\" \"<sección>\" <min> --asistente=<min> --dev=<min>`.";
        $lineas[] = '- Si trabajas desde Windows, los comandos del panel van por el puente: `powershell -NoProfile -File "$HOME\\.alma\\alma.ps1" <comando>` (en lugar de `php artisan`).';

        return implode("\n", $lineas);
    }

    /**
     * AGENTS.md para la carpeta del cliente: el formato abierto que leen los agentes de código
     * (Codex, Cursor y otros; en Claude Code se importa desde CLAUDE.md con `@AGENTS.md`).
     * Así el agente empieza sabiendo qué proyecto es, cómo hablar con el panel y el flujo.
     * El sistema de diseño no se copia aquí: se pide actualizado con guia:prompt.
     */
    public static function agentsMd(Proyecto $proyecto): string
    {
        $nombre = $proyecto->nombre;
        $mcp = $proyecto->conexion?->nombre_mcp ?? PromptElementor::nombreSugerido($nombre);
        $alma = 'powershell -NoProfile -File "$HOME\\.alma\\alma.ps1"';
        $paginas = $proyecto->paginas()->where('incluida', true)->orderBy('id')->pluck('nombre');
        $sitio = $proyecto->sitio_wp ?: "[falta la URL del sitio]";
        $figma = $proyecto->archivo_figma ?: "[falta el archivo de Figma]";
        $lista = $paginas->isEmpty() ? "aún ninguna (léelas del Figma)" : $paginas->implode(", ");
        $fecha = now("America/Lima")->format("d/m/Y");
        $urlPanel = rtrim(config("app.url"), "/") . "/proyectos/{$proyecto->id}";

        return <<<MD
        # {$nombre} — contexto para el agente

        Generado por el panel Asistente Alma el {$fecha}. Vuelve a descargarlo si cambian el sitio, el Figma o la conexión.
        Sirve para cualquier agente de código con MCP (Claude Code, Codex, OpenCode, Cursor u otros).

        ## Alcance: solo este proyecto
        Trabajas únicamente en **{$nombre}**. Usa siempre su nombre en los comandos del panel, no leas ni modifiques
        otros proyectos y no tomes en cuenta su estado, aunque aparezcan en el panel o en la cola.

        ## El proyecto
        - Sitio WordPress (staging): {$sitio}
        - Archivo de Figma: {$figma}
        - Servidor MCP de Elementor de este sitio: `{$mcp}`, registrado en la configuración MCP de tu agente. Si no lo ves entre tus herramientas, detente y avisa: no uses el servidor de otro proyecto.
        - Lectura del Figma: MCP figwright (requiere Figma de escritorio abierto con su plugin).
        - Páginas en alcance: {$lista}
        - Panel de supervisión: {$urlPanel} (lo mira el desarrollador mientras construyes).

        ## Cómo hablar con el panel
        El panel corre en WSL Ubuntu. Desde Windows sus comandos van por el puente (lo instala `scripts/instalar-windows.ps1` del panel):

        ```powershell
        {$alma} <comando> [argumentos]
        ```

        Si el puente falla, ejecuta `{$alma} diagnostico` y muestra el resultado antes de improvisar otra vía.
        Pasa siempre los JSON con `--archivo=<ruta>`, nunca en línea (las comillas entre PowerShell y WSL se rompen).

        | Para | Comando |
        |---|---|
        | Guía de una página: tokens, plan con IDs de Figma y reglas | `guia:prompt "{$nombre}" "<Página>"` |
        | Correcciones pendientes de este proyecto | `registro:cola "{$nombre}"` |
        | Cargar páginas, secciones (con su ID de Figma) y tokens | `registro:figma "{$nombre}" --archivo=<ruta.json>` |
        | Registrar una sección terminada | `registro:add "{$nombre}" "<Página>" "<Sección>" <min> --asistente=<min> --dev=<min>` |
        | Comprobar el sitio | `conexion:comprobar "{$nombre}"` |

        ## Flujo de trabajo
        1. Al empezar: `registro:cola "{$nombre}"`. Las correcciones pendientes van primero.
        2. Si el panel aún no tiene páginas, secciones o tokens de este proyecto, lee el Figma siguiendo `\$HOME\.claude\skills\alma-figma\SKILL.md` (en Claude Code se activa sola como skill; otros agentes deben leer ese archivo).
        3. Antes de construir una página, pide su guía con `guia:prompt`. Trae el ID de Figma de cada sección: es la memoria del diseño, úsala en vez de volver a recorrer el archivo.
        4. Construye **una sección a la vez** con el MCP `{$mcp}`, siempre en borrador. Al terminar cada una, regístrala con `registro:add`. El desarrollador la aprueba o pide corrección en el panel.
        5. Nunca publiques ni borres contenido del sitio. Nada se publica sin aprobación en el panel.

        ## Reglas
        - Lee el Figma en modo económico: `get_design_context` solo acepta marcos (no IDs de página) y para el inventario va con `detail: "minimal"`; `full` solo para la sección que construyes. Nunca `get_document` ni `get_node` para inventario.
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
