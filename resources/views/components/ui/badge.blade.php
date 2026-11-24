{{--
    Badge de estado. El color siempre significa algo (ver docs/UI.md):
    success = al dia / abierto / activo / enviado · warning = pendiente · danger = atrasado / inactivo
    info = informativo · neutral = sin estado · brand = categoria o rol

    <x-ui.badge tipo="success" punto>Abierto</x-ui.badge>
--}}
@props(['tipo' => 'neutral', 'punto' => false])
@php
    $clases = [
        'success' => 'badge-success',
        'warning' => 'badge-warning',
        'danger' => 'badge-danger',
        'info' => 'badge-info',
        'neutral' => 'badge-neutral',
        'brand' => 'badge-brand',
    ];
@endphp
<span {{ $attributes->class([$clases[$tipo] ?? 'badge-neutral', 'badge-dot' => $punto]) }}>{{ $slot }}</span>
