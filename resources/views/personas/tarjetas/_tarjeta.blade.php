{{-- Contenido de la tarjeta flotante de Personas (Tarjetas con QR de acceso). Lo pide la pantalla con fetch y lo
     vuelve a pedir tras cada accion; sin JavaScript los formularios funcionan igual (vuelven a Personas). --}}
@php
    $logo = 'data:image/png;base64,'.$marca['logoBase64'];
@endphp
<div data-tarjeta-persona="{{ $persona->id }}">
    {{-- La tarjeta: el mismo diseño que el PDF --}}
    <div class="mx-auto w-full max-w-[440px] overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-gray-200">
        <div class="flex items-center gap-3 bg-brand-800 px-4 py-3">
            <img src="{{ $logo }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-contain">
            <div class="min-w-0">
                <p class="text-base font-bold leading-tight text-white">{{ $marca['siglas'] }}</p>
                <p class="truncate text-[11px] leading-tight text-blue-100">{{ $marca['nombre'] }}</p>
            </div>
        </div>
        <div class="h-1 bg-brand-600"></div>
        <div class="flex items-center gap-2 py-3 pl-5 pr-2">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-widest text-brand-600">Tarjeta de acceso</p>
                <p class="mt-1 break-words text-xl font-bold leading-tight text-gray-900">{{ $tarjeta['nombre'] }}</p>
                @if ($tarjeta['clase'])
                    <p class="mt-2 text-[11px] text-gray-500">Clase</p>
                    <p class="text-sm font-semibold text-gray-700">{{ $tarjeta['clase'] }}</p>
                @endif
                <p class="mt-4 border-l-2 border-brand-600 pl-2 text-xs font-semibold leading-snug text-gray-900">Escanea para entrar<br>a tu cuenta</p>
            </div>
            <div class="aspect-square w-36 shrink-0 sm:w-40">
                {!! \App\Support\Qr\MatrizQr::svg($tarjeta['url'], '#111827', 'Código QR de acceso de '.$tarjeta['nombre']) !!}
            </div>
        </div>
    </div>

    {{-- Estado de la cuenta --}}
    <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Cuenta</p>
        @if (! $cuenta['tiene'])
            <p class="mt-1"><span class="badge-neutral">Sin cuenta</span></p>
            <p class="mt-1 text-gray-600">Al escanear la tarjeta, {{ $persona->nombre }} crea su usuario o correo y su contraseña.</p>
        @else
            <p class="mt-1 flex flex-wrap items-center gap-2">
                @if ($cuenta['debeCrear'])
                    <span class="badge-warning">Debe crear contraseña nueva</span>
                @else
                    <span class="badge-success">Con cuenta</span>
                @endif
                <span class="text-gray-700">{{ $cuenta['tipo'] === 'correo' ? 'Correo' : 'Usuario' }}: <strong class="break-all">{{ $cuenta['identificador'] }}</strong></span>
            </p>
            @if (! $cuenta['esMiembro'])
                <p class="mt-1 text-gray-600">Es una cuenta de {{ $cuenta['rol'] }}: su contraseña no se puede ver ni restablecer desde aquí. Al escanear, la tarjeta le pide su contraseña.</p>
            @elseif ($cuenta['debeCrear'])
                <p class="mt-1 text-gray-600">La próxima vez que escanee la tarjeta va a crear una contraseña nueva.</p>
            @endif
        @endif
    </div>

    <div data-tarjeta-aviso role="status" aria-live="polite" class="mt-3 hidden"></div>

    {{-- Acciones --}}
    <div class="mt-4 grid gap-2 sm:grid-cols-2">
        <a href="{{ route('personas.tarjeta.pdf', $persona) }}" target="_blank" rel="noopener" class="btn-secondary w-full">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Descargar tarjeta (PDF)
        </a>

        @if ($contrasenaVisibleActiva && $cuenta['tiene'] && $cuenta['esMiembro'])
            <form method="POST" action="{{ route('personas.tarjeta.contrasena', $persona) }}" data-tarjeta-accion="ver-contrasena">
                @csrf
                @if ($cuenta['hayCopia'])
                    <button type="submit" class="btn-secondary w-full">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Ver contraseña
                    </button>
                @else
                    <button type="button" class="btn-secondary w-full" disabled title="No hay copia de esta contraseña: se creó antes de esta función o se cambió por correo. Usa «Restablecer acceso».">
                        Ver contraseña
                    </button>
                @endif
            </form>
        @endif
    </div>

    <div data-tarjeta-contrasena class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 p-3">
        <p class="text-xs text-amber-800">Contraseña de {{ $cuenta['identificador'] }} (la consulta quedó en la auditoría):</p>
        <div class="mt-1 flex items-center justify-between gap-2">
            <code data-tarjeta-valor class="break-all font-mono text-lg font-semibold text-gray-900"></code>
            <button type="button" data-tarjeta-ocultar class="btn-ghost">Ocultar</button>
        </div>
    </div>

    <div class="mt-4 space-y-2 border-t border-gray-100 pt-4">
        @if ($cuenta['tiene'] && $cuenta['esMiembro'])
            <details class="rounded-lg border border-amber-200">
                <summary class="flex min-h-[44px] cursor-pointer select-none items-center gap-2 px-3 text-sm font-semibold text-amber-800">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Restablecer acceso
                </summary>
                <form method="POST" action="{{ route('personas.tarjeta.restablecer', $persona) }}" data-tarjeta-accion="restablecer" class="px-3 pb-3">
                    @csrf
                    <p class="text-sm text-gray-700">Se borra la contraseña de <strong>{{ $cuenta['identificador'] }}</strong> y se cierran sus sesiones. La próxima vez que escanee la tarjeta, {{ $persona->nombre }} crea una contraseña nueva. Es la forma recomendada cuando alguien la olvida.</p>
                    <button type="submit" class="btn-warning mt-2 w-full sm:w-auto">Sí, restablecer acceso</button>
                </form>
            </details>
        @endif

        <details class="rounded-lg border border-red-200">
            <summary class="flex min-h-[44px] cursor-pointer select-none items-center gap-2 px-3 text-sm font-semibold text-red-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                Generar QR nuevo
            </summary>
            <form method="POST" action="{{ route('personas.tarjeta.nuevo-qr', $persona) }}" data-tarjeta-accion="nuevo-qr" class="px-3 pb-3">
                @csrf
                <p class="text-sm text-gray-700">La tarjeta que {{ $persona->nombre }} tiene ahora deja de servir. Úsalo si la perdió o si alguien más la tiene. Su cuenta y su contraseña no cambian.</p>
                <button type="submit" class="btn-danger mt-2 w-full sm:w-auto">Sí, generar QR nuevo</button>
            </form>
        </details>
    </div>
</div>
