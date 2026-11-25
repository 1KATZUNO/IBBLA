@extends('layouts.admin')

@section('title', tenant_title('Auditoría'))
@section('page-title', 'Registro de Auditoría')

@section('page-subtitle', 'Lo que hicieron los administradores y tesoreros: quién, cuándo y desde dónde.')

@section('page-actions')
    <a href="{{ route('admin.auditoria.ingresos') }}" class="btn-secondary">
        <x-icono nombre="usuario" class="h-5 w-5" />
        Historial de ingresos
    </a>
@endsection

@section('content')
<div class="space-y-6">
    <form method="GET" class="filter-bar">
        <div class="filter-field w-full sm:w-56">
            <label for="f-ver" class="filter-label">Mostrar</label>
            <select id="f-ver" name="ver" class="input-primary" onchange="this.form.submit()">
                <option value="" {{ $verTodo ? '' : 'selected' }}>Solo acciones</option>
                <option value="todo" {{ $verTodo ? 'selected' : '' }}>Ver todo (visitas y datos técnicos)</option>
            </select>
        </div>
        <div class="filter-field w-full sm:w-auto sm:flex-1 sm:basis-40">
            <label for="f-user" class="filter-label">Usuario</label>
            <input id="f-user" type="text" name="user" value="{{ request('user') }}" class="input-primary">
        </div>
        <div class="filter-field w-full sm:w-auto sm:flex-1 sm:basis-40">
            <label for="f-action" class="filter-label">Acción</label>
            <input id="f-action" type="text" name="action" value="{{ request('action') }}" class="input-primary">
        </div>
        @if($verTodo)
        <div class="filter-field w-full sm:w-32">
            <label for="f-method" class="filter-label">Método</label>
            <select id="f-method" name="method" class="input-primary">
                <option value="">Todos</option>
                @foreach(['GET','POST','PUT','PATCH','DELETE'] as $m)
                    <option value="{{ $m }}" {{ request('method')===$m ? 'selected' : '' }}>{{ $m }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field w-full sm:w-auto sm:flex-1 sm:basis-40">
            <label for="f-route" class="filter-label">Ruta</label>
            <input id="f-route" type="text" name="route" value="{{ request('route') }}" class="input-primary">
        </div>
        <div class="filter-field w-full sm:w-36">
            <label for="f-ip" class="filter-label">IP</label>
            <input id="f-ip" type="text" name="ip" value="{{ request('ip') }}" class="input-primary">
        </div>
        @endif
        <div class="filter-field w-full sm:w-40">
            <label for="f-from" class="filter-label">Desde</label>
            <input id="f-from" type="date" name="from" value="{{ request('from') }}" class="input-primary">
        </div>
        <div class="filter-field w-full sm:w-40">
            <label for="f-to" class="filter-label">Hasta</label>
            <input id="f-to" type="date" name="to" value="{{ request('to') }}" class="input-primary">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-primary">Filtrar</button>
            <a href="{{ route('admin.auditoria.index', $verTodo ? ['ver' => 'todo'] : []) }}" class="btn-ghost">Limpiar</a>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        @if($verTodo)
                        <th>Método</th>
                        <th>Ruta</th>
                        @endif
                        <th>Acción</th>
                        @if($verTodo)
                        <th>IP</th>
                        <th>Agente</th>
                        @endif
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ $log->created_at }}</td>
                        <td>{{ $log->user_name }}<br><span class="text-xs text-gray-500">{{ $log->user_email }}</span></td>
                        @if($verTodo)
                        <td><x-ui.badge :tipo="$log->method === 'GET' ? 'neutral' : 'info'">{{ $log->method }}</x-ui.badge></td>
                        <td>{{ $log->route }}</td>
                        @endif
                        <td>{{ $log->etiqueta_accion }}</td>
                        @if($verTodo)
                        <td class="whitespace-nowrap">{{ $log->ip_address }}</td>
                        <td class="max-w-xs truncate">{{ $log->user_agent }}</td>
                        @endif
                        <td class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($log->details, 200) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $verTodo ? 8 : 4 }}" class="!p-0">
                            <x-ui.vacio icono="lupa" titulo="No hay registros" texto="Prueba con otros filtros o con un rango de fechas más amplio." />
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="card-footer">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
