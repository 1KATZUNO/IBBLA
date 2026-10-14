{{-- Aviso del culto cerrado: quien lo cerro, que se puede hacer y quien lo puede corregir --}}
@if($culto->cerrado)
    @php
        $cerradoTexto = 'Cerrado'
            .($culto->cerrado_at ? ' el '.$culto->cerrado_at->format('d/m/Y \a \l\a\s H:i') : '')
            .($culto->cerradoPor ? ' por '.$culto->cerradoPor->name : '');
    @endphp
    @if(! $puedeEditar)
        <div class="flex items-start gap-3 rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-gray-800" role="status">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-gray-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
            <div class="text-sm">
                <p class="font-semibold">{{ $cerradoTexto }} · solo lectura</p>
                <p>Puedes ver todo y descargar el PDF, pero no agregar ni editar. Si hay que corregir algo, pídele al administrador que lo corrija.</p>
            </div>
        </div>
    @else
        <div class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900" role="status">
            <svg class="w-5 h-5 mt-0.5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div class="text-sm">
                <p class="font-semibold">{{ $cerradoTexto }} · lo estás corrigiendo como administrador</p>
                <p>Cada cambio recalcula los totales con el tipo de cambio congelado al cerrar. Los tesoreros lo ven en solo lectura.</p>
            </div>
        </div>
    @endif
@endif
