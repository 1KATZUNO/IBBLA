@props(['nombre' => 'documento'])
<svg {{ $attributes->merge(['class' => 'w-5 h-5']) }} fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ \App\Support\Iconos::trazo($nombre) }}"></path></svg>
