<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diseno extends Model
{
    protected $table = 'disenos';
    protected $guarded = [];
    protected $casts = ['resumen' => 'array', 'adoptada_en' => 'datetime'];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function lectura(): array
    {
        return json_decode($this->datos, true) ?: [];
    }
}
