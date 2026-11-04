{{--
    Los tres pasos ilustrados para activar WhatsApp y el formulario donde se pega el enlace del bot.
    Lo incluye el componente x-whatsapp-activacion (hereda sus variables: $botNumero, $botFrase,
    $botEnlace, $ayudaUrl, $telefono, $telefonoFormateado, $consentimiento, $rutas, $tipo, $bolsa).
    Las imágenes salen de resources/manual/whatsapp/generar_imagenes.py.
--}}
@php
    $imagen = fn (string $archivo) => asset('images/manual/whatsapp/'.$archivo);
@endphp
<ol class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <li class="border border-gray-200 rounded-lg overflow-hidden">
        <img src="{{ $imagen('paso-1-contacto.png') }}" width="600" height="1000" loading="lazy"
             alt="Pantalla de contacto nuevo en el teléfono, con el nombre CallMeBot y el botón Guardar" class="w-full max-w-xs mx-auto">
        <div class="p-4 space-y-2">
            <h4 class="text-lg font-semibold text-gray-900">Paso 1. Guarde el contacto del bot</h4>
            <p class="text-base text-gray-700">En su celular agregue este número a sus contactos, con el nombre <strong>CallMeBot</strong>:</p>
            <p class="text-2xl font-bold text-gray-900" style="user-select: all;">{{ $botNumero }}</p>
        </div>
    </li>
    <li class="border border-gray-200 rounded-lg overflow-hidden">
        <img src="{{ $imagen('paso-2-mensaje.png') }}" width="600" height="1000" loading="lazy"
             alt="Conversación de WhatsApp con CallMeBot y el mensaje listo para enviar" class="w-full max-w-xs mx-auto">
        <div class="p-4 space-y-3">
            <h4 class="text-lg font-semibold text-gray-900">Paso 2. Mándele este mensaje</h4>
            <p class="text-base text-gray-700">Abra WhatsApp, entre a la conversación con CallMeBot y envíele <strong>exactamente</strong> este mensaje:</p>
            <p class="px-3 py-2 bg-gray-100 rounded-md text-base font-mono text-gray-900 break-words" style="user-select: all;">{{ $botFrase }}</p>
            <a href="{{ $botEnlace }}" target="_blank" rel="noopener" class="btn-success w-full text-center" style="white-space: normal;">Abrir WhatsApp con el mensaje listo</a>
            <p class="text-sm text-gray-600">El bot le contesta en menos de 2 minutos. Si no le contesta, espere un día y vuelva a intentarlo.</p>
        </div>
    </li>
    <li class="border border-gray-200 rounded-lg overflow-hidden">
        <img src="{{ $imagen('paso-3-enlace.png') }}" width="600" height="1000" loading="lazy"
             alt="Mensaje de respuesta del bot con la clave resaltada y el botón Copiar" class="w-full max-w-xs mx-auto">
        <div class="p-4 space-y-2">
            <h4 class="text-lg font-semibold text-gray-900">Paso 3. Copie la respuesta del bot</h4>
            <p class="text-base text-gray-700">El bot le responde con un mensaje que dice <strong>Your APIKEY is</strong> y un número (su clave). Deje el dedo sobre ese mensaje y toque <strong>Copiar</strong>.</p>
            <p class="text-base text-gray-700">Después péguelo en el cuadro de aquí abajo.</p>
            <p class="text-sm text-gray-600">Si el bot le dice «You already have an apikey», no se preocupe: ese mensaje también trae su clave (y a veces un enlace largo) y sirve igual.</p>
        </div>
    </li>
</ol>

@if($telefono === null)
<div class="mt-6 p-4 rounded-lg bg-amber-50 border border-amber-300 text-base text-amber-900" role="alert">
    {{ $bolsa->first('telefono') ?: 'No tenemos un teléfono válido registrado a su nombre, y WhatsApp necesita el mismo número con el que usted activa el bot. Pídale '.($tipo === 'persona' ? 'a la administración' : 'al administrador (Gestionar Usuarios)').' que lo escriba (8 dígitos, por ejemplo 8888-7777) y vuelva a intentarlo.' }}
</div>
@else
<form method="POST" action="{{ route($rutas['guardar']) }}" class="mt-6 space-y-4" autocomplete="off">
    @csrf
    @if($volver !== '')<input type="hidden" name="volver" value="{{ $volver }}">@endif
    <div>
        <label for="whatsapp-enlace" class="block text-lg font-semibold text-gray-900">Pegue aquí el mensaje que le respondió el bot</label>
        <textarea id="whatsapp-enlace" name="enlace" rows="4" required spellcheck="false" autocomplete="off" autocapitalize="off"
                  placeholder="https://api.callmebot.com/whatsapp.php?phone=...&apikey=..."
                  class="mt-2 w-full rounded-md border-gray-300 shadow-sm text-base {{ $bolsa->has('enlace') ? 'border-red-500' : '' }}"></textarea>
        @if($bolsa->has('enlace'))
        <p class="mt-2 text-base text-red-700" role="alert">{{ $bolsa->first('enlace') }}</p>
        @endif
        <p class="mt-2 text-sm text-gray-600">Debe ser el enlace del número <strong>{{ $telefonoFormateado }}</strong>, que es el que {{ $tipo === 'persona' ? 'la iglesia tiene registrado para usted' : 'tiene registrado su usuario' }}. También puede pegar solo la clave (el número de 6 o 7 cifras que dice «Your APIKEY is»).</p>
    </div>

    <div>
        <label class="flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="acepta" value="1" {{ old('acepta') ? 'checked' : '' }}
                   class="mt-1 h-6 w-6 shrink-0 rounded border-gray-400 text-green-700">
            <span class="text-base text-gray-800">{{ $consentimiento }}</span>
        </label>
        @if($bolsa->has('acepta'))
        <p class="mt-2 text-base text-red-700" role="alert">{{ $bolsa->first('acepta') }}</p>
        @endif
    </div>

    <button type="submit" class="btn-success w-full sm:w-auto">Activar WhatsApp</button>
</form>
@endif
