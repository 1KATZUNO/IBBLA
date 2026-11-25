@extends('layouts.admin')

@section('title', tenant_title('Agregar persona al corte'))
@section('page-title', 'Agregar persona a «'.$corte->nombre.'»')
@section('page-subtitle', 'Busca a la persona en el sistema. Si no está, dala de alta abajo.')

@section('content')
<div class="max-w-3xl space-y-6">
    <a href="{{ route('promesas-futuras.show', $corte) }}" class="btn-ghost">← Volver al corte</a>

    <div class="card p-4 sm:p-6">
        <h2 class="section-title">1. Buscar una persona que ya existe</h2>
        <form method="GET" class="mt-3 flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[220px]">
                <label for="q" class="field-label">Nombre, PIN o teléfono</label>
                <input id="q" name="q" value="{{ $q }}" class="input-primary w-full" autofocus>
            </div>
            <button class="btn-primary">Buscar</button>
        </form>

        @if(mb_strlen($q) >= 2)
            @if($resultados->isEmpty())
                <p class="mt-4 text-sm text-gray-600">No hay nadie con «{{ $q }}». Revisa cómo se escribe o dala de alta abajo.</p>
            @else
                <ul class="mt-4 divide-y divide-gray-100">
                    @foreach($resultados as $p)
                        <li class="py-2 flex items-center justify-between gap-3">
                            <div>
                                <span class="font-medium">{{ $p->nombre }}</span>
                                <span class="text-xs text-gray-500">@if($p->pin) PIN {{ $p->pin }} @endif @if($p->telefono) · {{ $p->telefono }} @endif</span>
                                @if(! $p->activo)
                                    <span class="badge-danger">inactiva</span>
                                @endif
                            </div>
                            <a href="{{ route('promesas-futuras.personas.edit', [$corte, $p]) }}" class="btn-row">{{ isset($enCorte[$p->id]) ? 'Ya está: editar' : 'Elegir' }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>

    <form method="POST" action="{{ route('promesas-futuras.personas.crear', $corte) }}" class="card p-4 sm:p-6 space-y-4">
        @csrf
        <h2 class="section-title">2. O dar de alta a una persona nueva</h2>
        <p class="text-sm text-gray-600">Igual que el alta rápida: solo el nombre es obligatorio. Después escribes lo que promete.</p>
        <div>
            <label for="nombre" class="field-label">Nombre completo</label>
            <input id="nombre" name="nombre" value="{{ old('nombre') }}" class="input-primary w-full" required maxlength="255">
            @error('nombre')<p class="field-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label for="telefono" class="field-label">Teléfono (opcional)</label>
                <input id="telefono" name="telefono" value="{{ old('telefono') }}" class="input-primary w-full" inputmode="tel">
            </div>
            <div>
                <label for="correo" class="field-label">Correo (opcional)</label>
                <input id="correo" name="correo" type="email" value="{{ old('correo') }}" class="input-primary w-full">
                @error('correo')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>
        @if(session('warning'))
            <label class="flex items-center gap-2 min-h-[44px]">
                <input type="checkbox" name="crear_igual" value="1" class="h-5 w-5">
                <span class="text-sm">Es otra persona con el mismo nombre: crearla de todos modos.</span>
            </label>
        @endif
        <div class="flex justify-end">
            <button type="submit" class="btn-primary">Dar de alta y seguir</button>
        </div>
    </form>
</div>
@endsection
