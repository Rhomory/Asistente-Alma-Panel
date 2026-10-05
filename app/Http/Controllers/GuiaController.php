<?php

namespace App\Http\Controllers;

use App\Models\Pagina;
use App\Models\Proyecto;
use App\Support\PromptGuia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Guía visual del proyecto (colores, tipografías, plan), el prompt del asistente y su CLAUDE.md. */
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

    /** Descarga el CLAUDE.md para la carpeta del cliente. */
    public function claudeMd(Proyecto $proyecto)
    {
        return response(PromptGuia::claudeMd($proyecto->load('conexion')), 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="CLAUDE.md"',
        ]);
    }
}
