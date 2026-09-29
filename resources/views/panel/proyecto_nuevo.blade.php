@extends('layouts.app')
@section('titulo', 'Nuevo proyecto')

@section('contenido')
    <h1>Nuevo proyecto</h1>
    <p class="sub">Registra el proyecto una sola vez; sus páginas se agregan luego desde la vista del proyecto y las secciones las escribe la consola.</p>

    <div class="card form-card">
        <form method="post" action="{{ route('proyecto.guardar') }}">
            @csrf
            <div class="campo">
                <label for="nombre">Nombre del proyecto *</label>
                <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" placeholder="Ej.: Sitio Clínica del Sur" required maxlength="120">
                @error('nombre')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="campo">
                <label for="cliente">Cliente (referencial)</label>
                <input id="cliente" name="cliente" type="text" value="{{ old('cliente') }}" placeholder="Razón social · ciudad — sin datos personales" maxlength="160">
                @error('cliente')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="campo">
                <label for="archivo_figma">Archivo de Figma</label>
                <input id="archivo_figma" name="archivo_figma" type="text" value="{{ old('archivo_figma') }}" placeholder="Nombre del archivo (no pegues enlaces con token)" maxlength="160">
                @error('archivo_figma')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="campo">
                <label for="sitio_wp">Staging de WordPress (URL)</label>
                <input id="sitio_wp" name="sitio_wp" type="url" value="{{ old('sitio_wp') }}" placeholder="https://proyecto-staging.tudominio.pe" maxlength="200">
                @error('sitio_wp')<span class="error">{{ $message }}</span>@enderror
            </div>
            <div class="acciones">
                <button class="btn p" type="submit">Crear proyecto</button>
                <a class="btn s" href="{{ route('dashboard') }}">Cancelar</a>
            </div>
        </form>
    </div>
@endsection
