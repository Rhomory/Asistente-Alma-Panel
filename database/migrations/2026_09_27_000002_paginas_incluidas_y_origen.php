<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El asistente detecta páginas y tokens al leer el diseño (registro:paginas /
 * registro:tokens); el panel decide qué se maqueta (check "incluida") y
 * distingue el origen de cada dato (detectado vs manual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', function (Blueprint $table) {
            $table->boolean('incluida')->default(true);   // desmarcada = no se maqueta
            $table->string('origen')->default('manual');  // detectada | manual
        });
        Schema::table('tokens', function (Blueprint $table) {
            $table->string('origen')->default('manual');  // detectado | manual
        });
    }

    public function down(): void
    {
        Schema::table('paginas', fn (Blueprint $t) => $t->dropColumn(['incluida', 'origen']));
        Schema::table('tokens', fn (Blueprint $t) => $t->dropColumn('origen'));
    }
};
