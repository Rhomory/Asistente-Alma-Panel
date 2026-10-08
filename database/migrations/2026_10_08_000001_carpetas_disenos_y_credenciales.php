<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carpeta de cada proyecto en Windows, versiones del diseño (Design/v1, v2…) y la marca de que la
 * credencial del sitio ya está guardada en el equipo (nunca el secreto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', fn (Blueprint $t) => $t->string('carpeta', 160)->nullable());
        Schema::table('conexiones', fn (Blueprint $t) => $t->timestamp('credencial_en_equipo')->nullable());

        Schema::create('disenos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('estado', 12)->default('nueva'); // nueva | activa | anterior
            $t->longText('datos');                      // la lectura del Figma (JSON)
            $t->json('resumen')->nullable();            // conteos de páginas, secciones y tokens
            $t->timestamp('adoptada_en')->nullable();
            $t->timestamps();
            $t->unique(['proyecto_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disenos');
        Schema::table('conexiones', fn (Blueprint $t) => $t->dropColumn('credencial_en_equipo'));
        Schema::table('proyectos', fn (Blueprint $t) => $t->dropColumn('carpeta'));
    }
};
