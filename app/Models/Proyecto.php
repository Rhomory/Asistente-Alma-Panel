<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Proyecto extends Model
{
    protected $table = 'proyectos';
    protected $guarded = [];

    public function paginas(): HasMany
    {
        return $this->hasMany(Pagina::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

    public function conexiones(): HasMany
    {
        return $this->hasMany(Conexion::class);
    }

    /** La conexión vigente del proyecto: la última registrada. */
    public function conexion(): HasOne
    {
        return $this->hasOne(Conexion::class)->latestOfMany();
    }
}
