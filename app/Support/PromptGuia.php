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
        if ($activa = $proyecto->disenoActivo()->first()) {
            $carpeta = CarpetaProyecto::rutaWindows($proyecto);
            $lineas[] = "Diseño en uso: v{$activa->version}" . ($carpeta ? " · capturas de referencia en {$carpeta}\\Design\\v{$activa->version}\\capturas" : '');
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
        $urlPanel = rtrim(config("app.url"), "/") . "/proyectos/{$proyecto->slug}";

        $varAuth = PromptElementor::variableAuth($nombre);
        $activa = $proyecto->disenoActivo()->first();
        $disenoActivo = $activa ? "Design\\v{$activa->version} (capturas de referencia en Design\\v{$activa->version}\\capturas)" : 'aún ninguno: lee el Figma (paso 2)';

        return <<<MD
        # {$nombre} — contexto para el agente

        Generado por el panel Asistente Alma el {$fecha}. El panel lo reescribe solo cuando cambian el sitio, la conexión o el diseño.
        Sirve para Claude Code, Codex, Cursor y OpenCode.

        ## Alcance: solo este proyecto
        Trabajas únicamente en **{$nombre}**. Usa siempre su nombre en los comandos del panel, no leas ni modifiques
        otros proyectos y no tomes en cuenta su estado, aunque aparezcan en el panel o en la cola.
        Escribe el nombre tal cual: "{$nombre}". **No crees otro proyecto ni una variante** ("{$nombre} v2", "{$nombre} copia"):
        si el Figma cambió, se guarda como versión nueva con `diseno:guardar`. Si un comando rechaza un nombre por parecido,
        no uses `--forzar-variante` por tu cuenta: explica al desarrollador que una variante comparte MCP y credencial
        parecidos (puedes terminar trabajando en el proyecto equivocado) y fuérzalo solo si te lo confirma.

        ## Esta carpeta
        - `AGENTS.md` (este archivo) y `CLAUDE.md` (lo importa para Claude Code).
        - Configuración MCP ya lista para cada agente: `.mcp.json` (Claude Code), `.codex/config.toml` (Codex),
          `.cursor/mcp.json` (Cursor) y `opencode.json` (OpenCode). Incluye figwright y `{$mcp}`.
        - `Design\\vN\`: cada lectura del Figma (`lectura.json` + `capturas\`). Diseño en uso: {$disenoActivo}.

        ## El proyecto
        - Sitio WordPress (staging): {$sitio}
        - Archivo de Figma: {$figma}
        - Servidor MCP de Elementor: `{$mcp}`. Su credencial está en la variable de Windows `{$varAuth}`; tú no la manejas.
        - Páginas en alcance: {$lista}
        - Panel de supervisión: {$urlPanel}

        ## Cómo hablar con el panel
        Desde Windows, los comandos del panel van por el puente:

        ```powershell
        {$alma} <comando> [argumentos]
        ```

        Si falla, ejecuta `{$alma} diagnostico` y muestra el resultado antes de improvisar otra vía.
        Pasa siempre los JSON con `--archivo=<ruta>`, nunca en línea.

        | Para | Comando |
        |---|---|
        | Guardar la lectura del Figma como nueva versión (Design\\vN) | `diseno:guardar "{$nombre}" --archivo=<ruta.json>` |
        | Guía de una página: tokens, plan con IDs de Figma y reglas | `guia:prompt "{$nombre}" "<Página>"` |
        | Correcciones pendientes de este proyecto | `registro:cola "{$nombre}"` |
        | Registrar una sección terminada | `registro:add "{$nombre}" "<Página>" "<Sección>" <min> --asistente=<min> --dev=<min>` |
        | Comprobar el sitio | `conexion:comprobar "{$nombre}"` |

        ## Flujo de trabajo
        0. **Conexión primero.** Comprueba que tienes las herramientas de figwright (`ping`) y de `{$mcp}`. Si falta alguna:
           - Claude Code: aprueba los servidores de `.mcp.json` cuando lo pida (o `/mcp`).
           - Codex: la carpeta debe estar marcada como de confianza para leer `.codex/config.toml`.
           - Cursor: activa los servidores en Settings › MCP.
           - Si `{$mcp}` no autentica, la variable `{$varAuth}` no está: pide al desarrollador que pegue el prompt de Elementor en el panel (Conexión) y reinicie el agente desde el botón "Abrir terminal" del proyecto.
           Si tuviste que cambiar algo, pide reiniciar el agente antes de seguir.
        1. `registro:cola "{$nombre}"`: las correcciones pendientes van primero.
        2. Si no hay diseño en uso, lee el Figma siguiendo `\$HOME\.claude\skills\alma-figma\SKILL.md` (en Claude Code se activa sola; los demás agentes deben leer ese archivo). Guarda la lectura con `diseno:guardar`, que crea `Design\\vN`, y exporta ahí las capturas de cada página.
        3. Si el Figma cambió, vuelve a leerlo y guárdalo con `diseno:guardar`: queda como versión nueva hasta que el desarrollador pulse "Usar esta versión" en el panel. Trabaja siempre con la versión en uso.
        4. Antes de construir una página, pide su guía con `guia:prompt`: trae el ID de Figma de cada sección (úsalo en vez de recorrer el archivo) y las capturas sirven de referencia visual.
        5. Construye **una sección a la vez** con `{$mcp}`, siempre en borrador, y regístrala con `registro:add`. El desarrollador la aprueba o pide corrección en el panel.
        6. Nunca publiques ni borres contenido del sitio. Nada se publica sin aprobación en el panel.

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
