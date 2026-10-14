{{-- Boton secundario (Breeze): cancelar, volver, filtros --}}
<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn-secondary']) }}>
    {{ $slot }}
</button>
