<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Arranque limpio: el panel empieza sin proyectos. Los datos de demostración del documento
     * (cifras canónicas de Bodega Andina) se cargan solo a pedido:
     *   php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        //
    }
}
