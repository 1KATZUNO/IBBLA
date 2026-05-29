@extends('layouts.admin')

@section('title', 'IBBSC - Importar Asistencia')
@section('page-title', 'Carga Retroactiva de Asistencias')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        {{ session('success') }}
    </div>
    @endif

    {{-- Resumen del último import (session flash) --}}
    @if(session('creadas') || session('actualizadas'))
    <div class="bg-emerald-50 border border-emerald-300 rounded-lg p-6">
        <h3 class="text-emerald-800 font-semibold mb-3">Resultado del import</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="font-medium text-emerald-700">Creadas: {{ count(session('creadas', [])) }}</p>
                <ul class="mt-1 space-y-1 text-emerald-700">
                    @foreach(session('creadas', []) as $r)
                        <li>· {{ $r['fecha'] }} ({{ $r['tipo_culto'] }}) — total {{ $r['total'] }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="font-medium text-emerald-700">Actualizadas: {{ count(session('actualizadas', [])) }}</p>
                <ul class="mt-1 space-y-1 text-emerald-700">
                    @foreach(session('actualizadas', []) as $r)
                        <li>· {{ $r['fecha'] }} ({{ $r['tipo_culto'] }}) — total {{ $r['total'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif

    {{-- Card: instrucciones --}}
    <div class="bg-white rounded-lg shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-900">¿Cómo cargar asistencias de meses anteriores?</h2>
        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
            <li>Descarga la <strong>plantilla Excel</strong>.</li>
            <li>Borra la fila de ejemplo (fila 2, en cursiva).</li>
            <li>Rellena una fila por cada culto histórico. Deja en blanco lo que no tengas (se interpreta como 0).</li>
            <li><strong>Fecha</strong> en formato <code>YYYY-MM-DD</code> (ej: <code>2026-01-04</code>).</li>
            <li><strong>Tipo Culto</strong> debe ser uno de: <code>domingo</code>, <code>domingo_pm</code>, <code>miercoles</code>, <code>sabado</code>, <code>especial</code>.</li>
            <li>Sube el archivo y se importa todo. Las filas válidas se guardan; las filas con error se reportan abajo para que las corrijas y vuelvas a subir.</li>
            <li>Toda asistencia cargada por este flujo queda marcada como <strong>retroactiva</strong> (auditoría).</li>
        </ol>
        <div class="flex gap-3 pt-2">
            <a href="{{ route('asistencia.importar.plantilla') }}"
               class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Descargar plantilla Excel
            </a>
        </div>
    </div>

    {{-- Card: form de subida --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Subir archivo</h2>
        <form method="POST" action="{{ route('asistencia.importar.procesar') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Archivo Excel (.xlsx o .xls, máx 5 MB)</label>
                <input type="file" name="archivo" accept=".xlsx,.xls" required
                       class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                @error('archivo')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit"
                    onclick="this.disabled=true; this.innerText='Procesando...'; this.form.submit();"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 transition">
                Importar
            </button>
        </form>
    </div>

    {{-- Errores --}}
    @if(!empty($errores ?? []))
    <div class="bg-red-50 border border-red-300 rounded-lg p-6">
        <h3 class="text-red-800 font-semibold mb-3">Errores en {{ $archivoNombre ?? 'el archivo' }}</h3>
        <ul class="space-y-1 text-sm">
            @foreach($errores as $err)
                <li class="text-red-700">
                    <strong>Fila {{ $err['fila'] }}:</strong> {{ $err['mensaje'] }}
                </li>
            @endforeach
        </ul>
        <p class="text-sm text-red-600 mt-3">Las filas con error <em>no se guardaron</em>. Las filas válidas sí. Corrige y vuelve a subir solo las filas que fallaron.</p>
    </div>

    @if(!empty($creadas ?? []) || !empty($actualizadas ?? []))
    <div class="bg-emerald-50 border border-emerald-300 rounded-lg p-6">
        <h3 class="text-emerald-800 font-semibold mb-3">Filas que sí se guardaron</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="font-medium text-emerald-700">Creadas: {{ count($creadas ?? []) }}</p>
                <ul class="mt-1 space-y-1 text-emerald-700">
                    @foreach(($creadas ?? []) as $r)
                        <li>· {{ $r['fecha'] }} ({{ $r['tipo_culto'] }}) — total {{ $r['total'] }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <p class="font-medium text-emerald-700">Actualizadas: {{ count($actualizadas ?? []) }}</p>
                <ul class="mt-1 space-y-1 text-emerald-700">
                    @foreach(($actualizadas ?? []) as $r)
                        <li>· {{ $r['fecha'] }} ({{ $r['tipo_culto'] }}) — total {{ $r['total'] }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    @endif
    @endif

</div>
@endsection
