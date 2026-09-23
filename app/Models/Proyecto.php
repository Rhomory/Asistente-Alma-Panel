<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    protected $table = 'proyectos';
    protected $guarded = [];

    public function paginas(): HasMany
    {
        return $this->hasMany(Pagina::class);
    }
}
