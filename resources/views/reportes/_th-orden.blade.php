{{--
    Cabecera ordenable de la vista previa (HU-R4). Variables: $vista (VistaPrevia), $columna, $titulo,
    $ruta (nombre de la ruta de la pantalla), $alinear (left|right|center).
--}}
<th scope="col" aria-sort="{{ $vista->ariaSort($columna) }}" class="px-1 py-1 text-{{ $alinear ?? 'left' }} text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
    <a href="{{ $vista->enlaceOrden($columna, request()->query(), $ruta) }}"
       class="inline-flex items-center gap-1 min-h-[44px] px-2 rounded hover:bg-gray-100 hover:text-gray-800 {{ $vista->orden === $columna ? 'text-gray-900' : '' }}"
       title="Ordenar por {{ mb_strtolower($titulo) }}">
        <span>{{ $titulo }}</span><span aria-hidden="true" class="text-[10px]">{{ $vista->flecha($columna) }}</span>
    </a>
</th>
