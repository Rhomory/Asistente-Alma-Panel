<?php

namespace App\Support;

use App\Models\Proyecto;

/**
 * Roles para trabajar con varios agentes a la vez en la carpeta del proyecto:
 *  - alma-conexion:      comprueba figwright, el servidor de Elementor y el puente. Solo lee.
 *  - alma-figma-lector:  lee el Figma y va dejando el detalle de cada sección en Design\vN\secciones\
 *                        para que el constructor (el agente principal) no espere a Figma.
 * Se escriben en el formato de cada agente (verificado en su documentación):
 *  - Claude Code: .claude/agents/<rol>.md (subagentes; heredan los MCP del proyecto).
 *  - OpenCode:    .opencode/agents/<rol>.md con mode: subagent.
 *  - Codex y Cursor no tienen subagentes por proyecto: usan .alma/roles/<rol>.md en una segunda terminal.
 * Los textos van en nowdoc y con marcadores, así las rutas de Windows ("Design\vN") no se vuelven escapes.
 */
class RolesAgentes
{
    /** @return array<string, string> ruta relativa => contenido */
    public static function archivos(Proyecto $p): array
    {
        $roles = [
            'alma-conexion' => [
                'Comprueba que figwright, el servidor de Elementor del proyecto y el puente al panel respondan. Úsalo al empezar una sesión o cuando algo falle. Solo lee, no cambia nada.',
                self::conexion($p),
            ],
            'alma-figma-lector' => [
                'Lee el Figma del proyecto y deja el detalle de cada sección en Design\vN\secciones para que el constructor no espere. Úsalo en paralelo mientras se construye una página.',
                self::lector($p),
            ],
        ];

        $archivos = [];
        foreach ($roles as $rol => [$descripcion, $cuerpo]) {
            $desc = str_replace('"', "'", $descripcion);
            $archivos[".claude/agents/{$rol}.md"] = "---\nname: {$rol}\ndescription: \"{$desc}\"\n---\n\n{$cuerpo}";
            $archivos[".opencode/agents/{$rol}.md"] = "---\ndescription: \"{$desc}\"\nmode: subagent\n---\n\n{$cuerpo}";
            $archivos[".alma/roles/{$rol}.md"] = "# Rol: {$rol}\n\n{$descripcion}\n\n{$cuerpo}";
        }

        return $archivos;
    }

    private static function datos(Proyecto $p): array
    {
        return [
            '{PROYECTO}' => $p->nombre,
            '{MCP}'      => $p->conexion?->nombre_mcp ?? PromptElementor::nombreSugerido($p->nombre),
            '{VAR}'      => PromptElementor::variableAuth($p->nombre),
            '{ALMA}'     => 'powershell -NoProfile -File "$HOME\.alma\alma.ps1"',
            '{FIGMA}'    => $p->archivo_figma ?: 'el archivo de Figma del proyecto',
            '{VERSION}'  => (string) config('alma.figwright', '0.6.0'),
        ];
    }

    private static function conexion(Proyecto $p): string
    {
        return strtr(<<<'MD'
        Trabajas solo en el proyecto **{PROYECTO}**. Tu tarea es comprobar la conexión y devolver un resumen corto. No construyas, no registres secciones y no cambies archivos.

        1. **figwright:** llama a `ping`.
           - Si `plugin` es `null`: pide abrir Figma de escritorio con el plugin figwright en {FIGMA}.
           - Si aparece `versionSkew` o `buildSkew`: el plugin y el servidor no coinciden. El panel fija el servidor en {VERSION}; pide actualizar el plugin a esa versión.
           - Si hay varios archivos abiertos, `list_files` y `use_file` con el del proyecto.
        2. **{MCP}:** usa una herramienta de solo lectura (listar páginas o el kit del sitio). Si falla la autenticación, falta la variable de Windows `{VAR}`: hay que pegar el prompt de Elementor en el panel (Conexión) y reabrir el agente con "Abrir terminal".
        3. **Puente al panel:** `{ALMA} registro:cola "{PROYECTO}"`. Si falla, ejecuta `{ALMA} diagnostico` y copia lo que salga en rojo.

        Responde con una tabla: figwright, {MCP} y panel, cada uno con OK o el problema y la acción concreta.
        MD, self::datos($p));
    }

    private static function lector(Proyecto $p): string
    {
        return strtr(<<<'MD'
        Trabajas solo en el proyecto **{PROYECTO}**. Lees el Figma y preparas la información; **no usas {MCP} ni registras secciones**: eso lo hace el constructor.

        ## Si no hay diseño en uso (o te piden releer)
        Sigue la skill `alma-figma` (en Claude Code se activa sola; en otros agentes lee `$HOME\.agents\skills\alma-figma\SKILL.md`): inventario en modo económico, guardado con `diseno:guardar` y capturas en `Design\vN\capturas`.

        ## Preparar secciones para construir
        1. Pide la guía de la página: `{ALMA} guia:prompt "{PROYECTO}" "<Página>"`. Trae la versión en uso (vN) y el plan con el ID de Figma de cada sección.
        2. Recorre las secciones **en el orden del plan**, una a la vez:
           - `get_design_context` con su `nodeId` y `detail: "full"`.
           - Guarda el resultado en `Design\vN\secciones\<id>.json` (en el nombre, cambia `:` por `-`; ej. `12:4` → `12-4.json`) con este formato:
             `{ "pagina": "...", "seccion": "...", "figma_id": "12:4", "contexto": <respuesta de get_design_context> }`
           - Agrega una línea a `Design\vN\secciones\_listas.txt`: `12:4 · <Página> › <Sección>`.
        3. Así el constructor empieza con la primera sección mientras tú preparas las siguientes. Si una sección ya tiene su archivo, sáltala.

        ## Reglas
        - Nada de `get_document`, `get_node` ni capturas sueltas fuera de `Design\vN\capturas`.
        - Medidas, colores y textos salen siempre de `get_design_context`, nunca de una imagen.
        - Si el Figma cambió respecto a la versión en uso, avisa: no mezcles versiones en la misma carpeta.
        - Al terminar, resume cuántas secciones quedaron listas y si hubo alguna sin ID o con error.
        MD, self::datos($p));
    }
}
