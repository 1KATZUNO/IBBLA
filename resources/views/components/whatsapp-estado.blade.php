{{--
    Estado del WhatsApp automático (CallMeBot) de una persona, para quien administra:

        <x-whatsapp-estado :persona="$persona" />

    Solo se dibuja con WHATSAPP_DRIVER=callmebot. Dice si la persona lo activó, desde cuándo y si lo probó;
    nunca muestra la clave. Quien lo activa es la propia persona desde Mi perfil.
--}}
@props(['persona'])
@php
    $servicioWhatsApp = app(\App\Services\WhatsApp\WhatsAppService::class);
@endphp
@if($servicioWhatsApp->usaClavePorReceptor())
@php
    $situacion = app(\App\Services\WhatsApp\ActivacionWhatsAppService::class)->situacion($persona);
    $activacion = $persona->activacionWhatsApp;
    $telefonoActual = \App\Services\WhatsApp\WhatsAppService::formatearTelefono(\App\Services\WhatsApp\WhatsAppService::normalizarTelefono($persona->telefono));
@endphp
<div {{ $attributes }}>
    <label class="text-sm font-medium text-gray-500">WhatsApp automático</label>
    <p class="mt-1 text-base text-gray-900">
        @if($situacion === 'activa')
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">Activado</span>
            desde el {{ $activacion->consentimiento_at?->format('d/m/Y') }} ({{ $activacion->telefono_formateado }}),
            {{ $activacion->verificado_at ? 'probado el '.$activacion->verificado_at->format('d/m/Y') : 'todavía sin probar' }}.
        @elseif($situacion === 'numero_cambio')
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-amber-100 text-amber-800">Hay que activarlo de nuevo</span>
            Cambió de teléfono ({{ $telefonoActual ?: 'sin teléfono' }}) después de activarlo.
        @elseif($situacion === 'ilegible')
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">Hay que activarlo de nuevo</span>
            La clave guardada no se puede leer.
        @elseif($situacion === 'desactivada')
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-700">Desactivado</span>
            La persona lo desactivó{{ $activacion->revocado_at ? ' el '.$activacion->revocado_at->format('d/m/Y') : '' }}.
        @else
            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-700">Sin activar</span>
            @if($persona->user_id)
                La persona lo activa desde su perfil (Mi perfil). Mientras tanto «Enviar por WhatsApp» abre WhatsApp a mano.
            @else
                Esta persona no tiene cuenta para entrar al sistema, por eso no puede activarlo: «Enviar por WhatsApp» abre WhatsApp a mano.
            @endif
        @endif
    </p>
    @if($situacion === 'activa' && $activacion->ultimo_error)
    <p class="mt-1 text-sm text-red-700">El último envío falló ({{ $activacion->ultimo_envio_at?->format('d/m/Y H:i') }}): {{ $activacion->ultimo_error }}</p>
    @endif
</div>
@endif
