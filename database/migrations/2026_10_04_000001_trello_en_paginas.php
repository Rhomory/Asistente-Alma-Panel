<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Enlace a la tarjeta de Trello de cada página, para el mensaje estándar de solicitud de QA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paginas', fn (Blueprint $t) => $t->string('trello_url')->nullable());
    }

    public function down(): void
    {
        Schema::table('paginas', fn (Blueprint $t) => $t->dropColumn('trello_url'));
    }
};
