<?php

namespace App\Http\Controllers;

use App\Models\Pagina;
use App\Models\Proyecto;
use App\Support\PromptGuia;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Guía visual del proyecto (colores, tipografías, plan), el prompt del asistente y su AGENTS.md. */
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

    /** Descarga el AGENTS.md para la carpeta del cliente (sirve para cualquier agente con MCP). */
    public function agentsMd(Proyecto $proyecto)
    {
        return response(PromptGuia::agentsMd($proyecto->load('conexion')), 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="AGENTS.md"',
        ]);
    }
}
