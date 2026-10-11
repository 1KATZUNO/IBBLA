@extends('layouts.admin')

@section('title', tenant_title('Historial de ingresos'))
@section('page-title', 'Historial de ingresos')

@section('content')
<div class="space-y-6">

    @if($errores->isNotEmpty())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <p class="font-semibold">Hay filtros que no se aplicaron:</p>
        @foreach($errores->all() as $mensaje)
            <p class="text-sm">{{ $mensaje }}</p>
        @endforeach
    </div>
    @endif

    {{-- Filtros --}}
    <div class="bg-white rounded-lg shadow p-4 sm:p-6">
        <form method="GET" action="{{ route('admin.auditoria.ingresos') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="usuario" class="block text-sm font-medium text-gray-700 mb-1">Usuario (nombre o correo)</label>
                    <input type="text" name="usuario" id="usuario" maxlength="100" value="{{ $entrada['usuario'] }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="evento" class="block text-sm font-medium text-gray-700 mb-1">Evento</label>
                    <select name="evento" id="evento" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="ingreso" {{ $entrada['evento'] === 'ingreso' ? 'selected' : '' }}>Inicios de sesión</option>
                        <option value="salida" {{ $entrada['evento'] === 'salida' ? 'selected' : '' }}>Cierres de sesión</option>
                        <option value="fallido" {{ $entrada['evento'] === 'fallido' ? 'selected' : '' }}>Intentos fallidos</option>
                    </select>
                </div>
                <div>
                    <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="{{ $entrada['fecha_inicio'] }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="{{ $entrada['fecha_fin'] }}"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">Filtrar</button>
                @if($hayFiltros)
                    <a href="{{ route('admin.auditoria.ingresos') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Quitar filtros</a>
                @endif
                <a href="{{ route('admin.auditoria.ingresos.exportar', array_filter($filtros)) }}"
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 font-semibold">
                    Descargar CSV
                </a>
                <a href="{{ route('admin.auditoria.index') }}" class="text-sm text-blue-600 hover:underline ml-auto">Ver auditoría completa</a>
            </div>
        </form>
    </div>

    {{-- Resumen del conjunto filtrado --}}
    <div class="flex flex-wrap gap-3 text-sm">
        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700"><strong>{{ number_format($totales['ingresos']) }}</strong> {{ $totales['ingresos'] === 1 ? 'inicio de sesión' : 'inicios de sesión' }}</span>
        <span class="px-3 py-1 rounded-full bg-gray-200 text-gray-600"><strong>{{ number_format($totales['salidas']) }}</strong> {{ $totales['salidas'] === 1 ? 'cierre de sesión' : 'cierres de sesión' }}</span>
        <span class="px-3 py-1 rounded-full bg-red-100 text-red-700"><strong>{{ number_format($totales['fallidos']) }}</strong> {{ $totales['fallidos'] === 1 ? 'intento fallido' : 'intentos fallidos' }}</span>
    </div>

    {{-- Tabla --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase whitespace-nowrap">Fecha y hora</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Usuario</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Correo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Evento</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">IP</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Navegador</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->user_name ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600">{{ $log->user_email ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm whitespace-nowrap">
                            @if($log->action === \App\Models\AuditLog::ACCION_LOGIN)
                                <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs">{{ $log->etiqueta_accion }}</span>
                            @elseif($log->action === \App\Models\AuditLog::ACCION_LOGIN_FALLIDO)
                                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs">{{ $log->etiqueta_accion }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-gray-200 text-gray-600 text-xs">{{ $log->etiqueta_accion }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm font-mono text-gray-600">{{ $log->ip_address ?? '—' }}</td>
                        <td class="px-4 py-2 text-sm text-gray-600 whitespace-nowrap" title="{{ $log->user_agent }}">{{ $log->navegador_resumen }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-500">
                            @if($hayFiltros)
                                No hay registros con estos filtros.
                            @else
                                Todavía no hay ingresos registrados. Se guardan automáticamente cada vez que alguien inicia o cierra sesión.
                            @endif
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
        @endif
    </div>

    <p class="text-xs text-gray-500">
        Las horas están en la zona horaria del sistema ({{ $zonaHoraria }}). Los inicios de sesión se conservan 12 meses.
    </p>
</div>
@endsection
