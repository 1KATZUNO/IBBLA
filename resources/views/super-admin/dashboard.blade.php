@extends('layouts.super-admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard Global')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-slate-600">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Iglesias</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalTenants }}</p>
            </div>
            <div class="w-12 h-12 bg-slate-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Iglesias Activas</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $activeTenants }}</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Usuarios Totales</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalUsers }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 border-l-4 border-l-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Miembros Totales</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalPersonas }}</p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Tenants Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-bold text-gray-900">Todas las Iglesias</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-700 text-white">
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Iglesia</th>
                    <th class="px-6 py-4 text-left text-sm font-semibold uppercase tracking-wider">Siglas</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Usuarios</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Categorias</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-4 text-center text-sm font-semibold uppercase tracking-wider">Creada</th>
                    <th class="px-6 py-4 text-right text-sm font-semibold uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($tenants as $tenant)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold" style="background-color: {{ \App\Models\Tenant::COLOR_THEMES[$tenant->color_theme]['600'] ?? '#475569' }}">
                                {{ substr($tenant->siglas, 0, 2) }}
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $tenant->nombre }}</p>
                                <p class="text-xs text-gray-500">{{ $tenant->slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-700">{{ $tenant->siglas }}</td>
                    <td class="px-6 py-4 text-center text-sm text-gray-700">{{ $tenant->users_count }}</td>
                    <td class="px-6 py-4 text-center text-sm text-gray-700">{{ $tenant->categories_count }}</td>
                    <td class="px-6 py-4 text-center">
                        @if($tenant->activo)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Activa</span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inactiva</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-center text-sm text-gray-500">{{ $tenant->created_at->format('d/m/Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="text-slate-600 hover:text-slate-800 text-sm font-medium">Editar</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        No hay iglesias registradas.
                        <a href="{{ route('super-admin.tenants.create') }}" class="text-slate-600 hover:underline font-medium">Crear primera iglesia</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
