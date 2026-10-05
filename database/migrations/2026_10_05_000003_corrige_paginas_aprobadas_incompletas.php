<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Páginas marcadas "aprobada" o "en_qc" que tienen secciones sin aprobar vuelven a "construyendo". */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('paginas')->whereIn('estado', ['aprobada', 'en_qc'])
            ->whereExists(fn ($q) => $q->from('secciones')->whereColumn('secciones.pagina_id', 'paginas.id')->where('secciones.estado', '!=', 'aprobada'))
            ->update(['estado' => 'construyendo']);
    }

    public function down(): void
    {
        // Corrección de datos: no se revierte.
    }
};
