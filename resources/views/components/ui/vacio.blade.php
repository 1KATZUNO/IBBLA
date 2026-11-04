{{--
    Estado vacio: dice que paso y que se puede hacer.

    <x-ui.vacio titulo="No hay cultos registrados" texto="Crea el primero para empezar a registrar sobres.">
        <x-slot name="acciones"><a href="..." class="btn-primary">Crear culto</a></x-slot>
    </x-ui.vacio>
--}}
@props(['icono' => 'inbox', 'titulo', 'texto' => null])
<div {{ $attributes->class('empty-state') }}>
    <span class="empty-state-icon"><x-icono :nombre="$icono" class="h-6 w-6" /></span>
    <p class="empty-state-title">{{ $titulo }}</p>
    @if($texto)
        <p class="empty-state-text">{{ $texto }}</p>
    @endif
    @isset($acciones)
        <div class="empty-state-actions">{{ $acciones }}</div>
    @endisset
</div>
