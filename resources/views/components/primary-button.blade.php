{{-- Boton primario (Breeze): usa el boton del sistema visual, con el color de la iglesia --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary']) }}>
    {{ $slot }}
</button>
