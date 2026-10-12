{{--
    Botón «Enviar por WhatsApp» con el informe de una persona.

        <x-whatsapp-enviar :persona="$persona" />

    Abre en otra pestaña la ruta personas.whatsapp, que genera el enlace firmado al PDF y redirige al
    chat de WhatsApp con el mensaje ya escrito (solo falta tocar «enviar»). Si la persona no tiene un
    teléfono que sirva para WhatsApp el botón sale deshabilitado y el tooltip dice cómo arreglarlo.
    Acepta class="..." para el espaciado del lugar donde se use, :grande="true" para un botón más alto y
    :compacto="true" para solo el icono (filas de tabla, junto a los demás botones de icono del layout).
--}}
@props(['persona', 'grande' => false, 'compacto' => false])
@php
    $telefonoGuardado = trim((string) $persona->telefono);
    // Tamaño: el normal va en filas de tabla; «grande» junto a botones de encabezado de página.
    $medida = $grande ? 'px-4 py-2 min-h-[44px] justify-center' : 'px-3 py-1 min-h-[44px] justify-center';
    $telefonoValido = \App\Services\WhatsApp\WhatsAppService::normalizarTelefono($persona->telefono) !== null;
    $icono = 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z';
@endphp
@if($compacto)
    @if($telefonoValido)
    <a href="{{ route('personas.whatsapp', $persona) }}" target="_blank" rel="noopener"
       aria-label="Enviar por WhatsApp: informe de {{ $persona->nombre }}"
       title="Abre WhatsApp con el informe de {{ $persona->nombre }} ya escrito; solo falta tocar enviar"
       {{ $attributes->merge(['class' => 'btn-icon text-green-700 hover:bg-green-50']) }}>
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icono }}"/></svg>
    </a>
    @else
    <button type="button" disabled aria-disabled="true"
            aria-label="Enviar por WhatsApp no disponible: sin teléfono válido"
            title="{{ $telefonoGuardado === '' ? $persona->nombre.' no tiene teléfono: edite la persona y agregue un número de 8 dígitos.' : 'El teléfono guardado ('.$telefonoGuardado.') no sirve para WhatsApp: edite la persona y deje un número de 8 dígitos.' }}"
            {{ $attributes->merge(['class' => 'btn-icon text-gray-300 cursor-not-allowed hover:bg-transparent hover:text-gray-300']) }}>
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icono }}"/></svg>
    </button>
    @endif
@elseif($telefonoValido)
<a href="{{ route('personas.whatsapp', $persona) }}" target="_blank" rel="noopener"
   title="Abre WhatsApp con el informe de {{ $persona->nombre }} ya escrito; solo falta tocar enviar"
   {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 '.$medida.' rounded-md bg-green-600 text-white text-sm font-medium hover:bg-green-700 transition-colors whitespace-nowrap']) }}>
    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icono }}"/></svg>
    Enviar por WhatsApp
</a>
@else
<span title="{{ $telefonoGuardado === '' ? $persona->nombre.' no tiene teléfono: edite la persona y agregue un número de 8 dígitos.' : 'El teléfono guardado ('.$telefonoGuardado.') no sirve para WhatsApp: edite la persona y deje un número de 8 dígitos.' }}"
      {{ $attributes->merge(['class' => 'inline-block']) }}>
    <button type="button" disabled aria-disabled="true"
            class="inline-flex items-center gap-1 {{ $medida }} rounded-md bg-gray-300 text-gray-500 text-sm font-medium cursor-not-allowed whitespace-nowrap">
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="{{ $icono }}"/></svg>
        Enviar por WhatsApp
    </button>
</span>
@endif
