@extends('layouts.super-admin')

@section('title', 'Dominios - ' . $tenant->siglas)
@section('page-title', 'Dominios de Email: ' . $tenant->nombre)

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Info General</a>
        <a href="{{ route('super-admin.tenants.branding', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Branding</a>
        <a href="{{ route('super-admin.tenants.categories', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Categorias</a>
        <a href="{{ route('super-admin.tenants.domains', $tenant) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium">Dominios</a>
        <a href="{{ route('super-admin.tenants.users', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Usuarios</a>
    </div>

    <!-- Add Domain Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Agregar Dominio</h3>
            <p class="text-sm text-gray-500 mt-1">Los usuarios con emails de estos dominios seran asociados a esta iglesia.</p>
        </div>
        <form method="POST" action="{{ route('super-admin.tenants.store-domain', $tenant) }}" class="p-6">
            @csrf
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label for="dominio" class="block text-sm font-medium text-gray-700 mb-1">Dominio <span class="text-red-500">*</span></label>
                    <input type="text" name="dominio" id="dominio" required value="{{ old('dominio') }}"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                        placeholder="Ej: ibbla.com">
                    @error('dominio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-slate-700 rounded-lg hover:bg-slate-600 transition-colors">
                    Agregar
                </button>
            </div>
        </form>
    </div>

    <!-- Domains List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Dominios registrados ({{ $domains->count() }})</h3>
        </div>
        <div class="divide-y divide-gray-100">
            @forelse($domains as $domain)
            <div class="px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">@{{ $domain->dominio }}</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            @if($domain->principal)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Principal</span>
                            @endif
                            @if($domain->activo)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Activo</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Inactivo</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div>
                    @if(!$domain->principal)
                        <form method="POST" action="{{ route('super-admin.tenants.destroy-domain', [$tenant, $domain]) }}" onsubmit="return confirm('Eliminar este dominio?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-medium hover:bg-red-100 transition-colors">
                                Eliminar
                            </button>
                        </form>
                    @else
                        <span class="text-xs text-gray-400">No eliminable</span>
                    @endif
                </div>
            </div>
            @empty
            <div class="px-6 py-12 text-center text-gray-500">
                No hay dominios registrados para esta iglesia.
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
