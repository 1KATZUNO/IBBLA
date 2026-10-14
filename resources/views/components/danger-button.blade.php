{{-- Boton de peligro (Breeze): eliminar y acciones destructivas --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-danger']) }}>
    {{ $slot }}
</button>
