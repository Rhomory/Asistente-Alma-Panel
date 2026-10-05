<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una conexión por sitio WordPress: qué servidor MCP de Elementor usa el asistente
 * para ese proyecto y qué versiones respondió el sitio en la última comprobación.
 * Nunca guarda contraseñas: el prompt de Elementor se almacena ya saneado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conexiones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $t->string('nombre_mcp', 80);              // ej. elementor-ecocreations
            $t->string('sitio_url', 200);
            $t->string('endpoint', 255)->nullable();   // URL /wp-json/… del MCP
            $t->string('usuario_wp', 80)->nullable();
            $t->text('prompt_saneado')->nullable();
            $t->string('estado', 20)->default('sin_comprobar'); // conectado|sin_conexion|sin_comprobar
            $t->string('wp_version', 20)->nullable();
            $t->string('elementor_version', 20)->nullable();
            $t->string('jetengine_version', 20)->nullable();
            $t->boolean('rest_activo')->default(false);
            $t->string('via', 80)->nullable();         // "host de Windows (172.x)" cuando aplica
            $t->timestamp('comprobada_en')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conexiones');
    }
};
