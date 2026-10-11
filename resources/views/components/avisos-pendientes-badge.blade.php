{{--
    Contador de avisos de WhatsApp pendientes, para poner junto al enlace del menú:

        <a href="{{ route('avisos.index') }}">Avisos <x-avisos-pendientes-badge /></a>

    No muestra nada si no hay pendientes. Cuenta solo los de la iglesia del usuario que navega
    (scope por iglesia del modelo). Es para quienes ven el panel: admin y tesorero.
--}}
@php
    $pendientes = \App\Models\AvisoWhatsApp::contarPendientes();
@endphp
@if($pendientes > 0)
<span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center px-2 py-1 text-xs font-semibold rounded-full bg-red-600 text-white']) }}>{{ $pendientes > 99 ? '99+' : $pendientes }}</span>
@endif
