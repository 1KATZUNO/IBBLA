{{--
    Tarjeta de resumen (KPI): etiqueta, valor grande y una nota corta.
    Se agrupan dentro de <div class="summary-grid">.

    <x-ui.resumen etiqueta="Ingresos del mes" valor="₡1.250.000" nota="vs. septiembre: +10 %" />
--}}
@props(['etiqueta', 'valor', 'nota' => null])
<div {{ $attributes->class('summary-card') }}>
    <p class="summary-label">{{ $etiqueta }}</p>
    <p class="summary-value">{{ $valor }}</p>
    @if($nota)
        <p class="summary-hint">{{ $nota }}</p>
    @endif
    {{ $slot }}
</div>
