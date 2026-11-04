@extends('layouts.admin')

@section('title', tenant_title('Mi Perfil'))
@section('page-title', 'Mi Perfil')

@section('content')
{{-- Cuenta del sistema (tesorero, asistente, admin...) sin ficha de persona: no hay promesas ni estado de cuenta, pero sí sus avisos. --}}
<div class="space-y-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-blue-600 flex items-center justify-center text-white text-2xl font-bold">
                {{ mb_strtoupper(mb_substr($usuario->name, 0, 2)) }}
            </div>
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-gray-900 break-words">{{ $usuario->name }}</h2>
                <p class="text-gray-600 break-all">{{ $usuario->email }}</p>
                @if($usuario->telefono)
                <p class="text-gray-600">{{ $usuario->telefono }}</p>
                @endif
            </div>
        </div>
        <p class="mt-4 text-base text-gray-700">
            Su cuenta es del sistema y no está ligada a la ficha de una persona de la iglesia, por eso aquí no hay promesas ni estado de cuenta.
            Si usted también da ofrendas y quiere verlas, pídale al administrador que ligue su cuenta a su ficha.
        </p>
    </div>

    <x-whatsapp-activacion :receptor="null" volver="mi-perfil" />
</div>
@endsection
