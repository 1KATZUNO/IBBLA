@extends('layouts.admin')

@section('title', tenant_title('Agregar persona'))
@section('page-title', 'Agregar persona')

@section('content')
{{-- Alta rapida (decision del cliente 13/10): el tesorero agrega personas sin ver la lista ni las fichas.
     Los demas datos (promesas, ministerios, acceso) los completa el administrador en Personas. --}}
<div class="max-w-xl mx-auto">
    <form action="{{ route('personas.quick-store') }}" method="POST" class="bg-white rounded-lg shadow p-4 sm:p-6 space-y-4">
        @csrf
        <p class="text-sm text-gray-600">Para alguien que todavía no está en la lista (por ejemplo, una visita que trajo su sobre). Queda activa y lista para elegirla en los sobres.</p>
        <div>
            <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
            <input type="text" name="nombre" id="nombre" value="{{ old('nombre') }}" required maxlength="255" autocomplete="off" autofocus
                   class="w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @error('nombre')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="telefono" class="block text-sm font-medium text-gray-700 mb-1">Teléfono <span class="font-normal text-gray-500">(opcional)</span></label>
                <input type="tel" name="telefono" id="telefono" value="{{ old('telefono') }}" maxlength="30" inputmode="tel" autocomplete="off"
                       class="w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('telefono')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="correo" class="block text-sm font-medium text-gray-700 mb-1">Correo <span class="font-normal text-gray-500">(opcional)</span></label>
                <input type="email" name="correo" id="correo" value="{{ old('correo') }}" maxlength="255" autocomplete="off"
                       class="w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @error('correo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary">Agregar persona</button>
        </div>
    </form>
</div>
@endsection
