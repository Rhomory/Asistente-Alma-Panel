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
            'archivo_figma' => 'nullable|string|max:160',
            'sitio_wp'      => 'nullable|url|max:200',
        ], [
            'nombre.required' => 'El proyecto necesita un nombre.',
            'nombre.unique'   => 'Ya existe un proyecto con ese nombre.',
            'sitio_wp.url'    => 'El staging debe ser una URL completa (https://…).',
        ]);

        $proyecto = Proyecto::create($datos);

        return redirect()->route('proyecto', $proyecto)
            ->with('ok', "Proyecto \"{$proyecto->nombre}\" creado. Agrega sus páginas aquí abajo.");
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

    public function guardarToken(Request $request, Proyecto $proyecto)
    {
        $datos = $request->validate([
            'tipo'  => 'required|in:color,tipografia,espaciado,otro',
            'valor' => 'required|string|max:120',
            'nota'  => 'nullable|string|max:160',
        ], ['valor.required' => 'El token necesita un valor (ej.: #7A3E2E o "Poppins").']);

        $proyecto->tokens()->create($datos);

        return back()->with('ok', 'Token de diseño registrado.');
    }

    public function eliminarToken(Token $token)
    {
        $token->delete();

        return back()->with('ok', 'Token eliminado.');
    }
}
