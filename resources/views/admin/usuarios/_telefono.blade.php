{{-- Teléfono (WhatsApp) del usuario. Variable: $telefono (valor guardado, o null al crear). --}}
<div class="mb-4">
    <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono (WhatsApp)</label>
    <input type="text" name="telefono" id="telefono" value="{{ old('telefono', $telefono ?? '') }}" inputmode="tel" placeholder="8888-7777"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
    <p class="mt-1 text-xs text-gray-500">
        Opcional. Los usuarios con rol de administrador o tesorero que tengan teléfono reciben avisos por WhatsApp
        (culto creado, sobres alcanzados). Escriba 8 dígitos; el prefijo +506 se agrega solo.
    </p>
    @error('telefono')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
