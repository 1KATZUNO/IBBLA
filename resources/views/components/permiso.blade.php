{{--
    Regla unica "sin permiso = deshabilitado con motivo u oculto" (ver docs/ROLES.md y App\Support\Permiso).

    Uso:
        <x-permiso :permiso="Gate::inspect('puedeEditarCulto', $culto)">
            <a href="..." class="btn-primary">+ Agregar sobre</a>
        </x-permiso>

    - Permitido: se pinta el contenido tal cual.
    - Negado por el rol (Permiso::ocultar): no se pinta nada.
    - Negado por el estado (Permiso::deshabilitar): se pinta en gris, sin poder tocarlo, y el motivo
      sale en un globo al pasar el mouse, al tocarlo (tablet) o al llegar con el teclado. El globo
      es uno solo para toda la pagina y se acomoda para no salirse de la pantalla.

    etiqueta: nombre de la accion para lectores de pantalla cuando el contenido es solo un icono.
--}}
@props(['permiso', 'etiqueta' => null])
@php
    $seOculta = \App\Support\Permiso::seOculta($permiso);
    $motivo = (string) $permiso->message();
    $nombreAccion = $etiqueta ?? trim(preg_replace('/\s+/u', ' ', strip_tags((string) $slot)));
@endphp
@if($permiso->allowed())
{{ $slot }}
@elseif(! $seOculta)
@once
@push('styles')
<style>
    .accion-bloqueada { display: inline-flex; cursor: not-allowed; border-radius: .5rem; outline: none; }
    .accion-bloqueada__contenido { display: inline-flex; opacity: .45; filter: grayscale(1); pointer-events: none; }
    .accion-bloqueada:focus-visible { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #3b82f6; }
    .globo-motivo {
        position: fixed; z-index: 90; max-width: min(20rem, calc(100vw - 16px)); padding: .5rem .75rem;
        border-radius: .5rem; background: #1f2937; color: #fff; font-size: .8125rem; line-height: 1.25rem;
        font-weight: 500; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, .25); pointer-events: none;
    }
    .globo-motivo[hidden] { display: none; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    const globo = document.createElement('div');
    globo.className = 'globo-motivo';
    globo.id = 'globo-motivo';
    globo.setAttribute('role', 'tooltip');
    globo.hidden = true;
    document.body.appendChild(globo);

    function mostrar(accion) {
        globo.textContent = accion.dataset.motivo || '';
        globo.hidden = false;
        const r = accion.getBoundingClientRect();
        const g = globo.getBoundingClientRect();
        let arriba = r.bottom + 8;
        if (arriba + g.height > window.innerHeight - 8) arriba = r.top - g.height - 8;
        let izquierda = r.left + r.width / 2 - g.width / 2;
        izquierda = Math.max(8, Math.min(izquierda, window.innerWidth - g.width - 8));
        globo.style.top = Math.max(8, arriba) + 'px';
        globo.style.left = izquierda + 'px';
        accion.setAttribute('aria-describedby', 'globo-motivo');
    }
    function ocultar() { globo.hidden = true; }

    document.addEventListener('mouseover', function (e) {
        const accion = e.target.closest && e.target.closest('.accion-bloqueada');
        if (accion) mostrar(accion);
    });
    document.addEventListener('mouseout', function (e) {
        const accion = e.target.closest && e.target.closest('.accion-bloqueada');
        if (accion && !accion.contains(e.relatedTarget) && document.activeElement !== accion) ocultar();
    });
    document.addEventListener('focusin', function (e) {
        const accion = e.target.closest && e.target.closest('.accion-bloqueada');
        accion ? mostrar(accion) : ocultar();
    });
    document.addEventListener('focusout', function (e) {
        if (e.target.closest && e.target.closest('.accion-bloqueada')) ocultar();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') ocultar(); });
    window.addEventListener('resize', ocultar);
    document.addEventListener('scroll', ocultar, true);
})();
</script>
@endpush
@endonce
<span {{ $attributes->class(['accion-bloqueada']) }}
      tabindex="0" role="button" aria-disabled="true"
      aria-label="{{ $nombreAccion !== '' ? $nombreAccion.'. ' : '' }}{{ $motivo }}"
      data-motivo="{{ $motivo }}">
    <span class="accion-bloqueada__contenido" inert>{{ $slot }}</span>
</span>
@endif
