<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Proyecto extends Model
{
    protected $table = 'proyectos';
    protected $guarded = [];

    /** Segmentos de /proyectos/… que ya usa el panel y no pueden ser el slug de un proyecto. */
    private const RESERVADOS = ['nuevo'];

    protected static function booted(): void
    {
        static::creating(function (Proyecto $p) {
            $p->slug ??= self::slugUnico($p->nombre);
        });
    }

    /** Las URLs del panel usan el nombre: /proyectos/sitio-bodega-andina. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Los enlaces antiguos con número (/proyectos/1) siguen funcionando. */
    public function resolveRouteBinding($value, $field = null)
    {
        return parent::resolveRouteBinding($value, $field)
            ?? (! $field && ctype_digit((string) $value) ? static::find($value) : null);
    }

    /** Slug desde el nombre, sin repetirse ni chocar con rutas del panel ("cota", "cota-2"…). */
    public static function slugUnico(string $nombre, ?int $excepto = null): string
    {
        $base = Str::slug($nombre) ?: 'proyecto';
        if (in_array($base, self::RESERVADOS, true) || ctype_digit($base)) {
            $base = "proyecto-{$base}";
        }
        $slug = $base;
        for ($n = 2; static::where('slug', $slug)->when($excepto, fn ($q) => $q->whereKeyNot($excepto))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

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

    public function disenos(): HasMany
    {
        return $this->hasMany(Diseno::class)->orderByDesc('version');
    }

    public function disenoActivo(): HasOne
    {
        return $this->hasOne(Diseno::class)->where('estado', 'activa');
    }

    /** La conexión vigente del proyecto: la última registrada. */
    public function conexion(): HasOne
    {
        return $this->hasOne(Conexion::class)->latestOfMany();
    }
}
