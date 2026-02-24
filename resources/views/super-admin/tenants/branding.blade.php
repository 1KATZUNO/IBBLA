@extends('layouts.super-admin')

@section('title', 'Branding - ' . $tenant->siglas)
@section('page-title', 'Branding: ' . $tenant->nombre)

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Info General</a>
        <a href="{{ route('super-admin.tenants.branding', $tenant) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium">Branding</a>
        <a href="{{ route('super-admin.tenants.categories', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Categorias</a>
        <a href="{{ route('super-admin.tenants.domains', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Dominios</a>
        <a href="{{ route('super-admin.tenants.users', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Usuarios</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Logo y Colores</h3>
            <p class="text-sm text-gray-500 mt-1">Personalice la apariencia de esta iglesia en la plataforma.</p>
        </div>

        <form method="POST" action="{{ route('super-admin.tenants.update-branding', $tenant) }}" enctype="multipart/form-data" class="p-6 space-y-8">
            @csrf
            @method('PUT')

            <!-- Logo -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Logo de la iglesia</label>
                <div class="flex items-start gap-6">
                    @if($tenant->logo_path)
                        <div class="flex-shrink-0">
                            <img src="{{ $tenant->logo_url }}" alt="Logo actual" class="w-24 h-24 rounded-xl object-contain bg-gray-50 border border-gray-200 p-2">
                            <p class="text-xs text-gray-500 mt-1 text-center">Logo actual</p>
                        </div>
                    @else
                        <div class="flex-shrink-0 w-24 h-24 rounded-xl bg-gray-100 border-2 border-dashed border-gray-300 flex items-center justify-center">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    @endif
                    <div class="flex-1">
                        <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/svg+xml"
                            class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200">
                        <p class="text-xs text-gray-500 mt-2">PNG, JPG o SVG. Maximo 2MB.</p>
                        @error('logo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Color Theme -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Tema de color</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($themes as $key => $theme)
                        <label class="cursor-pointer">
                            <input type="radio" name="color_theme" value="{{ $key }}" class="sr-only peer" {{ $tenant->color_theme === $key ? 'checked' : '' }}>
                            <div class="p-3 rounded-xl border-2 transition-all peer-checked:border-gray-900 peer-checked:shadow-md border-gray-200 hover:border-gray-400">
                                <div class="flex gap-1 mb-2">
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $theme['500'] }}"></div>
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $theme['600'] }}"></div>
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $theme['700'] }}"></div>
                                    <div class="w-6 h-6 rounded" style="background-color: {{ $theme['800'] }}"></div>
                                </div>
                                <p class="text-xs font-medium text-gray-700">{{ $theme['label'] }}</p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('color_theme') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Preview -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Vista previa del sidebar</label>
                <div class="rounded-xl overflow-hidden max-w-xs" id="sidebarPreview">
                    <div class="p-4 text-white" style="background-color: {{ \App\Models\Tenant::COLOR_THEMES[$tenant->color_theme]['800'] ?? '#1e40af' }}">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-white/10 rounded-lg flex items-center justify-center">
                                @if($tenant->logo_path)
                                    <img src="{{ $tenant->logo_url }}" alt="Logo" class="w-8 h-8 object-contain">
                                @else
                                    <span class="text-sm font-bold">{{ substr($tenant->siglas, 0, 2) }}</span>
                                @endif
                            </div>
                            <span class="font-bold">{{ $tenant->siglas }} Admin</span>
                        </div>
                    </div>
                    <div class="p-3 text-white/90 text-sm space-y-1" style="background-color: {{ \App\Models\Tenant::COLOR_THEMES[$tenant->color_theme]['800'] ?? '#1e40af' }}">
                        <div class="px-3 py-2 rounded-lg" style="background-color: {{ \App\Models\Tenant::COLOR_THEMES[$tenant->color_theme]['700'] ?? '#1d4ed8' }}">Principal</div>
                        <div class="px-3 py-2 rounded-lg opacity-70">Dashboard</div>
                        <div class="px-3 py-2 rounded-lg opacity-70">Recuento</div>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('super-admin.tenants.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-slate-700 rounded-lg hover:bg-slate-600 transition-colors">
                    Guardar Branding
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
