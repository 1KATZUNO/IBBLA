@extends('layouts.admin')

@section('title', tenant_title('Mi Perfil'))
@section('page-title', 'Mi Perfil')

@section('content')
<div class="space-y-6">
    <!-- Información Personal -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-wrap items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                {{ substr($persona->nombre, 0, 2) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $persona->nombre }}</h2>
                <p class="text-gray-600">{{ $persona->correo }}</p>
                @if($persona->telefono)
                <p class="text-gray-600">{{ $persona->telefono }}</p>
                @endif
            </div>
            <div class="sm:ml-auto flex flex-wrap gap-2">
                <a href="{{ route('mi-perfil.informe', ['accion' => 'ver']) }}" target="_blank" rel="noopener" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">Mi estado de cuenta (PDF)</a>
                <a href="{{ route('mi-perfil.informe', ['accion' => 'descargar']) }}" class="px-4 py-2 border border-green-600 text-green-700 rounded-md hover:bg-green-50">Descargar</a>
            </div>
        </div>
    </div>

    {{-- Avisos por WhatsApp (CallMeBot): la tarjeta solo se dibuja con WHATSAPP_DRIVER=callmebot --}}
    <x-whatsapp-activacion :receptor="$persona ?? null" volver="mi-perfil" />

    <!-- Resumen General -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php
            $d = $datos;
            $colon = fn ($v, $dec = 2) => ($v < 0 ? '-' : '').'₡'.number_format(abs($v), $dec);
        @endphp
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow p-6 text-white">
            <p class="text-blue-100 text-sm">Le corresponde al {{ $d['corte'] }}</p>
            <p class="text-2xl lg:text-3xl font-bold mt-2 break-words">{{ $colon($d['total_esperado']) }}</p>
            <p class="text-blue-100 text-xs mt-1">Meses completos: {{ $d['meses_cobrados'] ?: 'ninguno todavía' }}</p>
        </div>

        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow p-6 text-white">
            <p class="text-green-100 text-sm">He dado en mis compromisos</p>
            <p class="text-2xl lg:text-3xl font-bold mt-2 break-words">{{ $colon($d['dado_en_compromisos']) }}</p>
            <p class="text-green-100 text-xs mt-1">Total contribuido en el año: {{ $colon($d['total_dado']) }}</p>
        </div>

        <div class="bg-gradient-to-br {{ $d['al_dia'] || ! $d['tiene_compromisos'] ? 'from-green-500 to-green-600' : 'from-red-500 to-red-600' }} rounded-lg shadow p-6 text-white">
            <p class="{{ $d['al_dia'] || ! $d['tiene_compromisos'] ? 'text-green-100' : 'text-red-100' }} text-sm">Estado</p>
            <p class="text-2xl lg:text-3xl font-bold mt-2 break-words">{{ $d['tiene_compromisos'] ? $d['estado'] : 'Sin promesa' }}</p>
            @if($d['tiene_compromisos'])
                <p class="{{ $d['al_dia'] ? 'text-green-100' : 'text-red-100' }} text-xs mt-1">Diferencia: {{ $d['diferencia'] >= 0 ? '+' : '' }}{{ $colon($d['diferencia']) }}</p>
            @endif
        </div>
    </div>

    <!-- Mis compromisos (acumulado al corte, igual que el PDF) -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Mis compromisos al {{ $d['corte'] }}</h3>
        </div>
        <div class="p-6">
            @if($d['tiene_compromisos'])
                <div class="space-y-4">
                    @foreach($d['compromisos'] as $c)
                    @php
                        $porcentaje = $c['esperado'] > 0 ? min(100, $c['dado'] / $c['esperado'] * 100) : 100;
                    @endphp
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div>
                                <h4 class="font-semibold text-gray-900">{{ $c['nombre'] }}</h4>
                                <p class="text-sm text-gray-600">Promesa: {{ $c['detalle'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-gray-900">{{ $colon($c['dado']) }}</p>
                                <p class="text-sm text-gray-600">de {{ $colon($c['esperado']) }} que corresponde a la fecha</p>
                            </div>
                        </div>

                        <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            <div class="h-full rounded-full {{ $c['al_dia'] ? 'bg-green-500' : 'bg-yellow-500' }}" style="width: {{ number_format($porcentaje, 1, '.', '') }}%"></div>
                        </div>

                        <div class="mt-2 flex items-center justify-between text-sm">
                            @if($c['al_dia'])
                                <span class="text-green-700 font-semibold">Al día{{ $c['diferencia'] > 0 ? ' (adelantado '.$colon($c['diferencia']).')' : '' }}</span>
                            @else
                                <span class="text-amber-700 font-semibold">Pendiente: {{ $colon(abs($c['diferencia'])) }}</span>
                            @endif
                            <span class="text-gray-500">{{ number_format($porcentaje, 1) }}%</span>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Mes a mes: solo detalle informativo (el estado se decide por el acumulado) -->
                <details class="mt-6 border border-gray-200 rounded-lg">
                    <summary class="px-4 py-3 cursor-pointer min-h-[44px] font-medium text-gray-800">Ver el detalle mes a mes</summary>
                    <div class="table-scroll">
                        <table class="min-w-full text-sm divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">Mes</th>
                                    @foreach($d['compromisos'] as $c)
                                        <th class="px-3 py-2 text-right font-medium text-gray-500">{{ $c['nombre'] }}<br><span class="font-normal">di / del mes</span></th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($d['detalle_mensual'] as $m)
                                <tr class="{{ $m['cobrable'] ? '' : 'text-gray-500' }}">
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        {{ $m['nombre'] }}
                                        @if(! $m['cobrable'])
                                            <span class="block text-xs">en curso</span>
                                        @endif
                                    </td>
                                    @foreach($d['compromisos'] as $c)
                                        <td class="px-3 py-2 whitespace-nowrap text-right">{{ $colon($m['categorias'][$c['categoria']]['dado'], 0) }} / {{ $colon($m['categorias'][$c['categoria']]['del_mes'], 0) }}</td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @else
                <div class="text-center py-12">
                    <p class="text-gray-600">No tienes promesas registradas.</p>
                    <p class="text-sm text-gray-500 mt-1">Total contribuido en el año: {{ $colon($d['total_dado']) }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Información Adicional -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
        <h4 class="font-semibold text-blue-900 mb-2">Cómo se calcula</h4>
        <ul class="text-sm text-blue-800 space-y-1">
            <li>• Son las mismas cifras de tu estado de cuenta en PDF.</li>
            <li>• «Corresponde a la fecha» suma tu promesa de los meses completos ({{ $d['meses_cobrados'] ?: 'ninguno todavía' }}); el mes en curso no se cobra, pero lo que das en él sí cuenta.</li>
            <li>• Cuenta lo que diste en las categorías de tus promesas; el diezmo y otras ofrendas se ven en el PDF pero no cuentan aquí.</li>
            <li>• Los montos en dólares se convierten a colones con el tipo de cambio del sobre o del día del culto.</li>
            <li>• Si tienes dudas, contacta al administrador de la iglesia.</li>
        </ul>
    </div>
</div>
@endsection
