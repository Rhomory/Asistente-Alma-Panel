<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memoria del diseño: el ID del marco de Figma de cada página y de cada sección, para que el
 * asistente construya yendo directo al nodo sin volver a recorrer el archivo.
 * Y la URL de cada página del sitio (sitio.com/nosotros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $t) {
            $t->string('figma_id', 40)->nullable();
            $t->string('url', 255)->nullable();
        });
        Schema::table('secciones', fn (Blueprint $t) => $t->string('figma_id', 40)->nullable());
    }

    public function down(): void
    {
        Schema::table('paginas', fn (Blueprint $t) => $t->dropColumn(['figma_id', 'url']));
        Schema::table('secciones', fn (Blueprint $t) => $t->dropColumn('figma_id'));
    }
};
