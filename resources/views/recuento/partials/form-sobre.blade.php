{{--
    Campos de un sobre, compartidos por "Registrar sobre" y "Editar sobre" (flujo en
    docs/plan-oct-2026/FLUJO-SOBRES.md): persona por buscador, montos con teclado numerico,
    pago y moneda en dos toques, nota opcional. Va dentro del <form id="sobreForm">.
--}}
@php
    $editando = isset($sobre) && $sobre;
    $personaId = old('persona_id', $editando ? $sobre->persona_id : null);
    $personaElegida = $personaId ? $personas->firstWhere('id', (int) $personaId) : null;
    $metodo = old('metodo_pago', $editando ? $sobre->metodo_pago : 'efectivo');
    $moneda = old('moneda', $editando ? ($sobre->moneda ?? 'CRC') : 'CRC');
    $simbolo = $moneda === 'USD' ? '$' : '₡';
    $detallesActuales = $editando ? $sobre->detalles->keyBy('categoria') : collect();
    $notas = old('notas', $editando ? $sobre->notas : '');
    $campo = 'w-full min-h-[44px] rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500';
    $opcion = 'flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-lg border text-sm font-semibold cursor-pointer select-none border-gray-300 bg-white text-gray-700 hover:bg-gray-50 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-800 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500';
@endphp

{{-- 1. Persona --}}
<section aria-labelledby="paso-persona" class="space-y-2">
    <h3 id="paso-persona" class="flex items-center gap-2 text-sm font-semibold text-gray-800">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">1</span>
        Persona
    </h3>
    <input type="hidden" name="persona_id" id="persona_id" value="{{ $personaId }}">
    <div class="flex flex-wrap gap-2">
        <div class="relative flex-1 min-w-[14rem]">
            <label for="buscarPersona" class="sr-only">Buscar persona por nombre o PIN</label>
            <input type="search" id="buscarPersona" autocomplete="off" spellcheck="false" enterkeyhint="next"
                   placeholder="Escribe el nombre o el PIN" value="{{ $personaElegida['nombre'] ?? '' }}"
                   role="combobox" aria-expanded="false" aria-controls="resultadosPersona" aria-autocomplete="list"
                   class="{{ $campo }}" @if(! $editando) autofocus @endif>
            <ul id="resultadosPersona" role="listbox" aria-label="Personas"
                class="hidden absolute z-30 mt-1 w-full max-h-72 overflow-y-auto rounded-md border border-gray-200 bg-white py-1 shadow-lg"></ul>
        </div>
        <button type="button" class="btn-secondary" id="btnAnonimo">Anónimo</button>
        <button type="button" class="btn-secondary" id="btnPersonaNueva">+ Nueva</button>
    </div>
    <p class="text-sm text-gray-600" id="personaEstado" aria-live="polite">
        @if($personaElegida)
            Sobre de <strong class="text-gray-900">{{ $personaElegida['nombre'] }}</strong>
            @if($personaElegida['inactiva'] ?? false)
                <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">persona inactiva</span>
            @endif
        @else
            Sin persona: se guarda como <strong class="text-gray-900">Anónimo</strong>
        @endif
    </p>
    <div id="cajaPersonaNueva" class="hidden rounded-lg border border-green-200 bg-green-50 p-3">
        <label for="nombrePersonaNueva" class="block text-sm font-medium text-gray-800 mb-1">Nombre completo de la persona nueva</label>
        <div class="flex flex-wrap gap-2">
            <input type="text" id="nombrePersonaNueva" autocomplete="off" class="{{ $campo }} flex-1 min-w-[12rem]" placeholder="Ej.: Juan Pérez Gómez">
            <button type="button" class="btn-success" id="btnGuardarPersonaNueva">Agregar</button>
            <button type="button" class="btn-secondary" id="btnCancelarPersonaNueva">Cancelar</button>
        </div>
        <p class="mt-1 text-xs text-gray-600">Queda activa y sin usuario. Sus datos se completan después en Personas.</p>
    </div>
    @error('persona_id')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</section>

