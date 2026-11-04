@props([
    'name',
    'value' => 0,
    // true = la casilla cuenta en el total del formulario (el JS suma las que llevan .asistencia-input)
    'suma' => false,
    'requerido' => true,
])
{{--
    Casilla numérica de asistencia. Reemplaza al desplegable 0..100 que limitaba cada casilla a 100 personas.
    El tope sale de Asistencia::MAX_CANTIDAD, el mismo valor que usan las reglas de validación.
    - onfocus select(): al tocar la casilla el 0 queda seleccionado y el primer dígito lo reemplaza.
    - oninput: si alguien escribe "05" se limpia el cero (la regla `integer` de Laravel rechaza ceros a la izquierda).
    - onwheel blur(): evita que la rueda del ratón cambie la cantidad mientras se baja por la página.
    - onblur: una casilla que se deja vacía vuelve a 0, igual que cuando el desplegable siempre traía un valor.
--}}
<input type="number"
       name="{{ $name }}"
       value="{{ $value }}"
       min="0"
       max="{{ \App\Models\Asistencia::MAX_CANTIDAD }}"
       step="1"
       inputmode="numeric"
       @if($requerido) required @endif
       onfocus="this.select()"
       onwheel="this.blur()"
       onblur="if (this.value === '') this.value = '0'"
       oninput="if (/^0\d/.test(this.value)) this.value = this.value.replace(/^0+(?=\d)/, '')"
       {{ $attributes->class(['w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500', 'asistencia-input' => $suma]) }}>
