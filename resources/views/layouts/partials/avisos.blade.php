{{-- Mensajes del sistema: siempre arriba del contenido, que es donde cae la pantalla despues de guardar --}}
@php
    $avisos = [
        'success' => ['alert-success', 'text-green-600', 'ok', 'Listo'],
        'error' => ['alert-error', 'text-red-600', 'error', 'Error'],
        'warning' => ['alert-warning', 'text-yellow-600', 'alerta', 'Atencion'],
        'info' => ['alert-info', 'text-blue-600', 'info', 'Aviso'],
    ];
@endphp
@foreach($avisos as $clave => [$claseAviso, $colorIcono, $iconoAviso, $tituloAviso])
    @if(session($clave))
        <div class="{{ $claseAviso }} mb-6 flex items-start gap-3 animate-slide-in-right" role="{{ $clave === 'error' ? 'alert' : 'status' }}" data-aviso>
            <x-icono :nombre="$iconoAviso" class="mt-0.5 h-5 w-5 flex-shrink-0 {{ $colorIcono }}" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">{{ $tituloAviso }}</p>
                <p class="text-sm">{{ session($clave) }}</p>
            </div>
            <button type="button" class="btn-icon -my-2 -mr-3 text-current opacity-70 hover:bg-black/5 hover:opacity-100" onclick="this.closest('[data-aviso]').remove()" aria-label="Cerrar mensaje">
                <x-icono nombre="cerrar" class="h-5 w-5" />
            </button>
        </div>
    @endif
@endforeach

@if($errors->any())
    <div class="alert-error mb-6 flex items-start gap-3 animate-slide-in-right" role="alert" data-aviso>
        <x-icono nombre="error" class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-600" />
        <div class="min-w-0 flex-1">
            <p class="font-semibold">No se pudo guardar. Revisa estos datos:</p>
            <ul class="mt-1 list-inside list-disc space-y-0.5 text-sm">
                @foreach($errors->all() as $mensajeError)
                    <li>{{ $mensajeError }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="btn-icon -my-2 -mr-3 text-current opacity-70 hover:bg-black/5 hover:opacity-100" onclick="this.closest('[data-aviso]').remove()" aria-label="Cerrar mensaje">
            <x-icono nombre="cerrar" class="h-5 w-5" />
        </button>
    </div>
@endif
