<?php

namespace App\Http\Controllers;

use App\Models\Conexion;
use App\Models\Proyecto;
use App\Support\EntornoWP;
use App\Support\PromptElementor;
use Illuminate\Http\Request;

/**
 * Conexiones por sitio: cada proyecto tiene su WordPress y su servidor MCP de Elementor.
 * La contraseña de aplicación vive solo en la configuración MCP de la consola;
 * aquí se guarda el prompt de Elementor ya saneado y lo que responde el sitio.
 */
class ConexionController extends Controller
{
    public function index()
    {
        $conexiones = Conexion::with('proyecto')->orderByDesc('updated_at')->orderByDesc('id')->get();

        // La destacada es la del último proyecto en el que se trabajó.
        $ultimoProyecto = Proyecto::has('conexiones')->orderByDesc('updated_at')->first();
        $actual = $ultimoProyecto?->conexion ?? $conexiones->first();

        return view('panel.conexion', [
            'conexiones'  => $conexiones,
            'actual'      => $actual,
            'ultimas'     => EntornoWP::ultimasVersiones(),
            'proyectos'   => Proyecto::orderBy('nombre')->get(),
            'sinConexion' => Proyecto::doesntHave('conexiones')->orderBy('nombre')->get(),
        ]);
    }

    public function guardar(Request $request)
    {
        $datos = self::validar($request);
        $conexion = Conexion::create($datos);
        $conexion->proyecto->touch();

        return redirect()->route('conexion')->with('ok', self::mensaje($conexion, $request));
    }

    public function eliminar(Conexion $conexion)
    {
        $nombre = $conexion->nombre_mcp;
        $conexion->delete();

        return back()->with('ok', "Conexión \"{$nombre}\" eliminada del panel. Quítala también de tu agente (en Claude Code: claude mcp remove {$nombre})");
    }

    public function comprobar(Request $request)
    {
        $conexiones = $request->filled('conexion_id')
            ? Conexion::whereKey($request->integer('conexion_id'))->get()
            : Conexion::all();
        EntornoWP::comprobarVarias($conexiones); // todas en paralelo

        $ok = $conexiones->where('estado', 'conectado')->count();

        return back()->with('ok', "Comprobación lista: {$ok} de {$conexiones->count()} sitios responden.");
    }

    /**
     * Valida los datos de una conexión. Si se pegó el prompt de Elementor, completa
     * sitio, endpoint, usuario y nombre desde él y lo guarda sin secretos.
     */
    public static function validar(Request $request, bool $desdeProyecto = false): array
    {
        $prompt = trim((string) $request->input('prompt_mcp'));
        $extraido = $prompt !== '' ? PromptElementor::extraer($prompt) : [];

        $request->merge(array_filter([
            'sitio_url'  => $request->input('sitio_url') ?: ($extraido['sitio_url'] ?? null),
            'nombre_mcp' => $request->input('nombre_mcp') ?: ($extraido['nombre_mcp'] ?? null),
            'usuario_wp' => $request->input('usuario_wp') ?: ($extraido['usuario_wp'] ?? null),
        ]));

        $reglas = [
            'nombre_mcp' => ['required', 'string', 'max:80', 'regex:/^[\w.-]+$/'],
            'sitio_url'  => 'required|url|max:200',
            'usuario_wp' => 'nullable|string|max:80',
            'prompt_mcp' => 'nullable|string|max:8000',
        ];
        if (! $desdeProyecto) {
            $reglas['proyecto_id'] = 'required|exists:proyectos,id';
        }

        $datos = $request->validate($reglas, [
            'nombre_mcp.required'  => 'Indica el nombre del servidor MCP (ej.: elementor-mi-proyecto).',
            'nombre_mcp.regex'     => 'El nombre del servidor solo admite letras, números, guiones y puntos.',
            'sitio_url.required'   => 'Falta la URL del sitio WordPress (o pega el prompt de Elementor).',
            'sitio_url.url'        => 'El sitio debe ser una URL completa (https://… o http://localhost:8080).',
            'proyecto_id.required' => 'Elige el proyecto al que pertenece esta conexión.',
        ]);

        unset($datos['prompt_mcp']);
        $datos['sitio_url'] = rtrim($datos['sitio_url'], '/');
        $datos['endpoint'] = $extraido['endpoint'] ?? null;
        $datos['prompt_saneado'] = $prompt !== '' ? PromptElementor::sanear($prompt)['texto'] : null;

        return $datos;
    }

    public static function mensaje(Conexion $c, Request $request): string
    {
        $ocultos = $request->filled('prompt_mcp') ? PromptElementor::sanear($request->input('prompt_mcp'))['ocultos'] : 0;

        return "Conexión \"{$c->nombre_mcp}\" guardada para {$c->proyecto->nombre}."
            . ($ocultos ? " Se ocultaron {$ocultos} datos sensibles del prompt; la contraseña no se guarda en el panel." : '');
    }
}