{{-- 2. Montos --}}
<section aria-labelledby="paso-montos" class="space-y-2 border-t border-gray-100 pt-5">
    <h3 id="paso-montos" class="flex items-center gap-2 text-sm font-semibold text-gray-800">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">2</span>
        Montos
        <span class="font-normal text-gray-500">(deja vacío lo que no trae)</span>
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        @foreach($categorias as $indice => $cat)
            @php
                $valor = old('detalles.'.$indice.'.monto', $detallesActuales->get($cat->slug)?->monto);
                $valor = (float) $valor > 0 ? $valor : '';
            @endphp
            <div>
                <label for="monto_{{ $cat->slug }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $cat->nombre }}</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 simbolo-moneda" aria-hidden="true">{{ $simbolo }}</span>
                    <input type="number" name="detalles[{{ $indice }}][monto]" id="monto_{{ $cat->slug }}" value="{{ $valor }}"
                           step="0.01" min="0" inputmode="decimal" enterkeyhint="go" placeholder="0"
                           class="{{ $campo }} pl-7 tabular-nums detalle-monto">
                    <input type="hidden" name="detalles[{{ $indice }}][categoria]" value="{{ $cat->slug }}">
                </div>
            </div>
        @endforeach
    </div>
    @error('detalles')
        <p class="text-sm text-red-600">{{ $message }}</p>
    @enderror
</section>

{{-- 3. Pago y moneda --}}
<section aria-labelledby="paso-pago" class="space-y-3 border-t border-gray-100 pt-5">
    <h3 id="paso-pago" class="flex items-center gap-2 text-sm font-semibold text-gray-800">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs text-white">3</span>
        Pago y moneda
    </h3>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <fieldset>
            <legend class="block text-sm font-medium text-gray-700 mb-1">Método de pago</legend>
            <div class="grid grid-cols-2 gap-2">
                <label class="{{ $opcion }}"><input type="radio" name="metodo_pago" value="efectivo" class="sr-only" @checked($metodo !== 'transferencia')> Efectivo</label>
                <label class="{{ $opcion }}"><input type="radio" name="metodo_pago" value="transferencia" class="sr-only" @checked($metodo === 'transferencia')> Transferencia</label>
            </div>
            <div id="comprobanteWrapper" class="mt-3 {{ $metodo === 'transferencia' ? '' : 'hidden' }}">
                <label for="comprobante_numero" class="block text-sm font-medium text-gray-700 mb-1">N° de comprobante</label>
                <input type="text" name="comprobante_numero" id="comprobante_numero" inputmode="numeric" autocomplete="off"
                       value="{{ old('comprobante_numero', $editando ? $sobre->comprobante_numero : '') }}"
                       class="{{ $campo }}" placeholder="Ej.: 1234567890" @required($metodo === 'transferencia')>
                @error('comprobante_numero')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </fieldset>
        <fieldset>
            <legend class="block text-sm font-medium text-gray-700 mb-1">Moneda</legend>
            <div class="grid grid-cols-2 gap-2">
                <label class="{{ $opcion }}"><input type="radio" name="moneda" value="CRC" class="sr-only" @checked($moneda !== 'USD')> ₡ Colones</label>
                <label class="{{ $opcion }}"><input type="radio" name="moneda" value="USD" class="sr-only" @checked($moneda === 'USD')> $ Dólares</label>
            </div>
            <p id="tipoCambioNota" class="mt-2 text-xs text-gray-500 {{ $moneda === 'USD' ? '' : 'hidden' }}">
                Se convierte con el tipo de cambio de venta del día: <span id="tipoCambioValor">{{ $editando && $sobre->tipo_cambio_venta ? '₡'.number_format((float) $sobre->tipo_cambio_venta, 2) : 'consultando…' }}</span>
            </p>
        </fieldset>
    </div>
</section>

{{-- Nota opcional, plegada para no estorbar --}}
<details class="border-t border-gray-100 pt-4" @if($notas) open @endif>
    <summary class="inline-flex items-center min-h-[44px] cursor-pointer text-sm font-medium text-blue-700 hover:text-blue-900">+ Agregar nota</summary>
    <label for="notas" class="sr-only">Nota del sobre</label>
    <textarea name="notas" id="notas" rows="2" class="mt-2 w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $notas }}</textarea>
</details>

