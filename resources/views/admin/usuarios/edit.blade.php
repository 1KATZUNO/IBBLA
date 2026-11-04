@extends('layouts.admin')

@section('title', tenant_title('Editar Usuario'))
@section('page-title', 'Editar Usuario')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-lg shadow">
        <div class="p-6">
                    <form action="{{ route('usuarios.update', $usuario) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Nombre -->
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $usuario->name) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email', $usuario->email) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @include('admin.usuarios._telefono', ['telefono' => $usuario->telefono])

                        <!-- Rol -->
                        <div class="mb-4">
                            <label for="rol" class="block text-sm font-medium text-gray-700">Rol</label>
                            <select name="rol" id="rol" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="">Seleccionar rol...</option>
                                <option value="admin" {{ old('rol', $usuario->rol) === 'admin' ? 'selected' : '' }}>Administrador - Todo, incluida la configuración</option>
                                <option value="tesorero" {{ old('rol', $usuario->rol) === 'tesorero' ? 'selected' : '' }}>Tesorero - Recuento, dashboards y reportes financieros</option>
                                <option value="asistente" {{ old('rol', $usuario->rol) === 'asistente' ? 'selected' : '' }}>Asistente - Asistencia y sus reportes</option>
                                <option value="servidor" {{ old('rol', $usuario->rol) === 'servidor' ? 'selected' : '' }}>Servidor - Marcar su asistencia y Mi perfil</option>
                                <option value="miembro" {{ old('rol', $usuario->rol) === 'miembro' ? 'selected' : '' }}>Miembro - Mi perfil y su estado de cuenta</option>
                            </select>
                            @error('rol')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="border-t border-gray-200 my-6 pt-6">
                            <p class="text-sm text-gray-600 mb-4">
                                <strong>Nota:</strong> Deja los campos de contraseña vacíos si no deseas cambiarla.
                            </p>

                            <!-- Nueva Contraseña -->
                            <div class="mb-4">
                                <label for="password" class="block text-sm font-medium text-gray-700">Nueva Contraseña</label>
                                <input type="password" name="password" id="password"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirmar Contraseña -->
                            <div class="mb-4">
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar Nueva Contraseña</label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6 gap-4">
                            <a href="{{ route('usuarios.index') }}" class="btn-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn-primary">
                                Actualizar Usuario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
