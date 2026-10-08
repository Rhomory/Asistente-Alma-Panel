<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use App\Models\Seccion;
use App\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestión del catálogo (proyectos y páginas) desde la interfaz.
 * Las SECCIONES siguen entrando solo por consola (registro:add / registro:import):
 * el panel administra el catálogo, la consola escribe la medición.
 */
class GestionController extends Controller
{
    public function crear()
    {
        return view('panel.proyecto_nuevo');
    }

    public function guardar(Request $request)
    {
        $datos = $request->validate([
            'nombre'        => 'required|string|max:120|unique:proyectos,nombre',
            'cliente'       => 'nullable|string|max:160',
            'archivo_figma' => ['nullable', 'string', 'max:255', function ($attr, $valor, $falla) {
                if (str_starts_with($valor, 'http') && ! preg_match('#^https://(www\.)?figma\.com/#', $valor)) {
                    $falla('Si pegas un enlace, debe ser de figma.com (https://www.figma.com/…).');
                }
            }],
            'sitio_wp'      => 'nullable|url|max:200',
        ], [
            'nombre.required' => 'El proyecto necesita un nombre.',
            'nombre.unique'   => 'Ya existe un proyecto con ese nombre.',
            'sitio_wp.url'    => 'El sitio debe ser una URL completa (https://… o http://localhost/…).',
        ]);

        // Conexión opcional en el mismo formulario: se valida antes de crear nada.
        $conexion = null;
        if ($request->filled('prompt_mcp') || ($request->filled('nombre_mcp') && ! empty($datos['sitio_wp']))) {
            $request->merge(['sitio_url' => $request->input('sitio_url') ?: ($datos['sitio_wp'] ?? null)]);
            $conexion = ConexionController::validar($request, true);
            $datos['sitio_wp'] = ($datos['sitio_wp'] ?? null) ?: $conexion['sitio_url'];
        }

        $proyecto = Proyecto::create($datos);
        $mensaje = "Proyecto \"{$proyecto->nombre}\" creado.";
        if ($conexion) {
            $c = $proyecto->conexiones()->create($conexion);
            $mensaje .= ' ' . ConexionController::mensaje($c, $request) . ConexionController::entregarCredencial($c, $request);
        }
        $mensaje .= ConexionController::actualizarCarpeta($proyecto);

        return redirect()->route('proyecto', $proyecto)
            ->with('ok', $mensaje . ' El asistente cargará sus páginas al leer el Figma.');
    }

    /** Lo que se perdería al eliminar el proyecto. Un proyecto sin páginas ni tokens está "en blanco". */
    public static function conteoEliminar(Proyecto $proyecto): array
    {
        $secciones = Seccion::whereIn('pagina_id', $proyecto->paginas()->select('id'));

        $c = [
            'paginas'    => $proyecto->paginas()->count(),
            'secciones'  => (clone $secciones)->count(),
            'tokens'     => $proyecto->tokens()->count(),
            'conexiones' => $proyecto->conexiones()->count(),
            'eventos'    => Evento::whereIn('seccion_id', (clone $secciones)->select('id'))->count(),
        ];
        $c['en_blanco'] = $c['paginas'] === 0 && $c['tokens'] === 0;

        return $c;
    }

