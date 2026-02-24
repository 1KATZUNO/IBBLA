@extends('layouts.super-admin')

@section('title', 'Iglesias')
@section('page-title', 'Gestionar Iglesias')

@section('content')
<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <p class="text-gray-500">{{ $tenants->total() }} iglesia(s) registrada(s)</p>
    </div>
    <a href="{{ route('super-admin.tenants.create') }}" class="inline-flex items-center px-4 py-2.5 bg-slate-700 text-white text-sm font-semibold rounded-lg hover:bg-slate-600 transition-colors">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Nueva Iglesia
    </a>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, siglas o slug..."
                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all text-sm">
        </div>
        <div>
            <select name="estado" class="px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all text-sm">
                <option value="">Todas</option>
                <option value="activa" {{ request('estado') === 'activa' ? 'selected' : '' }}>Activas</option>
                <option value="inactiva" {{ request('estado') === 'inactiva' ? 'selected' : '' }}>Inactivas</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2.5 bg-slate-600 text-white rounded-lg hover:bg-slate-700 transition-colors text-sm font-medium">
            Filtrar
        </button>
    </form>
</div>

<!-- Tenants Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-700 text-white">
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider rounded-tl-lg">Iglesia</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Siglas</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Usuarios</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider rounded-tr-lg">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tenants as $tenant)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background-color: {{ \App\Models\Tenant::COLOR_THEMES[$tenant->color_theme]['600'] ?? '#475569' }}">
                                {{ substr($tenant->siglas, 0, 2) }}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $tenant->nombre }}</p>
                                <p class="text-xs text-gray-500">{{ $tenant->moneda_simbolo }} &middot; {{ $tenant->timezone }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-700">{{ $tenant->siglas }}</td>
                    <td class="px-6 py-4 text-center text-sm text-gray-700">{{ $tenant->users_count }}</td>
                    <td class="px-6 py-4 text-center">
                        @if($tenant->activo)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Activa</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                            <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="inline-flex items-center px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md text-xs font-medium hover:bg-slate-200 transition-colors" title="Editar">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Editar
                            </a>
                            <a href="{{ route('super-admin.tenants.branding', $tenant) }}" class="inline-flex items-center px-3 py-1.5 bg-purple-100 text-purple-700 rounded-md text-xs font-medium hover:bg-purple-200 transition-colors" title="Branding">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
                                Branding
                            </a>
                            <a href="{{ route('super-admin.tenants.categories', $tenant) }}" class="inline-flex items-center px-3 py-1.5 bg-amber-100 text-amber-700 rounded-md text-xs font-medium hover:bg-amber-200 transition-colors" title="Categorias">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                Categorias
                            </a>
                            <a href="{{ route('super-admin.tenants.domains', $tenant) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 rounded-md text-xs font-medium hover:bg-blue-200 transition-colors" title="Dominios">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                Dominios
                            </a>
                            <a href="{{ route('super-admin.tenants.users', $tenant) }}" class="inline-flex items-center px-3 py-1.5 bg-green-100 text-green-700 rounded-md text-xs font-medium hover:bg-green-200 transition-colors" title="Usuarios">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                Usuarios
                            </a>
                            <form method="POST" action="{{ route('super-admin.tenants.toggle', $tenant) }}" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-3 py-1.5 {{ $tenant->activo ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-green-100 text-green-700 hover:bg-green-200' }} rounded-md text-xs font-medium transition-colors" title="{{ $tenant->activo ? 'Desactivar' : 'Activar' }}">
                                    @if($tenant->activo)
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        Desactivar
                                    @else
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Activar
                                    @endif
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        No se encontraron iglesias.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tenants->hasPages())
    <div class="px-6 py-4 border-t border-gray-200">
        {{ $tenants->links() }}
    </div>
    @endif
</div>
@endsection
