<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Los proyectos que ya tenían sitio reciben su conexión, con el nombre de servidor sugerido. */
return new class extends Migration
{
    public function up(): void
    {
        $proyectos = DB::table('proyectos')->whereNotNull('sitio_wp')->where('sitio_wp', '!=', '')
            ->whereNotIn('id', DB::table('conexiones')->select('proyecto_id'))->get();

        foreach ($proyectos as $p) {
            DB::table('conexiones')->insert([
                'proyecto_id' => $p->id,
                'nombre_mcp'  => 'elementor-' . Str::slug($p->nombre),
                'sitio_url'   => rtrim($p->sitio_wp, '/'),
                'estado'      => 'sin_comprobar',
                'created_at'  => $p->updated_at, 'updated_at' => $p->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Las conexiones creadas aquí se quitan con la tabla en la migración anterior.
    }
};
