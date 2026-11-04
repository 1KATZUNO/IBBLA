{{--
    Nombre de la persona del sobre, en grande y junto al boton Guardar (pedido del cliente 04/11):
    si dice ANÓNIMO en rojo, todavia no se eligio a nadie. El script de form-sobre lo actualiza.
--}}
@php
    $personaIdBarra = old('persona_id', isset($sobre) && $sobre ? $sobre->persona_id : null);
    $personaBarra = $personaIdBarra ? $personas->firstWhere('id', (int) $personaIdBarra) : null;
@endphp
<div class="mr-auto flex min-w-0 flex-col" aria-live="polite">
    <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Sobre de</span>
    <span id="barraPersona" data-anonimo="{{ $personaBarra ? '0' : '1' }}"
          class="max-w-[22rem] truncate text-xl sm:text-2xl font-extrabold leading-tight {{ $personaBarra ? 'text-gray-900' : 'text-red-600' }}">{{ $personaBarra['nombre'] ?? 'ANÓNIMO' }}</span>
</div>
