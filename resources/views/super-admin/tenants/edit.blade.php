@extends('layouts.super-admin')

@section('title', 'Editar ' . $tenant->siglas)
@section('page-title', 'Editar: ' . $tenant->nombre)

@section('content')
<div class="max-w-3xl mx-auto">
    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium">Info General</a>
        <a href="{{ route('super-admin.tenants.branding', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Branding</a>
        <a href="{{ route('super-admin.tenants.categories', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Categorias</a>
        <a href="{{ route('super-admin.tenants.domains', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Dominios</a>
        <a href="{{ route('super-admin.tenants.users', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Usuarios</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Informacion General</h3>
        </div>

        <form method="POST" action="{{ route('super-admin.tenants.update', $tenant) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" id="nombre" value="{{ old('nombre', $tenant->nombre) }}" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                    @error('nombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="siglas" class="block text-sm font-medium text-gray-700 mb-1">Siglas <span class="text-red-500">*</span></label>
                    <input type="text" name="siglas" id="siglas" value="{{ old('siglas', $tenant->siglas) }}" required maxlength="20"
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all uppercase">
                    @error('siglas') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                    <input type="text" value="{{ $tenant->slug }}" disabled
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-500">
                </div>

                <div>
                    <label for="moneda_codigo" class="block text-sm font-medium text-gray-700 mb-1">Moneda <span class="text-red-500">*</span></label>
                    <select name="moneda_codigo" id="moneda_codigo" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                        @foreach($currencies as $code => $currency)
                            <option value="{{ $code }}" {{ old('moneda_codigo', $tenant->moneda_codigo) === $code ? 'selected' : '' }}>{{ $currency['simbolo'] }} {{ $currency['nombre'] }}</option>
                        @endforeach
                    </select>
                    @error('moneda_codigo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Zona horaria <span class="text-red-500">*</span></label>
                    <select name="timezone" id="timezone" required
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                        @foreach($timezones as $tz => $label)
                            <option value="{{ $tz }}" {{ old('timezone', $tenant->timezone) === $tz ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('timezone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Contacto -->
            <div class="pt-4 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4">Contacto</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="direccion" class="block text-sm font-medium text-gray-700 mb-1">Direccion</label>
                        <input type="text" name="direccion" id="direccion" value="{{ old('direccion', $tenant->direccion) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                    </div>

                    <div>
                        <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                        <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $tenant->telefono) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                    </div>

                    <div>
                        <label for="email_contacto" class="block text-sm font-medium text-gray-700 mb-1">Email de contacto</label>
                        <input type="email" name="email_contacto" id="email_contacto" value="{{ old('email_contacto', $tenant->email_contacto) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="sitio_web" class="block text-sm font-medium text-gray-700 mb-1">Sitio web</label>
                        <input type="text" name="sitio_web" id="sitio_web" value="{{ old('sitio_web', $tenant->sitio_web) }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                    </div>
                </div>
            </div>

            <!-- Redes Sociales -->
            <div class="pt-4 border-t border-gray-200">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4">Redes Sociales</h4>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="redes_instagram" class="block text-sm font-medium text-gray-700 mb-1">Instagram URL</label>
                        <input type="text" name="redes_instagram" id="redes_instagram" value="{{ old('redes_instagram', $tenant->redes_sociales['instagram'] ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="https://instagram.com/...">
                    </div>
                    <div>
                        <label for="redes_facebook" class="block text-sm font-medium text-gray-700 mb-1">Facebook URL</label>
                        <input type="text" name="redes_facebook" id="redes_facebook" value="{{ old('redes_facebook', $tenant->redes_sociales['facebook'] ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="https://facebook.com/...">
                    </div>
                    <div>
                        <label for="redes_youtube" class="block text-sm font-medium text-gray-700 mb-1">YouTube URL</label>
                        <input type="text" name="redes_youtube" id="redes_youtube" value="{{ old('redes_youtube', $tenant->redes_sociales['youtube'] ?? '') }}"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="https://youtube.com/...">
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <div><!-- Delete button is outside this form --></div>
                <div class="flex gap-3">
                    <a href="{{ route('super-admin.tenants.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancelar
                    </a>
                    <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-slate-700 rounded-lg hover:bg-slate-600 transition-colors">
                        Guardar Cambios
                    </button>
                </div>
            </div>
        </form>

        <!-- Delete form - must be OUTSIDE the edit form to avoid nested forms -->
        <div class="px-6 pb-6">
            <form method="POST" action="{{ route('super-admin.tenants.destroy', $tenant) }}" onsubmit="return confirm('Esta seguro que desea eliminar esta iglesia? Esta accion no se puede deshacer.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                    Eliminar Iglesia
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
