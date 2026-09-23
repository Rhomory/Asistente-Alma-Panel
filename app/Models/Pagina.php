<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pagina extends Model
{
    protected $table = 'paginas';
    protected $guarded = [];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class);
    }
}
