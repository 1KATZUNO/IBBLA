@extends('layouts.admin')

@php
    use App\Http\Controllers\PromesasFuturasController as PF;
    // Una fila por promesa existente; una fila vacía por cada categoría sin promesa
    $filas = [];
    foreach ($categorias as $cat) {
        $existentes = $suyas[$cat->slug] ?? collect();
        if ($existentes->isEmpty()) {
            $filas[] = ['cat' => $cat, 'monto' => null, 'frecuencia' => 'semanal', 'moneda' => 'CRC'];
        }
        foreach ($existentes as $p) {
            $filas[] = ['cat' => $cat, 'monto' => (float) $p->monto, 'frecuencia' => $p->frecuencia, 'moneda' => $p->moneda];
        }
    }
@endphp

@section('title', tenant_title($persona->nombre))
@section('page-title', $persona->nombre)
@section('page-subtitle', ($yaEsta ? 'Editar lo que promete en ' : 'Agregar a ').'«'.$corte->nombre.'» (desde el '.$corte->inicia_el->format('d/m/Y').'). Sus promesas de hoy no cambian.')

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-ghost">← Volver al corte</a>

    <form method="POST" action="{{ route('promesas-futuras.personas.update', [$corte, $persona]) }}" class="card overflow-hidden">
        @csrf
        @method('PUT')
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr><th>Categoría</th><th>Monto</th><th>Frecuencia</th><th>Moneda</th></tr>
                </thead>
                <tbody>
                    @foreach($filas as $i => $f)
                        <tr>
                            <td>
                                {{ $f['cat']->nombre }}
                                @if(! $f['cat']->activa)
                                    <span class="badge-info">nueva</span>
                                @endif
                                <input type="hidden" name="promesas[{{ $i }}][categoria]" value="{{ $f['cat']->slug }}">
                            </td>
                            <td>
                                <input name="promesas[{{ $i }}][monto]" inputmode="decimal" class="input-primary w-36 text-right"
                                    aria-label="Monto de {{ $f['cat']->nombre }}"
                                    value="{{ old("promesas.$i.monto", $f['monto'] !== null ? rtrim(rtrim(number_format($f['monto'], 2, '.', ''), '0'), '.') : '') }}" placeholder="0">
                            </td>
                            <td>
                                <select name="promesas[{{ $i }}][frecuencia]" class="input-primary" aria-label="Frecuencia de {{ $f['cat']->nombre }}">
                                    @foreach(PF::FRECUENCIAS as $valor => $texto)
                                        <option value="{{ $valor }}" @selected(old("promesas.$i.frecuencia", $f['frecuencia']) === $valor)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="promesas[{{ $i }}][moneda]" class="input-primary" aria-label="Moneda de {{ $f['cat']->nombre }}">
                                    @foreach(PF::MONEDAS as $valor => $texto)
                                        <option value="{{ $valor }}" @selected(old("promesas.$i.moneda", $f['moneda']) === $valor)>{{ $texto }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-4 py-2 text-xs text-gray-500">Deja vacío o en 0 lo que no promete. Los montos se guardan con dos decimales.</p>
        <div class="action-bar">
            <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-secondary">Cancelar</a>
            <button type="submit" class="btn-primary">Guardar</button>
        </div>
    </form>

    @if($yaEsta)
        <form method="POST" action="{{ route('promesas-futuras.personas.destroy', [$corte, $persona]) }}" class="card p-4 sm:p-6 border border-red-200"
            onsubmit="return confirm('¿Quitar a esta persona del corte?')">
            @csrf
            @method('DELETE')
            <h2 class="section-title text-red-800">Quitar del corte</h2>
            <p class="text-sm text-gray-700 mt-1">Cuando el corte entre en vigor, esta persona quedará sin promesa. La persona no se borra del sistema.</p>
            <button type="submit" class="btn-danger-soft mt-3">Quitar del corte</button>
        </form>
    @endif
</div>
@endsection
