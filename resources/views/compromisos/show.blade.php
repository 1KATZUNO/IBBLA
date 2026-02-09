@extends('layouts.admin')

@section('title', 'IBBSC - Compromisos - ' . $persona->nombre)
@section('page-title', 'Estado de Compromisos')

@section('content')
<div class="space-y-6">
    <!-- Header con información de la persona -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $persona->nombre }}</h2>
                <p class="text-gray-600 mt-1">
                    @if($persona->telefono) {{ $persona->telefono }} @endif
                    @if($persona->correo) • {{ $persona->correo }} @endif
                </p>
            </div>
            <a href="{{ route('personas.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                ← Volver
            </a>
        </div>
    </div>

    <!-- Filtro de mes/año -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" class="flex items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Año</label>
                <select name="año" class="rounded-md border-gray-300" onchange="this.form.submit()">
                    @for($y = date('Y'); $y >= date('Y') - 2; $y--)
                        <option value="{{ $y }}" {{ $año == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mes</label>
                <select name="mes" class="rounded-md border-gray-300" onchange="this.form.submit()">
                    @php
                        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    @endphp
                    @foreach($meses as $m => $nombreMes)
                        <option value="{{ $m + 1 }}" {{ $mes == ($m + 1) ? 'selected' : '' }}>{{ $nombreMes }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <!-- Resumen del Mes -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Total Esperado</p>
            <p class="text-2xl font-bold text-blue-600">₡{{ number_format($resumenTotal['total_prometido'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Total Dado</p>
            <p class="text-2xl font-bold text-green-600">₡{{ number_format($resumenTotal['total_dado'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <p class="text-sm text-gray-600">Diferencia del Mes</p>
            <p class="text-2xl font-bold {{ $resumenTotal['saldo_total'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                {{ $resumenTotal['saldo_total'] >= 0 ? '+' : '-' }}₡{{ number_format(abs($resumenTotal['saldo_total']), 2) }}
            </p>
        </div>
    </div>

    <!-- Tabla de Compromisos del Mes -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Detalle - {{ $meses[$mes - 1] }} {{ $año }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoría</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Esperado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Dado</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Diferencia</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($compromisos as $compromiso)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 capitalize">
                            {{ ucfirst($compromiso->categoria) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                            ₡{{ number_format($compromiso->monto_prometido, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-blue-600 font-semibold">
                            ₡{{ number_format($compromiso->monto_dado, 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold {{ $compromiso->saldo_actual >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $compromiso->saldo_actual >= 0 ? '+' : '-' }}₡{{ number_format(abs($compromiso->saldo_actual), 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            @if($compromiso->saldo_actual >= 0)
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                    @if($compromiso->saldo_actual > 0)
                                        Excedente
                                    @else
                                        Al día
                                    @endif
                                </span>
                            @else
                                <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                    Déficit
                                </span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            No hay promesas configuradas para esta persona
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historial de Meses Anteriores -->
    @if($historial->count() > 1)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Historial por Mes</h3>
        </div>
        <div class="p-6">
            <div class="space-y-4">
                @foreach($historial->take(6) as $periodo => $compsMes)
                @php
                    list($añoHist, $mesHist) = explode('-', $periodo);
                    $totalMes = $compsMes->sum('saldo_actual');
                @endphp
                <div class="border-l-4 {{ $totalMes >= 0 ? 'border-green-500' : 'border-red-500' }} pl-4">
                    <div class="flex justify-between items-center">
                        <h4 class="font-semibold text-gray-900">{{ $meses[intval($mesHist) - 1] }} {{ $añoHist }}</h4>
                        <span class="font-bold {{ $totalMes >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $totalMes >= 0 ? '+' : '-' }}₡{{ number_format(abs($totalMes), 2) }}
                        </span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                        @foreach($compsMes as $comp)
                        <div>
                            <span class="text-gray-600 capitalize">{{ $comp->categoria }}:</span>
                            <span class="font-semibold {{ $comp->saldo_actual >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $comp->saldo_actual >= 0 ? '+' : '-' }}₡{{ number_format(abs($comp->saldo_actual), 2) }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <!-- Notas explicativas -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <h4 class="text-sm font-semibold text-blue-900 mb-2">ℹ️ Información</h4>
        <ul class="text-sm text-blue-800 space-y-1">
            <li>• <strong>Esperado:</strong> Lo que debió dar este mes según su promesa</li>
            <li>• <strong>Dado:</strong> Lo que realmente dio este mes</li>
            <li>• <strong>Diferencia:</strong> Dado - Esperado (cada mes es independiente)</li>
            <li>• <strong>Excedente (verde):</strong> Dio más de lo esperado</li>
            <li>• <strong>Déficit (rojo):</strong> Dio menos de lo esperado</li>
        </ul>
    </div>
</div>
@endsection
