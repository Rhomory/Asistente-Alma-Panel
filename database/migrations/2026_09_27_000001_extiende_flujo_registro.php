<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa el flujo del panel (pantallas Plan y Construcción):
 * - Las secciones ganan ciclo de vida: planificada → construyendo → construida → aprobada,
 *   y un estimado en minutos (el plan de la guía técnica).
 * - Los eventos pueden quedar pendientes (cola de correcciones que la consola atiende).
 * - Tokens de diseño por proyecto (pantalla Diseño).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secciones', function (Blueprint $table) {
            $table->string('estado')->default('construida'); // planificada|construyendo|construida|aprobada
            $table->unsignedInteger('min_estimado')->nullable();
        });
        DB::statement("UPDATE secciones SET estado = CASE WHEN aprobada = 1 THEN 'aprobada' ELSE 'construida' END");

        Schema::table('eventos', function (Blueprint $table) {
            $table->boolean('resuelto')->default(true); // solicitudes de corrección nacen en false
        });

        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('tipo');   // color|tipografia|espaciado|otro
            $table->string('valor');  // ej. #7A3E2E, "Poppins (títulos)", "8 px / contenedor 1140 px"
            $table->string('nota')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
        Schema::table('eventos', fn (Blueprint $t) => $t->dropColumn('resuelto'));
        Schema::table('secciones', function (Blueprint $t) {
            $t->dropColumn(['estado', 'min_estimado']);
        });
    }
};
