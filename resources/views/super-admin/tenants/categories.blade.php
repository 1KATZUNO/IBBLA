@extends('layouts.super-admin')

@section('title', 'Categorias - ' . $tenant->siglas)
@section('page-title', 'Categorias: ' . $tenant->nombre)

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="{{ route('super-admin.tenants.edit', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Info General</a>
        <a href="{{ route('super-admin.tenants.branding', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Branding</a>
        <a href="{{ route('super-admin.tenants.categories', $tenant) }}" class="px-4 py-2 bg-slate-700 text-white rounded-lg text-sm font-medium">Categorias</a>
        <a href="{{ route('super-admin.tenants.domains', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Dominios</a>
        <a href="{{ route('super-admin.tenants.users', $tenant) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">Usuarios</a>
    </div>

    <!-- Add Category Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Agregar Categoria</h3>
        </div>
        <form method="POST" action="{{ route('super-admin.tenants.store-category', $tenant) }}" class="p-6">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
                <div class="sm:col-span-2">
                    <label for="nombre" class="block text-sm font-medium text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" name="nombre" id="nombre" required value="{{ old('nombre') }}"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all text-sm"
                        placeholder="Ej: Diezmo">
                </div>
                <div>
                    <label for="tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                    <select name="tipo" id="tipo" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all text-sm">
                        <option value="ambos">Ambos</option>
                        <option value="ingreso">Ingreso</option>
                        <option value="compromiso">Compromiso</option>
                    </select>
                </div>
                <div>
                    <label for="color" class="block text-sm font-medium text-gray-700 mb-1">Color</label>
                    <input type="color" name="color" id="color" value="{{ old('color', '#3b82f6') }}"
                        class="w-full h-[38px] px-1 py-1 rounded-lg border border-gray-300 bg-white cursor-pointer">
                </div>
                <div>
                    <label for="orden" class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                    <input type="number" name="orden" id="orden" value="{{ old('orden', 0) }}" min="0"
                        class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20 focus:outline-none transition-all text-sm">
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 text-sm font-semibold text-white bg-slate-700 rounded-lg hover:bg-slate-600 transition-colors">
                        Agregar
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <label class="inline-flex items-center text-sm text-gray-600">
                    <input type="checkbox" name="excluir_de_promesas" value="1" class="rounded border-gray-300 text-slate-600 focus:ring-slate-500 mr-2">
                    Excluir de promesas
                </label>
            </div>
            @if($errors->any())
                <div class="mt-2">
                    @foreach($errors->all() as $error)
                        <p class="text-red-500 text-xs">{{ $error }}</p>
                    @endforeach
                </div>
            @endif
        </form>
    </div>

    <!-- Categories Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-bold text-gray-900">Categorias existentes ({{ $categories->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-700 text-white">
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Orden</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Color</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Slug</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Tipo</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Excl. Promesas</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($categories as $category)
                    <tr class="hover:bg-gray-50 transition-colors" id="row-{{ $category->id }}">
                        <!-- View Mode -->
                        <td class="px-4 py-3 text-sm text-gray-700 view-mode">{{ $category->orden }}</td>
                        <td class="px-4 py-3 view-mode">
                            @if($category->color)
                                <div class="w-6 h-6 rounded-md" style="background-color: {{ $category->color }}"></div>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 view-mode">{{ $category->nombre }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 font-mono view-mode">{{ $category->slug }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 view-mode">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $category->tipo === 'ambos' ? 'bg-blue-100 text-blue-700' : ($category->tipo === 'ingreso' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700') }}">
                                {{ ucfirst($category->tipo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-gray-700 view-mode">
                            @if($category->excluir_de_promesas)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Si</span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right view-mode">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="toggleEdit({{ $category->id }})" class="text-slate-600 hover:text-slate-800 text-xs font-medium">Editar</button>
                                <form method="POST" action="{{ route('super-admin.tenants.destroy-category', [$tenant, $category]) }}" onsubmit="return confirm('Eliminar esta categoria?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">Eliminar</button>
                                </form>
                            </div>
                        </td>

                        <!-- Edit Mode (hidden by default) -->
                        <td colspan="7" class="px-4 py-3 edit-mode hidden">
                            <form method="POST" action="{{ route('super-admin.tenants.update-category', [$tenant, $category]) }}" class="flex items-center gap-3 flex-wrap">
                                @csrf
                                @method('PUT')
                                <input type="number" name="orden" value="{{ $category->orden }}" min="0" class="w-16 px-2 py-1.5 rounded border border-gray-300 text-sm">
                                <input type="color" name="color" value="{{ $category->color ?? '#3b82f6' }}" class="w-10 h-8 rounded border border-gray-300 cursor-pointer">
                                <input type="text" name="nombre" value="{{ $category->nombre }}" required class="w-32 px-2 py-1.5 rounded border border-gray-300 text-sm">
                                <select name="tipo" class="px-2 py-1.5 rounded border border-gray-300 text-sm">
                                    <option value="ambos" {{ $category->tipo === 'ambos' ? 'selected' : '' }}>Ambos</option>
                                    <option value="ingreso" {{ $category->tipo === 'ingreso' ? 'selected' : '' }}>Ingreso</option>
                                    <option value="compromiso" {{ $category->tipo === 'compromiso' ? 'selected' : '' }}>Compromiso</option>
                                </select>
                                <label class="inline-flex items-center text-xs text-gray-600">
                                    <input type="checkbox" name="excluir_de_promesas" value="1" {{ $category->excluir_de_promesas ? 'checked' : '' }} class="rounded border-gray-300 text-slate-600 focus:ring-slate-500 mr-1">
                                    Excl.
                                </label>
                                <button type="submit" class="px-3 py-1.5 bg-slate-700 text-white rounded text-xs font-medium hover:bg-slate-600">Guardar</button>
                                <button type="button" onclick="toggleEdit({{ $category->id }})" class="px-3 py-1.5 bg-gray-200 text-gray-700 rounded text-xs font-medium hover:bg-gray-300">Cancelar</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500 text-sm">
                            No hay categorias configuradas. Agregue una arriba.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function toggleEdit(id) {
        const row = document.getElementById('row-' + id);
        const viewCells = row.querySelectorAll('.view-mode');
        const editCell = row.querySelector('.edit-mode');

        viewCells.forEach(cell => cell.classList.toggle('hidden'));
        editCell.classList.toggle('hidden');
    }
</script>
@endpush
@endsection
