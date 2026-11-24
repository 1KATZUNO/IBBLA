@if($visible)
@php
    // $errors solo existe dentro de una petición web: fuera de ella (otra vista, una prueba) la tarjeta igual se dibuja.
    $bolsa = isset($errors) ? $errors->getBag('whatsapp') : new \Illuminate\Support\MessageBag();
    $aviso = session('whatsapp_aviso');
    $esPersona = $tipo === 'persona';
    $verificada = $activacion?->verificado_at !== null;

    // [texto, clases de la etiqueta]
    $etiqueta = match (true) {
        $situacion === 'activa' && $verificada => ['Activado', 'bg-green-100 text-green-800'],
        $situacion === 'activa' => ['Activado, falta la prueba', 'bg-amber-100 text-amber-800'],
        $situacion === 'numero_cambio' => ['Cambió su número', 'bg-amber-100 text-amber-800'],
        $situacion === 'ilegible' => ['Hay que activarlo de nuevo', 'bg-red-100 text-red-800'],
        $situacion === 'desactivada' => ['Desactivado', 'bg-gray-100 text-gray-700'],
        default => ['Sin activar', 'bg-gray-100 text-gray-700'],
    };
@endphp
<section id="whatsapp" class="bg-white rounded-lg shadow" style="scroll-margin-top: 6rem;">
    <div class="px-4 sm:px-6 py-4 border-b border-gray-200 flex flex-wrap items-center gap-3">
        <svg class="w-7 h-7 text-green-600 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <h3 class="text-xl font-semibold text-gray-900">Avisos por WhatsApp</h3>
        <span class="px-3 py-1 text-sm font-semibold rounded-full {{ $etiqueta[1] }}">{{ $etiqueta[0] }}</span>
    </div>

    <div class="p-4 sm:p-6 space-y-6">
        @if($aviso)
        <div class="p-4 rounded-lg border text-base {{ $aviso['exito'] ? 'bg-green-50 border-green-300 text-green-900' : 'bg-red-50 border-red-300 text-red-900' }}" role="{{ $aviso['exito'] ? 'status' : 'alert' }}">
            {{ $aviso['texto'] }}
        </div>
        @endif

        <p class="text-base text-gray-700">
            @if($esPersona)
                Reciba su estado de cuenta y los avisos de la iglesia directamente en su WhatsApp. Se activa una sola vez, en tres pasos, y es gratis.
            @else
                Reciba en su WhatsApp los avisos del sistema (cuando se crea un culto o un culto llega a los {{ (int) config('whatsapp.avisos.sobres_umbral', 10) }} sobres). Se activa una sola vez, en tres pasos, y es gratis.
            @endif
            El envío lo hace <a href="{{ $ayudaUrl }}" target="_blank" rel="noopener" class="underline text-blue-700">CallMeBot</a>, un servicio gratuito de un tercero que puede fallar:
            si falla, {{ $esPersona ? 'la iglesia le enviará el mensaje a mano' : 'el aviso queda en el panel para enviarlo a mano' }}.
        </p>

        @if($situacion === 'numero_cambio')
        <div class="p-4 rounded-lg bg-amber-50 border border-amber-300 text-base text-amber-900" role="alert">
            Su teléfono registrado cambió después de activar WhatsApp{{ $telefono ? ' (ahora es '.$telefonoFormateado.')' : '' }}, así que la clave anterior ya no sirve.
            Para seguir recibiendo mensajes active el bot con su número actual y pegue el enlace nuevo.
        </div>
        @elseif($situacion === 'ilegible')
        <div class="p-4 rounded-lg bg-red-50 border border-red-300 text-base text-red-900" role="alert">
            No pudimos leer la clave que tenía guardada. No pasa nada: pegue de nuevo el mensaje del bot (paso 3) y vuelva a aceptar.
        </div>
        @elseif($situacion === 'desactivada' && $activacion?->revocado_at)
        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200 text-base text-gray-800">
            Desactivó los avisos por WhatsApp el {{ $activacion->revocado_at->format('d/m/Y') }} y borramos su clave. Si quiere volver a recibirlos, siga los pasos.
        </div>
        @endif

        @if($estaActiva())
            <div class="p-4 rounded-lg bg-green-50 border border-green-200 space-y-2">
                <p class="text-lg text-green-900">
                    Le enviaremos {{ $esPersona ? 'su estado de cuenta y los avisos de la iglesia' : 'los avisos del sistema' }} al <strong>{{ $telefonoFormateado }}</strong>.
                </p>
                <ul class="text-base text-green-900 space-y-1">
                    <li>Activado el {{ $activacion->consentimiento_at?->format('d/m/Y') }}. Su clave está guardada y no se vuelve a mostrar.</li>
                    <li>
                        @if($verificada)
                            Prueba hecha el {{ $activacion->verificado_at->format('d/m/Y') }}: le llegó.
                        @else
                            Todavía falta la prueba: pulse «Enviar mensaje de prueba» y confirme que le llega.
                        @endif
                    </li>
                </ul>
                @if($activacion->ultimo_error)
                <p class="text-base text-red-800" role="alert">
                    El último envío falló ({{ $activacion->ultimo_envio_at?->format('d/m/Y H:i') }}): {{ $activacion->ultimo_error }}
                    Si el problema sigue, pegue el enlace de nuevo.
                </p>
                @endif
            </div>

            @if(! $verificada)
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                <img src="{{ asset('images/manual/whatsapp/paso-4-prueba.png') }}" width="600" height="1000" loading="lazy"
                     alt="Mensaje de prueba recibido en WhatsApp" class="w-full max-w-xs mx-auto border border-gray-200 rounded-lg">
                <p class="text-base text-gray-700">Así se verá el mensaje de prueba cuando todo salga bien. Pulse el botón verde y abra su WhatsApp.</p>
            </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route($rutas['probar']) }}" onsubmit="this.querySelector('button').disabled = true;">
                    @csrf
                    @if($volver !== '')<input type="hidden" name="volver" value="{{ $volver }}">@endif
                    <button type="submit" class="btn-success">Enviar mensaje de prueba</button>
                </form>
                <form method="POST" action="{{ route($rutas['desactivar']) }}" onsubmit="return confirm('¿Desactivar los avisos por WhatsApp? Borraremos su clave y dejará de recibirlos.');">
                    @csrf
                    @method('DELETE')
                    @if($volver !== '')<input type="hidden" name="volver" value="{{ $volver }}">@endif
                    <button type="submit" class="btn-danger-soft">Desactivar</button>
                </form>
            </div>

            <details class="border-t border-gray-200 pt-4" {{ $bolsa->any() ? 'open' : '' }}>
                <summary class="cursor-pointer text-base text-blue-700 underline">Cambió de teléfono o de clave: pegar un enlace nuevo</summary>
                <div class="mt-4">
                    @include('whatsapp._pasos')
                </div>
            </details>
        @else
            @include('whatsapp._pasos')
        @endif
    </div>
</section>
@endif
