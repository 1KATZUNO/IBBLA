{{--
    Nombre de la persona del sobre, en grande y junto al boton Guardar (pedido del cliente 04/11).
    Mientras no se elige a nadie dice "Sin persona" en gris; "ANÓNIMO" en rojo solo aparece cuando se
    marca «Sobre anónimo» a proposito (QE-014). El script de form-sobre lo actualiza.
--}}
@php
    $sobreBarra = isset($sobre) && $sobre ? $sobre : null;
    $personaIdBarra = old('persona_id', $sobreBarra ? $sobreBarra->persona_id : null);
    $personaBarra = $personaIdBarra ? $personas->firstWhere('id', (int) $personaIdBarra) : null;
    $anonimoBarra = ! $personaBarra && (bool) old('anonimo', $sobreBarra && ! $sobreBarra->persona_id);
    $estadoBarra = $personaBarra ? 'persona' : ($anonimoBarra ? 'anonimo' : 'vacio');
@endphp
<div class="mr-auto flex min-w-0 flex-col" aria-live="polite">
    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Sobre de</span>
    <span id="barraPersona" data-estado="{{ $estadoBarra }}"
          class="max-w-[22rem] truncate text-xl sm:text-2xl font-extrabold leading-tight {{ ['persona' => 'text-gray-900', 'anonimo' => 'text-red-600', 'vacio' => 'text-gray-400'][$estadoBarra] }}">{{ ['persona' => $personaBarra['nombre'] ?? '', 'anonimo' => 'ANÓNIMO', 'vacio' => 'Sin persona'][$estadoBarra] }}</span>
</div>
