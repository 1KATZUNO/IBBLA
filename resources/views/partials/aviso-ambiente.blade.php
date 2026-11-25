{{-- Franja del ambiente de pruebas (QA). Solo se ve cuando APP_ENV=qa; en produccion y local no pinta nada.
     Se incluye con una linea desde layouts/admin, guest y super-admin. Ver docs/AMBIENTE-QA.md --}}
@if(app()->environment('qa'))
    <div class="shrink-0 border-b border-warning-600 bg-warning-400 px-4 py-1.5 text-center text-sm font-bold text-warning-950" role="status" data-aviso-ambiente>
        AMBIENTE DE PRUEBAS · los datos no son reales
    </div>
@endif
