<?php

use App\Models\Proyecto;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// URL legible del proyecto: /proyectos/sitio-bodega-andina en vez de /proyectos/1.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $t) {
            $t->string('slug', 140)->nullable()->unique()->after('nombre');
        });

        foreach (DB::table('proyectos')->orderBy('id')->get(['id', 'nombre']) as $p) {
            DB::table('proyectos')->where('id', $p->id)->update(['slug' => Proyecto::slugUnico($p->nombre, $p->id)]);
        }
    }

    public function down(): void
    {
        Schema::table('proyectos', function (Blueprint $t) {
            $t->dropUnique(['slug']);
            $t->dropColumn('slug');
        });
    }
};