    /**
     * Elimina el proyecto. En blanco: basta la confirmación del navegador.
     * Con registros: hay que escribir el nombre exacto del proyecto; se borra todo lo relacionado.
     */
    public function eliminar(Request $request, Proyecto $proyecto)
    {
        $conteo = self::conteoEliminar($proyecto);

        if (! $conteo['en_blanco'] && trim((string) $request->input('confirmacion')) !== $proyecto->nombre) {
            return back()->withErrors(['confirmacion' => 'El nombre no coincide. Escríbelo exactamente como aparece para eliminar el proyecto.']);
        }

        $mcp = $proyecto->conexion?->nombre_mcp;

        DB::transaction(function () use ($proyecto) {
            // Los eventos solo quedarían huérfanos (seccion_id → null); se borran con el proyecto.
            Evento::whereIn('seccion_id', Seccion::whereIn('pagina_id', $proyecto->paginas()->select('id'))->select('id'))->delete();
            $proyecto->delete(); // páginas, secciones, tokens y conexiones caen en cascada
        });

        Evento::create(['tipo' => 'proyecto_eliminado', 'detalle' => $conteo['en_blanco']
            ? "Proyecto \"{$proyecto->nombre}\" eliminado (estaba en blanco)"
            : "Proyecto \"{$proyecto->nombre}\" eliminado con {$conteo['paginas']} páginas, {$conteo['secciones']} secciones y {$conteo['tokens']} tokens"]);

        return redirect()->route('dashboard')->with('ok', "Proyecto \"{$proyecto->nombre}\" eliminado"
            . ($conteo['en_blanco'] ? '.' : ' junto con todos sus registros.')
            . ($mcp ? " Quita también su servidor MCP de tu agente (en Claude Code: claude mcp remove {$mcp})" : ''));
    }

    public function guardarPagina(Request $request, Proyecto $proyecto)
    {
        $datos = $request->validate([
            'nombre'          => 'required|string|max:120',
            'secciones_total' => 'nullable|integer|min:0|max:50',
        ], [
            'nombre.required' => 'La página necesita un nombre.',
        ]);

        $proyecto->paginas()->create([
            'nombre'          => $datos['nombre'],
            'secciones_total' => $datos['secciones_total'] ?? 0,
            'estado'          => 'pendiente',
        ]);

        return redirect()->route('proyecto', $proyecto)
            ->with('ok', "Página \"{$datos['nombre']}\" agregada como pendiente.");
    }

    /** Marca o desmarca una página para maquetar (check del catálogo). */
    public function incluirPagina(Request $request, Pagina $pagina)
    {
        $pagina->update(['incluida' => $request->boolean('incluida')]);

        return back()->with('ok', $pagina->incluida
            ? "\"{$pagina->nombre}\" entra al alcance: se maquetará."
            : "\"{$pagina->nombre}\" marcada como no maquetar (queda fuera del alcance).");
    }

    public function guardarToken(Request $request, Proyecto $proyecto)
    {
        $datos = $request->validate([
            'tipo'  => 'required|in:color,tipografia,espaciado,otro',
            'valor' => 'required|string|max:120',
            'nota'  => 'nullable|string|max:160',
        ], ['valor.required' => 'El token necesita un valor (ej.: #7A3E2E o "Poppins").']);

        $proyecto->tokens()->create($datos + ['origen' => 'manual']);
        \App\Models\Evento::create(['tipo' => 'token', 'detalle' => "Token {$datos['tipo']} \"{$datos['valor']}\" registrado manualmente en {$proyecto->nombre}"]);

        return back()->with('ok', 'Token de diseño registrado.');
    }

    /** El check decide si el token se aplica como estilo global. */
    public function incluirToken(Request $request, Token $token)
    {
        $token->update(['incluido' => $request->boolean('incluido')]);

        return back()->with('ok', $token->incluido
            ? "Token \"{$token->valor}\" activado: se aplicará como estilo global."
            : "Token \"{$token->valor}\" desactivado: no se usará en la construcción.");
    }

    /** Solo tipografías, espaciados y otros son editables; los colores se reemplazan (agregar + eliminar). */
    public function actualizarToken(Request $request, Token $token)
    {
        abort_if($token->tipo === 'color', 422, 'Los colores no se editan: elimina el token y registra el color correcto.');

        $datos = $request->validate([
            'valor' => 'required|string|max:120',
            'nota'  => 'nullable|string|max:160',
        ], ['valor.required' => 'El token necesita un valor.']);

        $token->update($datos);

        return back()->with('ok', 'Token actualizado.');
    }

    public function eliminarToken(Token $token)
    {
        $token->delete();

        return back()->with('ok', 'Token eliminado.');
    }
}
