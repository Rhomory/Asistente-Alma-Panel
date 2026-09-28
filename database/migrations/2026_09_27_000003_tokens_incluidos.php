<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Igual que las páginas: el check decide qué tokens se aplican como estilos globales. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tokens', fn (Blueprint $t) => $t->boolean('incluido')->default(true));
    }

    public function down(): void
    {
        Schema::table('tokens', fn (Blueprint $t) => $t->dropColumn('incluido'));
    }
};
