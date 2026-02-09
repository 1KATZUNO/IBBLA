@extends('layouts.admin')

@section('title', 'IBBSC - Auditoría')
@section('page-title', 'Registro de Auditoría')

@section('content')
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <div>
                <label class="text-sm text-gray-700">Usuario</label>
                <input type="text" name="user" value="{{ request('user') }}" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="text-sm text-gray-700">Acción</label>
                <input type="text" name="action" value="{{ request('action') }}" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="text-sm text-gray-700">Método</label>
                <select name="method" class="w-full rounded-md border-gray-300">
                    <option value="">Todos</option>
                    @foreach(['GET','POST','PUT','PATCH','DELETE'] as $m)
                        <option value="{{ $m }}" {{ request('method')===$m ? 'selected' : '' }}>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-700">Ruta</label>
                <input type="text" name="route" value="{{ request('route') }}" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="text-sm text-gray-700">IP</label>
                <input type="text" name="ip" value="{{ request('ip') }}" class="w-full rounded-md border-gray-300">
            </div>
            <div class="flex items-end">
                <button class="px-4 py-2 bg-blue-600 text-white rounded-md">Filtrar</button>
            </div>
            <div>
                <label class="text-sm text-gray-700">Desde</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full rounded-md border-gray-300">
            </div>
            <div>
                <label class="text-sm text-gray-700">Hasta</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full rounded-md border-gray-300">
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Fecha</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Usuario</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Método</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Ruta</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Acción</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">IP</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Agente</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-700">Detalles</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm">{{ $log->created_at }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->user_name }}<br><span class="text-xs text-gray-500">{{ $log->user_email }}</span></td>
                        <td class="px-4 py-2 text-sm">{{ $log->method }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->route }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->action }}</td>
                        <td class="px-4 py-2 text-sm">{{ $log->ip_address }}</td>
                        <td class="px-4 py-2 text-sm truncate max-w-xs">{{ $log->user_agent }}</td>
                        <td class="px-4 py-2 text-xs font-mono">{{ \Illuminate\Support\Str::limit($log->details, 200) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">No hay registros</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-t">{{ $logs->links() }}</div>
    </div>
    
    <div class="flex justify-end">
        <a href="{{ route('admin.auditoria.index', array_merge(request()->query(), ['page' => 1])) }}" class="px-4 py-2 bg-gray-100 rounded-md">Actualizar</a>
    </div>
</div>
@endsection
