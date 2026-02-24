@extends('layouts.super-admin')

@section('title', 'Usuarios - ' . $tenant->siglas)
@section('page-title', 'Usuarios: ' . $tenant->nombre)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Info General</a>
        <a href="{{ route('super-admin.tenants.branding', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Branding</a>
        <a href="{{ route('super-admin.tenants.categories', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Categorias</a>
        <a href="{{ route('super-admin.tenants.domains', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Dominios</a>
        <a href="{{ route('super-admin.tenants.users', $tenant) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium">Usuarios</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Usuarios de {{ $tenant->siglas }} ({{ $users->count() }})</h3>
            <p class="text-sm text-gray-500 mt-1">Vista de solo lectura de los usuarios asociados a esta iglesia.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-700 text-white">
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Nombre</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Rol</th>
                        <th class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wider">Registrado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($users as $user)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-slate-200 flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-slate-600">{{ substr($user->name, 0, 2) }}</span>
                                </div>
                                <span class="font-medium text-gray-900">{{ $user->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @switch($user->rol)
                                    @case('admin') bg-red-100 text-red-700 @break
                                    @case('tesorero') bg-blue-100 text-blue-700 @break
                                    @case('asistente') bg-green-100 text-green-700 @break
                                    @case('miembro') bg-purple-100 text-purple-700 @break
                                    @default bg-gray-100 text-gray-700
                                @endswitch
                            ">
                                {{ ucfirst($user->rol) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center text-sm text-gray-500">
                            {{ $user->created_at->format('d/m/Y') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            No hay usuarios registrados para esta iglesia.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
