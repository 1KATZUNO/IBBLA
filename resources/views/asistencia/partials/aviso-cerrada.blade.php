{{--
    Aviso de asistencia cerrada (QE-015 / QA-021), con la misma regla que Recuento:
    - quien no la puede corregir la ve en gris, con el motivo (quien y cuando la cerro);
    - el administrador la puede corregir y se le avisa que queda en la auditoria.
    $modo: 'lectura' | 'admin'
--}}
@if($asistencia->cerrado)
    @php $cerradaTexto = \App\Http\Controllers\AsistenciaController::textoCerrada($asistencia); @endphp
    @if($modo === 'admin')
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900" role="status" data-aviso-asistencia="admin">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="text-sm">
                <p class="font-semibold">{{ $cerradaTexto }} · la estás corrigiendo como administrador</p>
                <p>El cambio queda en la auditoría y en Accesos &gt; Últimos cambios. Los asistentes la ven en solo lectura.</p>
            </div>
        </div>
    @else
        <div class="mb-6 flex items-start gap-3 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-800" role="status" data-aviso-asistencia="lectura">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            <div class="text-sm">
                <p class="font-semibold">{{ $cerradaTexto }} · solo lectura</p>
                <p>Puedes ver todas las cifras, pero no cambiarlas. Si hay que corregir algo, pídeselo al administrador.</p>
            </div>
        </div>
    @endif
@endif