@push('scripts')
<script>
(function () {
    const formulario = document.getElementById('sobreForm');
    const personas = @json($personas);
    const campoId = document.getElementById('persona_id');
    const buscador = document.getElementById('buscarPersona');
    const lista = document.getElementById('resultadosPersona');
    const estado = document.getElementById('personaEstado');
    const montos = Array.from(document.querySelectorAll('.detalle-monto'));
    const primerMonto = montos[0];
    let resultados = [];
    let marcado = -1;

    const normalizar = (t) => (t || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
    const escapar = (t) => (t || '').toString().replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    function pintarEstado(persona) {
        estado.innerHTML = persona
            ? 'Sobre de <strong class="text-gray-900">' + escapar(persona.nombre) + '</strong>'
                + (persona.inactiva ? ' <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">persona inactiva</span>' : '')
            : 'Sin persona: se guarda como <strong class="text-gray-900">Anónimo</strong>';
    }
    function cerrarLista() {
        lista.classList.add('hidden');
        buscador.setAttribute('aria-expanded', 'false');
        buscador.removeAttribute('aria-activedescendant');
        marcado = -1;
    }
    function elegir(persona) {
        campoId.value = persona ? persona.id : '';
        buscador.value = persona ? persona.nombre : '';
        pintarEstado(persona);
        cerrarLista();
        primerMonto && primerMonto.focus();
    }
    function marcar(indice) {
        marcado = indice;
        lista.querySelectorAll('[role="option"]').forEach(function (op, i) {
            op.setAttribute('aria-selected', i === indice ? 'true' : 'false');
            op.classList.toggle('bg-blue-50', i === indice);
            if (i === indice) { buscador.setAttribute('aria-activedescendant', op.id); op.scrollIntoView({ block: 'nearest' }); }
        });
    }
    function buscar() {
        const texto = normalizar(buscador.value);
        // Si se edita el texto despues de elegir, la eleccion se suelta hasta elegir otra vez
        const elegida = personas.find(p => String(p.id) === campoId.value);
        if (elegida && normalizar(elegida.nombre) !== texto) { campoId.value = ''; pintarEstado(null); }
        if (texto === '') { cerrarLista(); return; }

        resultados = personas.filter(p => normalizar(p.nombre).includes(texto) || (p.pin && String(p.pin) === texto)).slice(0, 8);
        lista.innerHTML = resultados.map((p, i) =>
            '<li id="opcion-persona-' + i + '" role="option" aria-selected="false" data-indice="' + i + '" class="flex items-center justify-between gap-3 min-h-[44px] px-3 py-2 cursor-pointer hover:bg-blue-50">' +
            '<span class="text-sm text-gray-900">' + escapar(p.nombre) + (p.inactiva ? ' <span class="text-xs text-gray-500">(inactiva)</span>' : '') + '</span>' +
            (p.pin ? '<span class="text-xs text-gray-500">PIN ' + escapar(p.pin) + '</span>' : '') + '</li>'
        ).join('') + '<li role="option" aria-selected="false" data-nueva="1" id="opcion-persona-nueva" class="flex items-center min-h-[44px] px-3 py-2 cursor-pointer text-sm font-medium text-green-800 hover:bg-green-50">+ Crear «' + escapar(buscador.value.trim()) + '» como persona nueva</li>';
        lista.classList.remove('hidden');
        buscador.setAttribute('aria-expanded', 'true');
        marcar(resultados.length ? 0 : -1);
    }

    buscador.addEventListener('input', buscar);
    buscador.addEventListener('keydown', function (e) {
        const opciones = lista.querySelectorAll('[role="option"]');
        if (e.key === 'ArrowDown' && !lista.classList.contains('hidden')) { e.preventDefault(); marcar(Math.min(marcado + 1, opciones.length - 1)); }
        else if (e.key === 'ArrowUp' && !lista.classList.contains('hidden')) { e.preventDefault(); marcar(Math.max(marcado - 1, 0)); }
        else if (e.key === 'Escape') { cerrarLista(); }
        else if (e.key === 'Enter') {
            // Enter nunca guarda desde aqui: elige a la persona marcada y pasa a los montos
            e.preventDefault();
            const opcion = opciones[marcado];
            if (!lista.classList.contains('hidden') && opcion) {
                opcion.dataset.nueva ? abrirPersonaNueva(buscador.value.trim()) : elegir(resultados[Number(opcion.dataset.indice)]);
            } else {
                cerrarLista();
                primerMonto && primerMonto.focus();
            }
        }
    });
    lista.addEventListener('mousedown', function (e) {
        const opcion = e.target.closest('[role="option"]');
        if (!opcion) return;
        e.preventDefault();
        opcion.dataset.nueva ? abrirPersonaNueva(buscador.value.trim()) : elegir(resultados[Number(opcion.dataset.indice)]);
    });
    buscador.addEventListener('blur', function () { setTimeout(cerrarLista, 150); });
    document.getElementById('btnAnonimo').addEventListener('click', function () { elegir(null); });

    // Persona nueva sin salir del sobre
    const cajaNueva = document.getElementById('cajaPersonaNueva');
    const nombreNueva = document.getElementById('nombrePersonaNueva');
    const guardarNueva = document.getElementById('btnGuardarPersonaNueva');
    function abrirPersonaNueva(nombre) {
        cerrarLista();
        cajaNueva.classList.remove('hidden');
        nombreNueva.value = nombre || '';
        nombreNueva.focus();
    }
    document.getElementById('btnPersonaNueva').addEventListener('click', function () { abrirPersonaNueva(buscador.value.trim()); });
    document.getElementById('btnCancelarPersonaNueva').addEventListener('click', function () { cajaNueva.classList.add('hidden'); buscador.focus(); });
    nombreNueva.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); guardarNueva.click(); } });
    guardarNueva.addEventListener('click', function () {
        const nombre = nombreNueva.value.trim();
        if (!nombre) { nombreNueva.focus(); return; }
        guardarNueva.disabled = true;
        fetch(@json(route('personas.quick-store')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
            body: JSON.stringify({ nombre: nombre })
        }).then(r => r.ok ? r.json() : Promise.reject(r)).then(function (datos) {
            const persona = { id: datos.persona.id, nombre: datos.persona.nombre, pin: datos.persona.pin || null };
            personas.push(persona);
            cajaNueva.classList.add('hidden');
            elegir(persona);
        }).catch(function () {
            alert('No se pudo agregar la persona. Revisa la conexión e intenta de nuevo.');
        }).finally(function () { guardarNueva.disabled = false; });
    });

    // Total en vivo, simbolo de moneda y conversion
    const radiosMoneda = document.querySelectorAll('input[name="moneda"]');
    const nota = document.getElementById('tipoCambioNota');
    let tipoCambio = {{ $editando && $sobre->tipo_cambio_venta ? (float) $sobre->tipo_cambio_venta : 0 }};
    const formato = (n) => n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    function moneda() { return document.querySelector('input[name="moneda"]:checked')?.value || 'CRC'; }
    function calcularTotal() {
        const total = montos.reduce((suma, m) => suma + (parseFloat(m.value) || 0), 0);
        const simbolo = moneda() === 'USD' ? '$' : '₡';
        document.querySelectorAll('.simbolo-moneda').forEach(s => s.textContent = simbolo);
        document.getElementById('totalBarra').textContent = simbolo + formato(total);
        const convertido = document.getElementById('totalConvertido');
        if (moneda() === 'USD' && tipoCambio > 0) {
            convertido.textContent = '≈ ₡' + formato(total * tipoCambio);
            convertido.classList.remove('hidden');
        } else {
            convertido.classList.add('hidden');
        }
    }
    montos.forEach(m => m.addEventListener('input', calcularTotal));
    radiosMoneda.forEach(r => r.addEventListener('change', function () {
        nota.classList.toggle('hidden', moneda() !== 'USD');
        calcularTotal();
    }));
    fetch(@json(route('api.tipo-cambio')), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(function (d) {
            if (d.disponible) { tipoCambio = d.venta; document.getElementById('tipoCambioValor').textContent = '₡' + formato(d.venta); }
            else { document.getElementById('tipoCambioValor').textContent = 'no disponible (se guarda sin convertir)'; }
            calcularTotal();
        }).catch(() => {});
    calcularTotal();

    // Transferencia pide comprobante
    const comprobante = document.getElementById('comprobante_numero');
    document.querySelectorAll('input[name="metodo_pago"]').forEach(r => r.addEventListener('change', function () {
        const esTransferencia = document.querySelector('input[name="metodo_pago"]:checked').value === 'transferencia';
        document.getElementById('comprobanteWrapper').classList.toggle('hidden', !esTransferencia);
        comprobante.required = esTransferencia;
        if (esTransferencia) comprobante.focus();
    }));

    // Enter en un monto o en el comprobante = boton principal (Guardar y siguiente / Guardar cambios)
    const principal = document.getElementById('btnPrincipalSobre');
    formulario.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target === buscador || e.target === nombreNueva) return;
        if (e.target.matches('.detalle-monto, #comprobante_numero')) {
            e.preventDefault();
            formulario.requestSubmit ? formulario.requestSubmit(principal) : principal.click();
        }
    });

    // Un solo envio por formulario (el servidor ademas ignora el mismo codigo de envio dos veces)
    let enviando = false;
    formulario.addEventListener('submit', function (e) {
        if (enviando) { e.preventDefault(); return; }
        enviando = true;
        formulario.querySelectorAll('[data-boton-guardar]').forEach(function (b) {
            b.setAttribute('aria-disabled', 'true');
            b.classList.add('opacity-60', 'pointer-events-none');
        });
        (e.submitter || principal).textContent = 'Guardando…';
    });
})();
</script>
@endpush
