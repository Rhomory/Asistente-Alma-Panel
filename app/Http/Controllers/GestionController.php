<?php

namespace App\Http\Controllers;

use App\Models\Pagina;
use App\Models\Proyecto;
use App\Models\Token;
use Illuminate\Http\Request;

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
            $mensaje .= ' ' . ConexionController::mensaje($c, $request);
        }

        return redirect()->route('proyecto', $proyecto)
            ->with('ok', $mensaje . ' El asistente cargará sus páginas al leer el Figma.');
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
