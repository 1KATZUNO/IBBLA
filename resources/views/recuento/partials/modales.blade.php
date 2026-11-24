{{-- Ventanas de la pantalla del culto: una sola de confirmacion para eliminar o cerrar,
     una para dinero suelto / egreso (agregar o editar) y la de firma. --}}
<div id="modalConfirmar" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/50" role="dialog" aria-modal="true" aria-labelledby="modalConfirmarTitulo">
    <div class="relative mx-4 sm:mx-auto mt-16 sm:mt-24 max-w-md rounded-lg bg-white p-5 shadow-xl">
        <h3 id="modalConfirmarTitulo" class="text-lg font-semibold text-gray-900"></h3>
        <p id="modalConfirmarMensaje" class="mt-2 text-sm text-gray-600"></p>
        <div class="mt-5 flex flex-wrap justify-end gap-3">
            <button type="button" class="btn-secondary" data-cerrar-modal>Cancelar</button>
            <button type="button" class="btn-danger" id="modalConfirmarBoton">Sí, eliminar</button>
        </div>
    </div>
</div>

@if($puedeEditar)
<div id="modalMovimiento" class="hidden fixed inset-0 z-50 overflow-y-auto bg-gray-900/50" role="dialog" aria-modal="true" aria-labelledby="modalMovimientoTitulo">
    <div class="relative mx-4 sm:mx-auto mt-6 sm:mt-20 max-w-md rounded-lg bg-white p-5 shadow-xl">
        <h3 id="modalMovimientoTitulo" class="text-lg font-semibold text-gray-900 mb-4">Agregar dinero suelto</h3>
        <form id="formMovimiento" method="POST" action="{{ route('recuento.store-suelto') }}"
              data-crear-suelto="{{ route('recuento.store-suelto') }}"
              data-crear-egreso="{{ route('recuento.store-egreso') }}">
            @csrf
            <input type="hidden" name="_method" value="POST" id="movimientoMetodo">
            <input type="hidden" name="culto_id" value="{{ $culto->id }}">
            <div class="mb-4">
                <label for="movimientoMonto" class="block text-sm font-medium text-gray-700 mb-2">Monto (₡) *</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500">₡</span>
                    <input type="number" name="monto" id="movimientoMonto" min="0.01" step="0.01" required inputmode="decimal"
                           class="w-full min-h-[44px] pl-7 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
            <div class="mb-4">
                <label for="movimientoDescripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                <textarea name="descripcion" id="movimientoDescripcion" rows="2" maxlength="500"
                          class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" class="btn-secondary" data-cerrar-modal>Cancelar</button>
                <button type="submit" class="btn-success" id="movimientoGuardar">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Firmas: se corrigen aunque el culto este cerrado (decision del cliente 13/10) --}}
@if($puedeFirmar)
<div id="modalFirma" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="modalFirmaTitulo">
    <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
        <div class="flex justify-between items-center p-4 border-b">
            <h4 id="modalFirmaTitulo" class="text-lg font-semibold text-gray-800">Firma de: <span id="modalFirmaNombre" class="text-blue-600"></span></h4>
            <button type="button" class="btn-icon" data-cerrar-modal aria-label="Cerrar">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-4">
            <div class="border-2 border-dashed border-gray-300 rounded-lg bg-white mb-4">
                <canvas id="signatureCanvas" class="w-full cursor-crosshair" style="touch-action: none;"></canvas>
            </div>
            <p class="text-sm text-gray-500 text-center">Dibuje su firma con el mouse o el dedo</p>
        </div>
        <div class="flex flex-wrap justify-between gap-2 p-4 border-t bg-gray-50 rounded-b-xl">
            <button type="button" class="btn-secondary" id="firmaLimpiar">Limpiar</button>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" data-cerrar-modal>Cancelar</button>
                <button type="button" class="btn-primary" id="firmaGuardar">Guardar firma</button>
            </div>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
