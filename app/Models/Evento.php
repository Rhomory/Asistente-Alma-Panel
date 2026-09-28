<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evento extends Model
{
    protected $table = 'eventos';
    protected $guarded = [];
    protected $casts = ['resuelto' => 'boolean'];

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }
}
