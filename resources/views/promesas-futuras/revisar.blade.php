@extends('layouts.admin')

@php
    use App\Models\PromesaProgramada;
    use App\Services\Promesas\Importacion\AnalizadorImportacion as A;
    use App\Http\Controllers\PromesasFuturasController as PF;
    $titulos = collect($analisis['categorias'])->pluck('titulo', 'col');
    $montos = fn (array $fila) => collect($fila['montos'])->map(fn ($m, $col) => $titulos[$col].' '.PromesaProgramada::formatoMonto($m, $lote['moneda']))->implode(' · ');
    $ficha = fn (int $id) => $analisis['personas'][$id] ?? null;
    $decidir = collect($analisis['filas'])->whereIn('estado', [A::PARECIDO, A::NO_EXISTE]);
    $resto = collect($analisis['filas'])->whereNotIn('estado', [A::PARECIDO, A::NO_EXISTE]);
    $c = $analisis['conteo'];
    $etiquetas = [
        A::CALZA => ['Calza', 'success'],
        A::EN_CORTE => ['Ya está en este corte: se deja como está', 'info'],
        A::SIN_MONTOS => ['Sin montos: se ignora', 'neutral'],
        A::REPETIDA => ['Repetida en el archivo: se toma la primera', 'neutral'],
    ];
@endphp

@section('title', tenant_title('Revisar importación'))
@section('page-title', 'Revisar el Excel antes de aplicar')
@section('page-subtitle', '«'.$lote['archivo'].'» para «'.$corte->nombre.'». Montos '.mb_strtolower(PF::FRECUENCIAS[$lote['frecuencia']]).'es en '.($lote['moneda'] === 'USD' ? 'dólares' : 'colones').'. Todavía no se guardó nada.')

