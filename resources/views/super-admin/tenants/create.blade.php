@extends('layouts.super-admin')

@section('title', 'Nueva Iglesia')
@section('page-title', 'Crear Nueva Iglesia')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Datos de la Nueva Iglesia</h3>
            <p class="text-sm text-gray-500 mt-1">Complete la informacion para registrar una nueva iglesia en la plataforma.</p>
        </div>

        <form method="POST" action="{{ route('super-admin.tenants.store') }}" class="p-6 space-y-8">
            @csrf

            <!-- Datos Basicos -->
            <div>
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Informacion Basica
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo de la iglesia <span class="text-red-500">*</span></label>
                        <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="Ej: Iglesia Biblica Bautista Los Angeles">
                        @error('nombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="siglas" class="block text-sm font-medium text-gray-700 mb-1">Siglas <span class="text-red-500">*</span></label>
                        <input type="text" name="siglas" id="siglas" value="{{ old('siglas') }}" required maxlength="20"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all uppercase"
                            placeholder="Ej: IBBLA">
                        @error('siglas') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="dominio" class="block text-sm font-medium text-gray-700 mb-1">Dominio de email principal <span class="text-red-500">*</span></label>
                        <input type="text" name="dominio" id="dominio" value="{{ old('dominio') }}" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="Ej: ibbla.com">
                        @error('dominio') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Configuracion -->
            <div>
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    Configuracion
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="color_theme" class="block text-sm font-medium text-gray-700 mb-1">Color principal <span class="text-red-500">*</span></label>
                        <select name="color_theme" id="color_theme" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                            @foreach($themes as $key => $theme)
                                <option value="{{ $key }}" {{ old('color_theme', 'blue') === $key ? 'selected' : '' }}>{{ $theme['label'] }}</option>
                            @endforeach
                        </select>
                        @error('color_theme') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="moneda_codigo" class="block text-sm font-medium text-gray-700 mb-1">Moneda <span class="text-red-500">*</span></label>
                        <select name="moneda_codigo" id="moneda_codigo" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                            @foreach($currencies as $code => $currency)
                                <option value="{{ $code }}" {{ old('moneda_codigo', 'CRC') === $code ? 'selected' : '' }}>{{ $currency['simbolo'] }} {{ $currency['nombre'] }}</option>
                            @endforeach
                        </select>
                        @error('moneda_codigo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700 mb-1">Zona horaria <span class="text-red-500">*</span></label>
                        <select name="timezone" id="timezone" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                            @foreach($timezones as $tz => $label)
                                <option value="{{ $tz }}" {{ old('timezone', 'America/Costa_Rica') === $tz ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Color Theme Preview -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Vista previa del color</label>
                <div class="flex gap-2" id="colorPreview">
                    @foreach($themes as $key => $theme)
                        <div class="color-swatch cursor-pointer rounded-lg w-10 h-10 border-2 transition-all {{ old('color_theme', 'blue') === $key ? 'border-gray-900 scale-110' : 'border-transparent' }}"
                             style="background-color: {{ $theme['600'] }}"
                             data-theme="{{ $key }}"
                             title="{{ $theme['label'] }}">
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Admin User -->
            <div>
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-4 flex items-center">
                    <svg class="w-4 h-4 mr-2 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Usuario Administrador
                </h4>
                <p class="text-sm text-gray-500 mb-4">Se creara automaticamente un usuario administrador para esta iglesia.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-1">Nombre del administrador <span class="text-red-500">*</span></label>
                        <input type="text" name="admin_name" id="admin_name" value="{{ old('admin_name', 'Administrador') }}" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all">
                        @error('admin_name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-1">Email del administrador <span class="text-red-500">*</span></label>
                        <input type="email" name="admin_email" id="admin_email" value="{{ old('admin_email') }}" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="Ej: admin@ibbla.com">
                        @error('admin_email') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="admin_password" class="block text-sm font-medium text-gray-700 mb-1">Contrasena <span class="text-red-500">*</span></label>
                        <input type="password" name="admin_password" id="admin_password" required minlength="6"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all"
                            placeholder="Minimo 6 caracteres">
                        @error('admin_password') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('super-admin.tenants.index') }}" class="px-6 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-slate-700 rounded-lg hover:bg-slate-600 transition-colors">
                    Crear Iglesia
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('.color-swatch').forEach(swatch => {
        swatch.addEventListener('click', function() {
            document.querySelectorAll('.color-swatch').forEach(s => {
                s.classList.remove('border-gray-900', 'scale-110');
                s.classList.add('border-transparent');
            });
            this.classList.remove('border-transparent');
            this.classList.add('border-gray-900', 'scale-110');
            document.getElementById('color_theme').value = this.dataset.theme;
        });
    });
</script>
@endpush
@endsection
