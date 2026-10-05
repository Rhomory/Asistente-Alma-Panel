<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pagina extends Model
{
    protected $table = 'paginas';
    protected $guarded = [];
    protected $casts = ['incluida' => 'boolean'];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class);
    }

    /**
     * URL de la página en el sitio. Si se guardó una ruta (`/nosotros`) o una URL completa, manda esa;
     * si no, se arma desde el sitio del proyecto: "Inicio" es la raíz y el resto va como /slug.
     */
    public function urlSitio(): ?string
    {
        $sitio = rtrim((string) $this->proyecto?->sitio_wp, '/');
        $propia = trim((string) $this->url);

        if (preg_match('#^https?://#i', $propia)) {
            return $propia;
        }
        if ($sitio === '') {
            return null;
        }
        if ($propia !== '') {
            return $propia === '/' ? $sitio : $sitio . '/' . ltrim($propia, '/');
        }

        return in_array(mb_strtolower($this->nombre), ['inicio', 'home', 'portada'], true)
            ? $sitio
            : $sitio . '/' . \Illuminate\Support\Str::slug($this->nombre);
    }
}
