@extends('layouts.admin')

@section('title', tenant_title('Accesos de edición'))
@section('page-title', 'Accesos de edición')

@section('content')
@php
    $campo = 'w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm';
    $tipoElegido = old('tipo', 'cultos');
    $cultosMarcados = collect(old('cultos', $cultoPreseleccionado ? [$cultoPreseleccionado] : []))->map(fn ($id) => (int) $id)->all();
    $opcionesTipo = [
        'cultos' => ['Cultos concretos', 'Uno o varios cultos cerrados que tú marques.'],
        'rango' => ['Un rango de fechas', 'Todos los cultos entre dos fechas.'],
        'todos' => ['Todos los cultos', 'Cualquier culto, también los que se cierren después.'],
    ];
@endphp
<div class="space-y-6">
    <div>
        <p class="text-sm text-gray-600 max-w-3xl">
            Un culto cerrado es solo lectura para tesorería. Aquí le das a un usuario permiso para <strong>corregir</strong>
            cultos cerrados, con fecha de vencimiento si quieres. Mientras tenga un acceso vigente también puede crear cultos.
            Cada acceso y cada cambio hecho con él quedan en la auditoría.
        </p>
    </div>

    {{-- Conceder --}}
    <form method="POST" action="{{ route('admin.accesos.store') }}" class="bg-white rounded-lg shadow" id="formAcceso">
        @csrf
        <div class="px-4 sm:px-6 py-3 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Conceder acceso</h3>
        </div>

        <div class="p-4 sm:p-6 space-y-6">
            {{-- 1. A quien --}}
            <div class="max-w-md">
                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                @if($usuarios->isEmpty())
                    <p class="text-sm text-gray-600">No hay usuarios de tesorería a quienes darles acceso.
                        <a href="{{ route('usuarios.create') }}" class="font-medium text-blue-700 hover:underline">Crea uno con el rol Tesorero</a>.</p>
                @else
                    <select id="user_id" name="user_id" class="{{ $campo }}" required>
                        <option value="">Elige a quién</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" @selected((int) old('user_id') === $usuario->id)>{{ $usuario->name }} · {{ $usuario->rol_nombre }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Solo aparecen usuarios que entran a Recuento y no son administradores (el administrador ya puede corregir todo).</p>
                @endif
            </div>

            {{-- 2. Sobre que cultos --}}
            <fieldset>
                <legend class="block text-sm font-medium text-gray-700 mb-2">¿Qué cultos podrá corregir?</legend>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    @foreach($opcionesTipo as $valor => [$titulo, $ayuda])
                        <label class="flex items-start gap-3 min-h-[44px] rounded-lg border p-3 cursor-pointer has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 border-gray-300 hover:bg-gray-50">
                            <input type="radio" name="tipo" value="{{ $valor }}" class="mt-1 text-blue-600 focus:ring-blue-500" data-tipo-acceso @checked($tipoElegido === $valor)>
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $titulo }}</span>
                                <span class="block text-xs text-gray-500">{{ $ayuda }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                {{-- Cultos concretos --}}
                <div class="mt-4" data-panel-tipo="cultos" @if($tipoElegido !== 'cultos') hidden @endif>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <label for="filtroCultos" class="text-sm text-gray-700">Marca los cultos cerrados <span class="text-gray-500">(<span data-marcados>{{ count($cultosMarcados) }}</span> marcados)</span></label>
                        <input type="search" id="filtroCultos" placeholder="Filtrar por fecha, p. ej. 11/10" autocomplete="off" class="w-full sm:w-64 min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                    <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-100" id="listaCultos">
                        @forelse($cultos as $culto)
                            @php $textoCulto = ucfirst($culto->fecha->locale('es')->isoFormat('ddd')).' '.$culto->fecha->format('d/m/Y').' · '.$culto->tipo_nombre; @endphp
                            <label class="flex items-center gap-3 min-h-[44px] px-3 py-2 cursor-pointer hover:bg-gray-50" data-texto="{{ \Illuminate\Support\Str::lower($textoCulto.' '.$culto->fecha->format('d/m')) }}">
                                <input type="checkbox" name="cultos[]" value="{{ $culto->id }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked(in_array($culto->id, $cultosMarcados, true))>
                                <span class="flex-1 text-sm text-gray-800">{{ $textoCulto }}</span>
                                <span class="text-xs text-gray-500 whitespace-nowrap">cerrado {{ $culto->cerrado_at?->format('d/m') }}{{ $culto->cerradoPor ? ' · '.$culto->cerradoPor->name : '' }}</span>
                            </label>
                        @empty
                            <p class="px-3 py-4 text-sm text-gray-500">Todavía no hay cultos cerrados.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Rango de fechas --}}
                <div class="mt-4" data-panel-tipo="rango" @if($tipoElegido !== 'rango') hidden @endif>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md">
                        <div>
                            <label for="desde" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                            <input type="date" id="desde" name="desde" value="{{ old('desde') }}" class="{{ $campo }}">
                        </div>
                        <div>
                            <label for="hasta" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                            <input type="date" id="hasta" name="hasta" value="{{ old('hasta') }}" class="{{ $campo }}">
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-sm text-gray-600" data-panel-tipo="todos" @if($tipoElegido !== 'todos') hidden @endif>
                    Podrá corregir cualquier culto cerrado. Úsalo solo con quien lleva la tesorería.
                </p>
            </fieldset>

            {{-- 3. Hasta cuando y por que --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label for="vence_el" class="block text-sm font-medium text-gray-700 mb-1">Vence el <span class="font-normal text-gray-500">(opcional)</span></label>
                    <div class="flex flex-wrap items-center gap-2">
                        <input type="date" id="vence_el" name="vence_el" value="{{ old('vence_el') }}" min="{{ now()->toDateString() }}" class="{{ $campo }} sm:max-w-[12rem]">
                        <button type="button" class="btn-secondary" data-vence="0">Hoy</button>
                        <button type="button" class="btn-secondary" data-vence="7">7 días</button>
                        <button type="button" class="btn-secondary" data-vence="30">30 días</button>
                        <button type="button" class="btn-secondary" data-vence="">Sin vencimiento</button>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Termina al final de ese día. Sin fecha dura hasta que lo revoques.</p>
                </div>
                <div>
                    <label for="motivo" class="block text-sm font-medium text-gray-700 mb-1">Motivo <span class="font-normal text-gray-500">(opcional)</span></label>
                    <input type="text" id="motivo" name="motivo" value="{{ old('motivo') }}" maxlength="255" placeholder="Ej.: corregir el sobre #12 del 11/10" class="{{ $campo }}">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3 px-4 sm:px-6 py-3 border-t border-gray-200 bg-gray-50 rounded-b-lg">
            <button type="submit" class="btn-primary" @disabled($usuarios->isEmpty())>Conceder acceso</button>
        </div>
    </form>

    {{-- Vigentes --}}
    <section class="bg-white rounded-lg shadow overflow-hidden" aria-labelledby="titulo-vigentes">
        <div class="px-4 sm:px-6 py-3 border-b border-gray-200">
            <h3 id="titulo-vigentes" class="text-lg font-semibold text-gray-900">Accesos vigentes <span class="text-sm font-normal text-gray-500">({{ $vigentes->count() }})</span></h3>
        </div>
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Puede corregir</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vence</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">Otorgado</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cambios</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Revocar</span></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($vigentes as $permiso)
                        <tr>
                            <td class="px-4 py-3 text-sm">
                                <span class="block font-medium text-gray-900">{{ $permiso->user?->name }}</span>
                                <span class="block text-xs text-gray-500">{{ $permiso->user?->email }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800">
                                {{ $permiso->descripcionAlcance($cultosPorId) }}
                                @if($permiso->motivo)
                                    <span class="block text-xs text-gray-500">{{ $permiso->motivo }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm whitespace-nowrap">
                                @if($permiso->vence_el)
                                    <span class="text-gray-800">{{ $permiso->vence_el->format('d/m/Y') }}</span>
                                    <span class="block text-xs text-gray-500">{{ $permiso->vence_el->locale('es')->diffForHumans() }}</span>
                                @else
                                    <span class="text-gray-500">Sin vencimiento</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap hidden lg:table-cell">
                                {{ $permiso->created_at->format('d/m/Y') }}
                                <span class="block text-xs text-gray-500">por {{ $permiso->otorgadoPor?->name ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-800">{{ $ediciones[$permiso->id] ?? 0 }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                <button type="button" class="btn-row text-red-700 hover:bg-red-50"
                                        data-confirmar="#form-revocar-{{ $permiso->id }}"
                                        data-mensaje="¿Revocar el acceso de {{ $permiso->user?->name }} ({{ $permiso->descripcionAlcance($cultosPorId) }})? Desde ese momento verá esos cultos en solo lectura.">
                                    Revocar
                                </button>
                                <form id="form-revocar-{{ $permiso->id }}" action="{{ route('admin.accesos.destroy', $permiso) }}" method="POST" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center">
                                <p class="text-base font-semibold text-gray-800">Nadie tiene accesos de edición</p>
                                <p class="mt-1 text-sm text-gray-500">Los cultos cerrados solo los corrige el administrador.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Lo que se cambio en cultos cerrados --}}
    <section class="bg-white rounded-lg shadow overflow-hidden" aria-labelledby="titulo-cambios">
        <div class="px-4 sm:px-6 py-3 border-b border-gray-200">
            <h3 id="titulo-cambios" class="text-lg font-semibold text-gray-900">Últimos cambios en cultos cerrados</h3>
            <p class="text-xs text-gray-500">Hechos con un acceso de edición o como corrección del administrador. El detalle completo está en Auditoría.</p>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse($ultimosCambios as $cambio)
                <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 sm:px-6 py-2 text-sm">
                    <span class="tabular-nums text-gray-500 whitespace-nowrap">{{ $cambio->created_at->format('d/m/Y H:i') }}</span>
                    <span class="font-medium text-gray-900">{{ $cambio->user_name }}</span>
                    <span class="text-gray-700">{{ $cambio->datos['accion'] ?? $cambio->action }}</span>
                    @if(! empty($cambio->datos['culto_id']))
                        <a href="{{ route('recuento.culto', $cambio->datos['culto_id']) }}" class="text-blue-700 hover:underline">
                            culto del {{ ! empty($cambio->datos['fecha_culto']) ? \Carbon\Carbon::parse($cambio->datos['fecha_culto'])->format('d/m/Y') : '#'.$cambio->datos['culto_id'] }}
                        </a>
                    @endif
                    <span class="ml-auto rounded-full px-2 py-0.5 text-xs font-medium {{ empty($cambio->datos['permiso_id']) ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ empty($cambio->datos['permiso_id']) ? 'Corrección del administrador' : 'Con acceso de edición' }}
                    </span>
                </li>
            @empty
                <li class="px-4 sm:px-6 py-6 text-sm text-gray-500">Todavía no hay cambios en cultos cerrados.</li>
            @endforelse
        </ul>
    </section>

    {{-- Historial --}}
    @if($historial->isNotEmpty())
    <details class="bg-white rounded-lg shadow overflow-hidden">
        <summary class="flex items-center justify-between min-h-[44px] px-4 sm:px-6 py-3 cursor-pointer text-lg font-semibold text-gray-900">
            Vencidos y revocados <span class="text-sm font-normal text-gray-500">({{ $historial->count() }})</span>
        </summary>
        <div class="table-scroll border-t border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuario</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Podía corregir</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terminó</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Cambios</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @foreach($historial as $permiso)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $permiso->user?->name }}</td>
                            <td class="px-4 py-3 text-sm text-gray-700">{{ $permiso->descripcionAlcance($cultosPorId) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                @if($permiso->sin_cultos)
                                    Sus cultos se eliminaron
                                @elseif($permiso->revocado_at)
                                    Revocado el {{ $permiso->revocado_at->format('d/m/Y') }}{{ $permiso->revocadoPor ? ' por '.$permiso->revocadoPor->name : '' }}
                                @else
                                    Venció el {{ $permiso->vence_el?->format('d/m/Y') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-right tabular-nums text-gray-800">{{ $ediciones[$permiso->id] ?? 0 }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    @endif
</div>

{{-- Confirmacion de revocar --}}
<div id="modalConfirmarAcceso" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/50" role="dialog" aria-modal="true" aria-labelledby="modalConfirmarAccesoTitulo">
    <div class="relative mx-4 sm:mx-auto mt-16 sm:mt-24 max-w-md rounded-lg bg-white p-5 shadow-xl">
        <h3 id="modalConfirmarAccesoTitulo" class="text-lg font-semibold text-gray-900">Revocar acceso</h3>
        <p id="modalConfirmarAccesoMensaje" class="mt-2 text-sm text-gray-600"></p>
        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <button type="button" class="btn-secondary" data-cerrar-modal>Cancelar</button>
            <button type="button" class="btn-danger" id="modalConfirmarAccesoBoton">Sí, revocar</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    // Mostrar solo el panel del alcance elegido
    const radios = document.querySelectorAll('[data-tipo-acceso]');
    function mostrarPanel() {
        const elegido = document.querySelector('[data-tipo-acceso]:checked')?.value;
        document.querySelectorAll('[data-panel-tipo]').forEach(function (panel) {
            panel.hidden = panel.dataset.panelTipo !== elegido;
        });
    }
    radios.forEach(r => r.addEventListener('change', mostrarPanel));

    // Filtrar la lista de cultos y contar los marcados
    const filtro = document.getElementById('filtroCultos');
    filtro?.addEventListener('input', function () {
        const texto = filtro.value.trim().toLowerCase();
        document.querySelectorAll('#listaCultos [data-texto]').forEach(function (fila) {
            fila.classList.toggle('hidden', texto !== '' && !fila.dataset.texto.includes(texto));
        });
    });
    document.getElementById('listaCultos')?.addEventListener('change', function () {
        document.querySelector('[data-marcados]').textContent = document.querySelectorAll('#listaCultos input:checked').length;
    });

    // Atajos de vencimiento
    const vence = document.getElementById('vence_el');
    document.querySelectorAll('[data-vence]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            if (boton.dataset.vence === '') { vence.value = ''; return; }
            const fecha = new Date();
            fecha.setDate(fecha.getDate() + Number(boton.dataset.vence));
            vence.value = fecha.getFullYear() + '-' + String(fecha.getMonth() + 1).padStart(2, '0') + '-' + String(fecha.getDate()).padStart(2, '0');
        });
    });

    // Revocar con confirmacion
    const modal = document.getElementById('modalConfirmarAcceso');
    const confirmar = document.getElementById('modalConfirmarAccesoBoton');
    let formulario = null;
    document.addEventListener('click', function (e) {
        const pide = e.target.closest('[data-confirmar]');
        if (pide) {
            formulario = document.querySelector(pide.dataset.confirmar);
            document.getElementById('modalConfirmarAccesoMensaje').textContent = pide.dataset.mensaje;
            modal.classList.remove('hidden');
            confirmar.focus();
            return;
        }
        if (e.target.closest('[data-cerrar-modal]') || e.target === modal) modal.classList.add('hidden');
    });
    confirmar.addEventListener('click', function () {
        if (formulario) { confirmar.disabled = true; formulario.submit(); }
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') modal.classList.add('hidden'); });
})();
</script>
@endpush
@endsection
