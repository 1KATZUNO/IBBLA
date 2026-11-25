@extends('layouts.admin')

@section('title', tenant_title('Eliminar corte'))
@section('page-title', 'Eliminar «'.$corte->nombre.'»')
@section('page-subtitle', 'Acción que no se puede deshacer: revisa bien antes de confirmar.')

@section('content')
<div class="max-w-3xl">
    <div class="card p-6 border border-red-200">
        <h2 class="text-lg font-semibold text-red-800">Esto es lo que se va a borrar</h2>
        <ul class="mt-3 list-disc pl-5 space-y-1 text-sm text-gray-800">
            <li>El corte «{{ $corte->nombre }}», que iba a entrar en vigor el {{ $corte->inicia_el->format('d/m/Y') }}.</li>
            <li>{{ $promesas }} {{ $promesas === 1 ? 'promesa programada' : 'promesas programadas' }} de {{ $personas }} {{ $personas === 1 ? 'persona' : 'personas' }}.</li>
            @if($categorias->isNotEmpty())
                <li>Las categorías nuevas que se crearon para este corte y todavía no se usan: {{ $categorias->implode(', ') }}.</li>
            @endif
        </ul>
        <h3 class="mt-5 text-sm font-semibold text-gray-900">Lo que NO se borra</h3>
        <p class="mt-1 text-sm text-gray-700">Las promesas que rigen hoy, los sobres, los compromisos y las personas (también las que se crearon al importar).</p>

        <form method="POST" action="{{ route('promesas-futuras.destroy', $corte) }}" class="mt-6 space-y-3">
            @csrf
            @method('DELETE')
            <label for="confirmacion" class="field-label">Para eliminar, escribe CONFIRMAR</label>
            <input id="confirmacion" name="confirmacion" type="text" autocomplete="off" class="input-primary w-full sm:w-64" required>
            @error('confirmacion')<p class="field-error">{{ $message }}</p>@enderror
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-danger">Eliminar corte</button>
            </div>
        </form>
    </div>
</div>
@endsection
