@extends('layouts.admin')

@php use App\Http\Controllers\PromesasFuturasController as PF; @endphp

@section('title', tenant_title('Importar Excel'))
@section('page-title', 'Importar Excel a «'.$corte->nombre.'»')
@section('page-subtitle', 'Primero ves una revisión; nada se guarda hasta que tocas «Aplicar».')

@section('page-actions')
    <a href="{{ route('promesas-futuras.plantilla') }}" class="btn-secondary">Descargar plantilla</a>
@endsection

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-ghost">← Volver al corte</a>

    @isset($erroresArchivo)
        <div class="alert-error">
            <p class="font-semibold">El archivo «{{ $archivoNombre }}» no se pudo usar. Corrígelo y vuelve a subirlo:</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-sm">
                @foreach($erroresArchivo as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endisset

    <div class="card p-4 sm:p-6 text-sm text-gray-700 space-y-1">
        <p class="font-semibold text-gray-900">Cómo debe venir el Excel</p>
        <p>Fila 1 con los títulos. Columna A: <strong>Miembro</strong> (o <strong>Nombre</strong>); columna B: <strong>Clase</strong>; columna C: <strong>Frecuencia</strong> (Semanal, Quincenal o Mensual); de la D en adelante, una columna por categoría.</p>
        <p>La frecuencia de cada fila vale para todos sus montos. Si la celda está vacía, o si el archivo no trae la columna Frecuencia (las categorías empiezan entonces en la C), se usa la que elijas abajo.</p>
        <p>Un título que la iglesia no tiene se propone como <strong>categoría nueva</strong>. Si la persona ya está en este corte, <strong>se deja como está</strong>: no se cambia ni se duplica.</p>
    </div>

    <form method="POST" action="{{ route('promesas-futuras.importar.subir', $corte) }}" enctype="multipart/form-data" class="card p-4 sm:p-6 space-y-5">
        @csrf
        <div>
            <label for="archivo" class="field-label">Archivo de Excel (.xlsx o .xls, máximo 2 MB)</label>
            <input id="archivo" name="archivo" type="file" accept=".xlsx,.xls" required class="input-primary w-full py-2">
            @error('archivo')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="frecuencia" class="field-label">Frecuencia si la fila no la trae</label>
                <select id="frecuencia" name="frecuencia" class="input-primary w-full">
                    @foreach(PF::FRECUENCIAS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('frecuencia', 'semanal') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="moneda" class="field-label">Moneda</label>
                <select id="moneda" name="moneda" class="input-primary w-full">
                    @foreach(PF::MONEDAS as $valor => $texto)
                        <option value="{{ $valor }}" @selected(old('moneda', 'CRC') === $valor)>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="action-bar">
            <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">Subir y revisar</button>
        </div>
    </form>
</div>
@endsection