(function () {
    const modales = document.querySelectorAll('[role="dialog"]');
    const abrir = (modal) => modal.classList.remove('hidden');
    const cerrar = (modal) => modal && modal.classList.add('hidden');

    // Confirmacion unica: eliminar sobre / dinero suelto / egreso y cerrar el culto
    const confirmar = document.getElementById('modalConfirmar');
    const botonConfirmar = document.getElementById('modalConfirmarBoton');
    let formularioPendiente = null;

    // Dinero suelto y egresos: la misma ventana para agregar o editar
    const movimiento = document.getElementById('modalMovimiento');
    const formMovimiento = document.getElementById('formMovimiento');

    document.addEventListener('click', function (e) {
        const pideConfirmar = e.target.closest('[data-confirmar]');
        if (pideConfirmar) {
            formularioPendiente = document.querySelector(pideConfirmar.dataset.confirmar);
            document.getElementById('modalConfirmarTitulo').textContent = pideConfirmar.dataset.titulo || 'Confirmar';
            document.getElementById('modalConfirmarMensaje').textContent = pideConfirmar.dataset.mensaje || '';
            botonConfirmar.textContent = pideConfirmar.dataset.boton || 'Confirmar';
            botonConfirmar.className = pideConfirmar.dataset.variante || 'btn-danger';
            botonConfirmar.disabled = false;
            abrir(confirmar);
            botonConfirmar.focus();
            return;
        }

        const pideMovimiento = e.target.closest('[data-abrir-movimiento]');
        if (pideMovimiento && movimiento) {
            const esSuelto = pideMovimiento.dataset.abrirMovimiento === 'suelto';
            const editando = !!pideMovimiento.dataset.accion;
            document.getElementById('modalMovimientoTitulo').textContent =
                (editando ? 'Editar ' : 'Agregar ') + (esSuelto ? 'dinero suelto' : 'egreso');
            formMovimiento.action = editando ? pideMovimiento.dataset.accion
                : (esSuelto ? formMovimiento.dataset.crearSuelto : formMovimiento.dataset.crearEgreso);
            document.getElementById('movimientoMetodo').value = editando ? 'PUT' : 'POST';
            document.getElementById('movimientoMonto').value = pideMovimiento.dataset.monto || '';
            document.getElementById('movimientoDescripcion').value = pideMovimiento.dataset.descripcion || '';
            const guardar = document.getElementById('movimientoGuardar');
            guardar.textContent = editando ? 'Actualizar' : 'Guardar';
            guardar.className = esSuelto ? 'btn-success' : 'btn-danger';
            abrir(movimiento);
            document.getElementById('movimientoMonto').focus();
            return;
        }

        if (e.target.closest('[data-cerrar-modal]')) {
            cerrar(e.target.closest('[role="dialog"]'));
            return;
        }
        // Tocar fuera de la ventana la cierra
        if (e.target.matches('[role="dialog"]')) {
            cerrar(e.target);
        }
        // Menus desplegables (PDF): se cierran al tocar fuera
        document.querySelectorAll('details[data-menu][open]').forEach(function (menu) {
            if (!menu.contains(e.target)) menu.removeAttribute('open');
        });
    });

    botonConfirmar.addEventListener('click', function () {
        if (formularioPendiente) {
            botonConfirmar.disabled = true;
            formularioPendiente.submit();
        }
    });

    formMovimiento?.addEventListener('submit', function () {
        document.getElementById('movimientoGuardar').disabled = true;
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') modales.forEach(cerrar);
    });

    // Buscador de sobres de la tabla
    const buscador = document.querySelector('[data-filtrar-sobres]');
    buscador?.addEventListener('input', function () {
        const texto = buscador.value.trim().toLowerCase();
        let visibles = 0;
        document.querySelectorAll('#tabla-sobres tr[data-buscar]').forEach(function (fila) {
            const coincide = !texto || fila.dataset.buscar.includes(texto);
            fila.classList.toggle('hidden', !coincide);
            if (coincide) visibles++;
        });
        document.querySelector('[data-sin-resultados]')?.classList.toggle('hidden', visibles > 0);
    });

    // Firmas: dibujar en el lienzo y guardar como imagen en el campo oculto
    const modalFirma = document.getElementById('modalFirma');
    const lienzo = document.getElementById('signatureCanvas');
    const contenedorTesoreros = document.getElementById('tesorerosContainer');
    if (!modalFirma || !lienzo) return;

    let ctx = null, dibujando = false, ultimo = null, destino = null;

    function prepararLienzo() {
        lienzo.width = lienzo.parentElement.offsetWidth - 4;
        lienzo.height = 200;
        ctx = lienzo.getContext('2d');
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
    }
    function posicion(e) {
        const r = lienzo.getBoundingClientRect();
        const p = e.touches ? e.touches[0] : e;
        return { x: p.clientX - r.left, y: p.clientY - r.top };
    }
    function empezar(e) { e.preventDefault(); dibujando = true; ultimo = posicion(e); }
    function trazar(e) {
        if (!dibujando) return;
        e.preventDefault();
        const p = posicion(e);
        ctx.beginPath(); ctx.moveTo(ultimo.x, ultimo.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        ultimo = p;
    }
    function terminar() { dibujando = false; }
    lienzo.addEventListener('mousedown', empezar);
    lienzo.addEventListener('mousemove', trazar);
    lienzo.addEventListener('mouseup', terminar);
    lienzo.addEventListener('mouseout', terminar);
    lienzo.addEventListener('touchstart', empezar, { passive: false });
    lienzo.addEventListener('touchmove', trazar, { passive: false });
    lienzo.addEventListener('touchend', terminar);

    function abrirFirma(nombre, campoImagen, vistaPrevia) {
        destino = { campoImagen, vistaPrevia };
        document.getElementById('modalFirmaNombre').textContent = nombre;
        abrir(modalFirma);
        setTimeout(function () {
            prepararLienzo();
            if (campoImagen.value) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, lienzo.width, lienzo.height);
                img.src = campoImagen.value;
            }
        }, 50);
    }

    document.getElementById('firmasForm')?.addEventListener('click', function (e) {
        const firmar = e.target.closest('[data-firmar]');
        if (firmar) {
            if (firmar.dataset.firmar === 'pastor') {
                abrirFirma(document.getElementById('firma_pastor_nombre').value || 'Pastor',
                    document.getElementById('firma_pastor_imagen'), document.getElementById('preview_pastor'));
            } else {
                const fila = firmar.closest('.tesorero-row');
                abrirFirma(fila.querySelector('.tesorero-nombre').value || 'Tesorero',
                    fila.querySelector('.tesorero-imagen'), fila.querySelector('.tesorero-preview'));
            }
            return;
        }
        if (e.target.closest('[data-agregar-tesorero]')) {
            const indice = contenedorTesoreros.dataset.siguienteIndice;
            const html = document.getElementById('plantillaTesorero').innerHTML.replaceAll('__INDICE__', indice);
            contenedorTesoreros.insertAdjacentHTML('beforeend', html);
            contenedorTesoreros.dataset.siguienteIndice = String(Number(indice) + 1);
            contenedorTesoreros.lastElementChild.querySelector('.tesorero-nombre').focus();
            return;
        }
        const quitar = e.target.closest('[data-quitar-tesorero]');
        if (quitar) quitar.closest('.tesorero-row').remove();
    });

    document.getElementById('firmaLimpiar').addEventListener('click', function () {
        ctx && ctx.clearRect(0, 0, lienzo.width, lienzo.height);
    });
    document.getElementById('firmaGuardar').addEventListener('click', function () {
        if (!destino) return;
        const imagen = lienzo.toDataURL('image/png');
        destino.campoImagen.value = imagen;
        destino.vistaPrevia.innerHTML = '<img src="' + imagen + '" alt="Firma" class="max-w-full max-h-full object-contain">';
        cerrar(modalFirma);
    });
})();
</script>
@endpush
