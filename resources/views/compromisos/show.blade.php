@extends('layouts.admin')

@section('title', tenant_title('Compromisos - ' . $persona->nombre))
@section('page-title', 'Estado de Compromisos')

@section('content')
@php
    $d = $datos;
    $colon = fn ($v, $dec = 2) => ($v < 0 ? '-' : '').'₡'.number_format(abs($v), $dec);
    $conSigno = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '-' : '')).'₡'.number_format(abs($v), 2);
@endphp
<div class="space-y-6">
    <!-- Header con información de la persona -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-wrap justify-between items-start gap-3">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $persona->nombre }}</h2>
                <p class="text-gray-600 mt-1">
                    @if($persona->telefono) {{ $persona->telefono }} @endif
                    @if($persona->correo) • {{ $persona->correo }} @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-whatsapp-enviar :persona="$persona" :grande="true" />
                <a href="{{ route('personas.informe', ['persona' => $persona, 'accion' => 'ver']) }}" target="_blank" rel="noopener" class="btn-secondary">
                    Estado de cuenta (PDF)
                </a>
                <a href="{{ route('personas.informe', ['persona' => $persona, 'accion' => 'descargar']) }}" class="btn-secondary">
                    Descargar
                </a>
                <a href="{{ route('personas.index') }}" class="btn-secondary">
                    ← Volver
                </a>
                <a href="{{ route('personas.edit', $persona) }}" class="btn-secondary">
                    Editar persona
                </a>
            </div>
        </div>
    </div>

    <!-- Año (año en curso: corte hoy; años anteriores: al 31 de diciembre) -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="anio-compromisos" class="block text-sm font-medium text-gray-700 mb-2">Año</label>
                <select id="anio-compromisos" name="año" class="min-h-[44px] rounded-md border-gray-300" onchange="this.form.submit()">
                    @foreach($anios as $y)
                        <option value="{{ $y }}" {{ $año == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <p class="text-sm text-gray-600 pb-2">{{ $d['periodo'] }} · solo consulta, no modifica nada.</p>
        </form>
    </div>

    @if($d['tiene_compromisos'])
    <!-- Resumen acumulado al corte: las mismas cifras del estado de cuenta en PDF -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Le corresponde al {{ $d['corte'] }}</p>
            <p class="text-2xl font-bold text-blue-600">{{ $colon($d['total_esperado']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Meses completos: {{ $d['meses_cobrados'] ?: 'ninguno todavía' }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Ha dado en sus compromisos</p>
            <p class="text-2xl font-bold text-green-600">{{ $colon($d['dado_en_compromisos']) }}</p>
            <p class="text-xs text-gray-500 mt-1">Hasta el {{ $d['corte'] }}, incluido el mes en curso</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Diferencia</p>
            <p class="text-2xl font-bold {{ $d['diferencia'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $conSigno($d['diferencia']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Estado</p>
            <p class="mt-1">
                <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $d['al_dia'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $d['estado'] }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-2">Total contribuido (todas las categorías): {{ $colon($d['total_dado']) }}</p>
        </div>
    </div>

    <!-- Por compromiso -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Compromisos al {{ $d['corte'] }}</h3>
        </div>
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Promesa</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Le corresponde</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ha dado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($d['compromisos'] as $c)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $c['nombre'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $c['detalle'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">{{ $colon($c['esperado']) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-blue-700 font-semibold">{{ $colon($c['dado']) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $c['diferencia'] >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $conSigno($c['diferencia']) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $c['al_dia'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">{{ $c['al_dia'] ? 'Al día' : 'Pendiente' }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mes a mes: solo informativo (el estado se decide por el acumulado) -->
    <details class="bg-white rounded-lg shadow overflow-hidden">
        <summary class="px-6 py-4 bg-gray-50 cursor-pointer min-h-[44px] text-lg font-semibold text-gray-900">Detalle mes a mes (informativo)</summary>
        <div class="table-scroll">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mes</th>
                        @foreach($d['compromisos'] as $c)
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ $c['nombre'] }}<br><span class="normal-case font-normal">dado / del mes</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($d['detalle_mensual'] as $m)
                    <tr class="{{ $m['cobrable'] ? '' : 'bg-gray-50 text-gray-500' }}">
                        <td class="px-4 py-2 whitespace-nowrap font-medium">
                            {{ $m['nombre'] }}
                            @if(! $m['cobrable'])
                                <span class="block text-xs font-normal">en curso, no se cobra</span>
                            @endif
                        </td>
                        @foreach($d['compromisos'] as $c)
                            <td class="px-4 py-2 whitespace-nowrap text-right">{{ $colon($m['categorias'][$c['categoria']]['dado'], 0) }} / {{ $colon($m['categorias'][$c['categoria']]['del_mes'], 0) }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
    @else
    <div class="bg-white rounded-lg shadow p-6 text-gray-600">
        Esta persona no tiene promesas en categorías de compromiso. Total contribuido en el periodo: {{ $colon($d['total_dado']) }}.
    </div>
    @endif

    <!-- Notas explicativas -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-blue-900 mb-2">Cómo se calcula</h4>
        <ul class="text-sm text-blue-800 space-y-1">
            <li>• <strong>Le corresponde:</strong> lo prometido en los meses completos antes del corte (semanal × domingos del mes, quincenal × 2, mensual × 1). El mes en curso no se cobra.</li>
            <li>• <strong>Ha dado:</strong> todo lo entregado hasta el corte en las categorías con promesa, incluido el mes en curso. El diezmo y las demás ofrendas no cuentan.</li>
            <li>• <strong>Al día:</strong> lo dado alcanza lo que le corresponde (acumulado, no mes a mes).</li>
            <li>• Dólares en colones: tipo de cambio del sobre (o el del día del culto); promesas en dólares, el del último día de cada mes.</li>
            <li>• Son las mismas cifras del estado de cuenta en PDF, de Mi perfil y del reporte de cumplimiento.</li>
        </ul>
    </div>
</div>
@endsection
