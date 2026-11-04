{{--
    Filtro de personas de los reportes (FiltroPersonas): todas las activas, con promesa, por
    ministerio ("cualquier ministerio" = servidores) o elegidas a mano con buscador y chips.
    Va dentro de un <form> con x-data="selectorPersonas(...)" (ver _selector-personas-js).
    Variables: $filtro (FiltroPersonas), $ministerios.
--}}
<div>
    <label for="grupo" class="block text-sm font-medium text-gray-700 mb-1">Personas</label>
    <select name="grupo" id="grupo" x-model="grupo"
            class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <option value="todas">Todas las activas</option>
        <option value="con_promesa">Con promesa</option>
        <option value="ministerio">Por ministerio</option>
        <option value="seleccion">Elegir personas</option>
    </select>
</div>
<div x-show="grupo === 'ministerio'" x-cloak>
    <label for="ministerio" class="block text-sm font-medium text-gray-700 mb-1">Ministerio</label>
    <select name="ministerio" id="ministerio" :disabled="grupo !== 'ministerio'"
            class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <option value="todos">Cualquier ministerio (servidores)</option>
        @foreach($ministerios as $ministerio)
            <option value="{{ $ministerio->id }}" @selected($filtro->ministerioId === $ministerio->id)>{{ $ministerio->nombre }}</option>
        @endforeach
    </select>
</div>
<div x-show="grupo === 'seleccion'" x-cloak class="sm:col-span-2">
    <label for="buscar-persona" class="block text-sm font-medium text-gray-700 mb-1">
        Personas elegidas <span class="text-gray-500 font-normal" x-text="'(' + elegidas.length + ')'"></span>
    </label>
    <input type="hidden" name="personas" :value="elegidas.map(p => p.id).join(',')" :disabled="grupo !== 'seleccion'">
    <div class="relative">
        <input type="search" id="buscar-persona" x-model="busqueda" autocomplete="off" placeholder="Escriba un nombre y elija"
               @keydown.enter.prevent="agregarPrimera()" @keydown.escape="busqueda = ''"
               class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <ul x-show="sugerencias().length > 0" x-cloak
            class="absolute z-30 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg">
            <template x-for="p in sugerencias()" :key="p.id">
                <li>
                    <button type="button" @click="agregar(p)" class="w-full text-left px-3 py-2 min-h-[44px] hover:bg-blue-50">
                        <span x-text="p.nombre"></span>
                        <span x-show="!p.activo" class="text-xs text-gray-500">(inactiva)</span>
                    </button>
                </li>
            </template>
        </ul>
    </div>
    <div class="flex flex-wrap gap-2 mt-2">
        <template x-for="p in elegidas" :key="p.id">
            <span class="inline-flex items-center gap-1 pl-3 py-1 rounded-full bg-blue-50 text-blue-800 text-sm border border-blue-200">
                <span x-text="p.nombre"></span>
                <button type="button" @click="quitar(p.id)" class="inline-flex items-center justify-center h-7 w-7 rounded-full hover:bg-blue-100" :aria-label="'Quitar a ' + p.nombre">&times;</button>
            </span>
        </template>
        <button type="button" x-show="elegidas.length > 0" @click="elegidas = []" class="text-sm text-gray-600 hover:underline px-2">Quitar todas</button>
    </div>
</div>
