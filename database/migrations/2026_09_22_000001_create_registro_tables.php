<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro del Asistente Alma: proyecto → página → sección → evento.
 * Mismo esquema del allegado SQL del proyecto de mejora (SQLite),
 * portable a MySQL/PostgreSQL sin cambios de estructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('cliente')->nullable();      // referencial, sin datos sensibles
            $table->string('archivo_figma')->nullable(); // nombre del archivo, nunca tokens
            $table->string('sitio_wp')->nullable();      // URL del staging, nunca credenciales
            $table->timestamps();
        });

        Schema::create('paginas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('secciones_total')->default(0);
            $table->unsignedInteger('linea_base_min')->default(480); // DAP actual
            $table->string('estado')->default('pendiente'); // pendiente|construyendo|en_qc|aprobada
            $table->timestamps();
        });

        Schema::create('secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pagina_id')->constrained('paginas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('widget_plan')->nullable();   // mapeo según la guía técnica
            $table->string('ejecuto')->default('asistente'); // asistente|desarrollador|mixto
            $table->dateTime('inicio')->nullable();
            $table->dateTime('fin')->nullable();
            $table->unsignedInteger('minutos')->default(0);
            $table->unsignedInteger('min_asistente')->default(0);
            $table->unsignedInteger('min_dev')->default(0);
            $table->unsignedInteger('correcciones')->default(0);
            $table->boolean('aprobada')->default(false);
            $table->timestamps();
        });

        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seccion_id')->nullable()->constrained('secciones')->nullOnDelete();
            $table->string('tipo'); // construccion|correccion|aprobacion|verificacion|error
            $table->text('detalle')->nullable();
            $table->timestamps();
        });

        DB::statement('
            CREATE VIEW reporte_pagina AS
            SELECT p.id, p.proyecto_id, p.nombre, p.estado, p.linea_base_min,
                   COALESCE(SUM(s.minutos), 0)       AS min_total,
                   COALESCE(SUM(s.min_asistente), 0) AS min_asistente,
                   COALESCE(SUM(s.min_dev), 0)       AS min_dev,
                   COALESCE(SUM(s.correcciones), 0)  AS correcciones,
                   COUNT(s.id)                       AS secciones_reg
            FROM paginas p
            LEFT JOIN secciones s ON s.pagina_id = p.id
            GROUP BY p.id
        ');
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS reporte_pagina');
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('secciones');
        Schema::dropIfExists('paginas');
        Schema::dropIfExists('proyectos');
    }
};
