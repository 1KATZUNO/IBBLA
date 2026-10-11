@php
    $isEdit = isset($ministerio);
    $m = $ministerio ?? null;
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.ministerios.update', $m) : route('admin.ministerios.store') }}" class="space-y-4 bg-white rounded-lg shadow p-6">
    @csrf
    @if($isEdit) @method('PUT') @endif

    <div>
        <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
        <input type="text" name="nombre" id="nombre" required maxlength="80" value="{{ old('nombre', $m->nombre ?? '') }}"
               class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        @error('nombre') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="color" class="block text-sm font-medium text-gray-700 mb-1">Color *</label>
            <input type="color" name="color" id="color" value="{{ old('color', $m->color ?? '#6B7280') }}"
                   class="h-10 w-20 rounded-md border-gray-300">
            @error('color') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="orden" class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
            <input type="number" name="orden" id="orden" min="0" max="9999" value="{{ old('orden', $m->orden ?? '') }}"
                   placeholder="Automático (al final de la lista)"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <p class="text-xs text-gray-500 mt-1">Posición en la lista. Déjalo vacío para agregarlo al final.</p>
            @error('orden') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
        <textarea name="descripcion" id="descripcion" rows="2" maxlength="255"
                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('descripcion', $m->descripcion ?? '') }}</textarea>
        @error('descripcion') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="activo" value="0">
        <input type="checkbox" name="activo" id="activo" value="1" {{ old('activo', $m->activo ?? true) ? 'checked' : '' }}
               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
        <label for="activo" class="text-sm">Activo <span class="text-gray-500">(los inactivos no se ofrecen al asignar personas)</span></label>
    </div>

    <details class="text-sm" {{ $errors->has('slug') ? 'open' : '' }}>
        <summary class="cursor-pointer text-gray-600">Opciones avanzadas</summary>
        <div class="mt-3">
            <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">Identificador (slug)</label>
            <input type="text" name="slug" id="slug" maxlength="80" pattern="[a-z0-9_-]+" value="{{ old('slug', $m->slug ?? '') }}"
                   placeholder="Se genera solo desde el nombre"
                   class="w-full max-w-md rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 font-mono text-sm">
            <p class="text-xs text-gray-500 mt-1">Identificador interno (minúsculas, sin espacios). Normalmente no hace falta escribirlo: se genera a partir del nombre.</p>
            @error('slug') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
        </div>
    </details>

    <div class="flex gap-3 pt-4 border-t">
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-semibold">
            {{ $isEdit ? 'Guardar cambios' : 'Crear ministerio' }}
        </button>
        <a href="{{ route('admin.ministerios.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
            Cancelar
        </a>
    </div>
</form>