@section('content')
<form method="POST" action="{{ route('promesas-futuras.importar.aplicar', $corte) }}" id="form-aplicar" class="space-y-6">
    @csrf

    <div class="summary-grid">
        <x-ui.resumen etiqueta="Calzan" :valor="$c[A::CALZA]" nota="se agregan al corte" />
        <x-ui.resumen etiqueta="Ya están en el corte" :valor="$c[A::EN_CORTE]" nota="se dejan como están" />
        <x-ui.resumen etiqueta="Nombre parecido" :valor="$c[A::PARECIDO]" nota="tienes que elegir" />
        <x-ui.resumen etiqueta="No existen" :valor="$c[A::NO_EXISTE]" nota="se crean (o elige)" />
        <x-ui.resumen etiqueta="Se ignoran" :valor="$c[A::SIN_MONTOS] + $c[A::REPETIDA]" nota="sin montos o repetidas" />
    </div>

    {{-- Categorías --}}
    <div class="card p-4 sm:p-6">
        <h2 class="section-title">Categorías (títulos de las columnas)</h2>
        <p class="text-sm text-gray-600 mt-1">Una categoría nueva se crea <strong>inactiva</strong>: no aparece en los sobres ni en los reportes hasta el {{ $corte->inicia_el->format('d/m/Y') }}.</p>
        <div class="mt-4 divide-y divide-gray-100">
            @foreach($analisis['categorias'] as $col)
                <div class="py-3 grid sm:grid-cols-2 gap-2 items-center">
                    <div>
                        <span class="text-xs text-gray-500">Columna {{ $col['col'] }}</span>
                        <div class="font-medium">{{ $col['titulo'] }}</div>
                        @if($col['slug'])
                            <x-ui.badge tipo="success">Calza con {{ $col['nombre_categoria'] }}</x-ui.badge>
                        @else
                            <x-ui.badge tipo="warning">No existe en la iglesia</x-ui.badge>
                        @endif
                    </div>
                    <div>
                        <label class="sr-only" for="cat-{{ $col['col'] }}">Qué hacer con {{ $col['titulo'] }}</label>
                        <select id="cat-{{ $col['col'] }}" name="categorias[{{ $col['col'] }}]" class="input-primary w-full">
                            @php $elegida = old('categorias.'.$col['col'], $col['slug'] ?? 'nueva'); @endphp
                            @unless($col['slug'])
                                <option value="nueva" @selected($elegida === 'nueva')>Crear la categoría nueva «{{ $col['titulo'] }}»</option>
                            @endunless
                            @foreach($categorias as $cat)
                                <option value="{{ $cat->slug }}" @selected($elegida === $cat->slug)>Usar «{{ $cat->nombre }}»{{ $cat->activa ? '' : ' (nueva de este corte)' }}</option>
                            @endforeach
                            <option value="omitir" @selected($elegida === 'omitir')>No importar esta columna</option>
                        </select>
                        @error('categorias.'.$col['col'])<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Personas que necesitan decisión --}}
    <div class="card p-4 sm:p-6">
        <h2 class="section-title">Personas que necesitan tu decisión ({{ $decidir->count() }})</h2>
        @if($decidir->isEmpty())
            <p class="text-sm text-gray-600 mt-2">Ninguna: todos los nombres calzan con alguien del sistema.</p>
        @else
            <p class="text-sm text-gray-600 mt-1">Nunca se crea una persona repetida: si el nombre es parecido a alguien, elige quién es.</p>
            <datalist id="lista-personas">
                @foreach($todas as $p)
                    <option value="{{ $p->nombre }}{{ $p->pin ? ' · PIN '.$p->pin : '' }} #{{ $p->id }}"></option>
                @endforeach
            </datalist>
            <div class="mt-4 divide-y divide-gray-100">
                @foreach($decidir as $f)
                    @php $n = $f['fila']; $actual = old("filas.$n", $f['estado'] === A::NO_EXISTE ? 'nueva' : null); @endphp
                    <fieldset class="py-4" data-grupo="{{ $n }}">
                        <legend class="font-medium">
                            Fila {{ $n }}: {{ $f['nombre'] }}
                            @if($f['estado'] === A::PARECIDO)
                                <x-ui.badge tipo="warning">Nombre parecido</x-ui.badge>
                            @else
                                <x-ui.badge tipo="info">No existe</x-ui.badge>
                            @endif
                        </legend>
                        <p class="text-xs text-gray-500">{{ $f['clase'] ? 'Clase '.$f['clase'].' · ' : '' }}{{ $montos($f) }}</p>
                        <div class="mt-2 grid gap-1">
                            @foreach($f['candidatas'] as $id)
                                @php $p = $ficha($id); @endphp
                                <label class="flex items-center gap-2 min-h-[44px]">
                                    <input type="radio" name="filas[{{ $n }}]" value="persona:{{ $id }}" class="h-5 w-5" @checked($actual === 'persona:'.$id)>
                                    <span>Es <strong>{{ $p['nombre'] }}</strong>
                                        <span class="text-xs text-gray-500">{{ $p['pin'] ? 'PIN '.$p['pin'] : '' }}{{ $p['telefono'] ? ' · '.$p['telefono'] : '' }}</span>
                                        @if($p['en_corte'])
                                            <x-ui.badge tipo="info">ya está en este corte: se deja como está</x-ui.badge>
                                        @endif
                                        @if(! $p['activo'])
                                            <x-ui.badge tipo="danger">inactiva</x-ui.badge>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                            <label class="flex items-center gap-2 min-h-[44px]">
                                <input type="radio" name="filas[{{ $n }}]" value="buscar" class="h-5 w-5" @checked($actual === 'buscar')>
                                <span>Es otra persona:</span>
                                <input name="buscar[{{ $n }}]" list="lista-personas" value="{{ old("buscar.$n") }}" class="input-primary flex-1 min-w-[180px]" placeholder="Escribe y elige de la lista"
                                    aria-label="Buscar a la persona de la fila {{ $n }}" onfocus="this.closest('fieldset').querySelector('input[value=buscar]').checked = true; window.revisarAplicar && revisarAplicar()">
                            </label>
                            <label class="flex items-center gap-2 min-h-[44px]">
                                <input type="radio" name="filas[{{ $n }}]" value="nueva" class="h-5 w-5" @checked($actual === 'nueva')>
                                <span>Crear persona nueva «{{ $f['nombre'] }}»</span>
                            </label>
                            <label class="flex items-center gap-2 min-h-[44px]">
                                <input type="radio" name="filas[{{ $n }}]" value="omitir" class="h-5 w-5" @checked($actual === 'omitir')>
                                <span>Omitir esta fila</span>
                            </label>
                        </div>
                        @error("filas.$n")<p class="field-error">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach
            </div>
        @endif
    </div>

    {{-- El resto --}}
    <details class="card p-4 sm:p-6">
        <summary class="cursor-pointer min-h-[44px] flex items-center section-title">Ver las {{ $resto->count() }} filas que no necesitan decisión</summary>
        <div class="table-scroll mt-3">
            <table class="data-table">
                <thead><tr><th>Fila</th><th>Nombre en el archivo</th><th>Estado</th><th class="hidden md:table-cell">Montos</th></tr></thead>
                <tbody>
                    @foreach($resto as $f)
                        <tr>
                            <td class="tabular-nums">{{ $f['fila'] }}</td>
                            <td>{{ $f['nombre'] }}</td>
                            <td><x-ui.badge :tipo="$etiquetas[$f['estado']][1]">{{ $etiquetas[$f['estado']][0] }}</x-ui.badge>@if($f['estado'] === A::REPETIDA) <span class="text-xs text-gray-500">(fila {{ $f['primera'] }})</span>@endif</td>
                            <td class="hidden md:table-cell text-xs text-gray-600">{{ $montos($f) ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <div class="action-bar">
        <button type="submit" form="form-descartar" class="btn-secondary">Descartar archivo</button>
        <span id="aviso-pendientes" class="text-sm text-amber-700"></span>
        <button type="submit" id="btn-aplicar" class="btn-primary">Aplicar</button>
    </div>
</form>
<form method="POST" action="{{ route('promesas-futuras.importar.descartar', $corte) }}" id="form-descartar">@csrf</form>

<script>
    // "Aplicar" solo se habilita cuando todas las filas tienen una decisión
    function revisarAplicar() {
        const grupos = document.querySelectorAll('#form-aplicar fieldset[data-grupo]');
        let faltan = 0;
        grupos.forEach(g => {
            const elegido = g.querySelector('input[type=radio]:checked');
            const busca = g.querySelector('input[list]');
            if (!elegido || (elegido.value === 'buscar' && !/#\d+\s*$/.test(busca.value))) faltan++;
        });
        const btn = document.getElementById('btn-aplicar');
        btn.disabled = faltan > 0;
        btn.title = faltan > 0 ? 'Falta decidir ' + faltan + (faltan === 1 ? ' fila' : ' filas') : '';
        document.getElementById('aviso-pendientes').textContent = faltan > 0 ? 'Falta decidir ' + faltan + (faltan === 1 ? ' fila.' : ' filas.') : '';
    }
    document.getElementById('form-aplicar').addEventListener('change', revisarAplicar);
    document.getElementById('form-aplicar').addEventListener('input', revisarAplicar);
    revisarAplicar();
</script>
@endsection
