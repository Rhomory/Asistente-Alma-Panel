<?php

namespace App\Http\Controllers;

use App\Models\Pagina;
use App\Models\Proyecto;
use App\Support\PromptGuia;
use Illuminate\Http\Request;

/** Guía visual del proyecto (colores, tipografías, plan) y el prompt que recibe el asistente. */
class GuiaController extends Controller
{
    public function show(Request $request, Proyecto $proyecto)
    {
        $proyecto->load('paginas', 'conexion');
        $pagina = $request->filled('pagina')
            ? Pagina::where('proyecto_id', $proyecto->id)->find($request->integer('pagina'))
            : null;

        return view('panel.guia', [
            'proyecto' => $proyecto,
            'pagina'   => $pagina,
            'guia'     => PromptGuia::guia($proyecto, $pagina),
            'prompt'   => PromptGuia::prompt($proyecto, $pagina),
        ]);
    }
}
