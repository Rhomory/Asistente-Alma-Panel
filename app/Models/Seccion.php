<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seccion extends Model
{
    protected $table = 'secciones';
    protected $guarded = [];
    protected $casts = ['aprobada' => 'boolean', 'inicio' => 'datetime', 'fin' => 'datetime'];

    public function pagina(): BelongsTo
    {
        return $this->belongsTo(Pagina::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }
}
