<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conexion extends Model
{
    protected $table = 'conexiones';
    protected $guarded = [];
    protected $casts = ['rest_activo' => 'boolean', 'comprobada_en' => 'datetime'];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
