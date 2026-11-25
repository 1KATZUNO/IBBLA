{{--
    Selector de personas de los reportes (HU-R2, FiltroPersonas): filtros rápidos combinables
    (estado, ministerios, con promesa, aportó en el periodo), lista de las que cumplen los filtros con
    casillas, búsqueda por nombre, PIN o teléfono, "Seleccionar todos los filtrados" / "Quitar todos" y
    contador. Sin selección a mano entran todas las que cumplen los filtros, y se dice.
    Va dentro de un <form> con x-data="selectorPersonas(...)" (ver _selector-personas-js).
    Todo viaja en la URL: grupo=filtros|seleccion, estado, ministerios, promesa, aporto, personas.
--}}
<fieldset class="sm:col-span-2 lg:col-span-4 border border-gray-200 rounded-lg p-3 sm:p-4" aria-labelledby="titulo-personas">
    <legend id="titulo-personas" class="px-1 text-sm font-semibold text-gray-800">Personas</legend>

    <input type="hidden" name="grupo" :value="elegidas.length > 0 ? 'seleccion' : 'filtros'">
    <input type="hidden" name="personas" :value="elegidas.join(',')" :disabled="elegidas.length === 0">
    <input type="hidden" name="ministerios" :value="ministerios.join(',')" :disabled="ministerios.length === 0">

    {{-- Filtros rápidos --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label for="filtro-estado" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
            <select name="estado" id="filtro-estado" x-model="estado" @change="alCambiarFiltros()"
                    class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="activos">Activas</option>
                <option value="inactivos">Inactivas</option>
                <option value="todos">Activas e inactivas</option>
            </select>
        </div>
        <div>
            <label for="filtro-promesa" class="block text-sm font-medium text-gray-700 mb-1">Con promesa</label>
            <select name="promesa" id="filtro-promesa" x-model="promesa" @change="alCambiarFiltros()"
                    class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="todos">Todas</option>
                <option value="si">Sí, con promesa</option>
                <option value="no">No, sin promesa</option>
            </select>
        </div>
        <div>
            <label for="filtro-aporto" class="block text-sm font-medium text-gray-700 mb-1">Aportó en el periodo</label>
            <select name="aporto" id="filtro-aporto" x-model="aporto" @change="alCambiarFiltros()"
                    class="w-full min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="todos">Todas</option>
                <option value="si">Sí aportó</option>
                <option value="no">No aportó</option>
            </select>
        </div>
    </div>

    {{-- Ministerios (varios a la vez) --}}
    <div class="mt-3" x-show="catalogoMinisterios.length > 0">
        <p class="block text-sm font-medium text-gray-700 mb-1" id="titulo-ministerios">Ministerios <span class="font-normal text-gray-500">(puede marcar varios; ninguno = no filtrar por ministerio)</span></p>
        <input type="search" x-show="catalogoMinisterios.length > 12" x-model="buscarMinisterio" placeholder="Buscar ministerio" aria-label="Buscar ministerio"
               class="mb-2 w-full sm:max-w-xs min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <div class="flex flex-wrap gap-2" role="group" aria-labelledby="titulo-ministerios">
            <label class="inline-flex items-center gap-2 min-h-[44px] px-3 rounded-lg border cursor-pointer"
                   :class="ministerios.includes('todos') ? 'bg-blue-50 border-blue-400 text-blue-900' : 'bg-white border-gray-300 text-gray-700'">
                <input type="checkbox" class="rounded border-gray-300" :checked="ministerios.includes('todos')" @change="alternarMinisterio('todos')">
                <span>Cualquier ministerio</span>
            </label>
            <template x-for="m in ministeriosVisibles()" :key="m.id">
                <label class="inline-flex items-center gap-2 min-h-[44px] px-3 rounded-lg border cursor-pointer"
                       :class="ministerios.includes(String(m.id)) ? 'bg-blue-50 border-blue-400 text-blue-900' : 'bg-white border-gray-300 text-gray-700'">
                    <input type="checkbox" class="rounded border-gray-300" :checked="ministerios.includes(String(m.id))" @change="alternarMinisterio(String(m.id))">
                    <span x-text="m.nombre"></span>
                </label>
            </template>
        </div>
    </div>

    {{-- Contador y acciones --}}
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <p class="text-sm font-semibold text-gray-900" aria-live="polite" x-text="textoContador()"></p>
        <div class="flex flex-wrap gap-2 ml-auto">
            <button type="button" @click="seleccionarFiltrados()" class="btn-secondary min-h-[44px]">Seleccionar todos los filtrados</button>
            <button type="button" @click="quitarTodos()" class="btn-secondary min-h-[44px]">Quitar todos</button>
        </div>
    </div>
    <p class="mt-1 text-sm text-blue-900 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2" x-show="elegidas.length === 0" x-text="textoSinSeleccion()"></p>

    {{-- Aviso tipo Gmail --}}
    <p class="mt-2 text-sm bg-amber-50 border border-amber-200 text-amber-900 rounded-lg px-3 py-2" x-show="avisoVisibles" x-cloak>
        <span x-text="'Se eligieron las ' + elegidas.length + ' personas que se ven en la lista.'"></span>
        <button type="button" class="underline font-semibold min-h-[44px] px-1" @click="seleccionarFiltrados()"
                x-text="'Seleccionar las ' + filtradas().length + ' que cumplen los filtros'"></button>
    </p>

    {{-- Lista de las que cumplen los filtros --}}
    <div class="mt-3">
        <label for="buscar-persona" class="block text-sm font-medium text-gray-700 mb-1">Buscar en la lista (nombre, PIN o teléfono)</label>
        <input type="search" id="buscar-persona" x-model="busqueda" autocomplete="off" placeholder="Escriba para buscar"
               @keydown.enter.prevent="agregarPrimera()" @keydown.escape="busqueda = ''"
               class="w-full sm:max-w-md min-h-[44px] rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">

        <div class="mt-2 border border-gray-200 rounded-lg max-h-72 overflow-y-auto">
            <label class="flex items-center gap-3 px-3 min-h-[44px] bg-gray-50 border-b border-gray-200 sticky top-0 cursor-pointer">
                <input type="checkbox" class="rounded border-gray-300" :checked="visiblesTodasElegidas()" @change="alternarVisibles($event.target.checked)">
                <span class="text-sm font-medium text-gray-700" x-text="'Elegir las ' + visibles().length + ' de la lista'"></span>
                <span class="ml-auto text-xs text-gray-500" x-text="visibles().length < conBusqueda().length ? ('se ven ' + visibles().length + ' de ' + conBusqueda().length) : ''"></span>
            </label>
            <template x-for="p in visibles()" :key="p.id">
                <label class="flex items-center gap-3 px-3 min-h-[44px] border-b border-gray-100 hover:bg-blue-50 cursor-pointer">
                    <input type="checkbox" class="rounded border-gray-300" :checked="estaElegida(p.id)" @change="alternar(p.id)">
                    <span class="text-sm text-gray-900" x-text="p.nombre"></span>
                    <span x-show="!p.activo" class="text-xs text-gray-500">(inactiva)</span>
                    <span x-show="p.promesa" class="ml-auto text-xs text-gray-500">con promesa</span>
                </label>
            </template>
            <p x-show="conBusqueda().length === 0" class="px-3 py-4 text-sm text-gray-600">Nadie cumple estos filtros. Cambie el estado, el ministerio o quite la búsqueda.</p>
            <button type="button" x-show="visibles().length < conBusqueda().length" @click="limite += 50"
                    class="w-full min-h-[44px] text-sm text-blue-700 hover:bg-blue-50">Mostrar 50 más</button>
        </div>
    </div>

    {{-- Chips de las elegidas a mano --}}
    <div class="flex flex-wrap gap-2 mt-3" x-show="elegidas.length > 0" x-cloak>
        <template x-for="p in elegidasObjetos()" :key="p.id">
            <span class="inline-flex items-center gap-1 pl-3 py-1 rounded-full bg-blue-50 text-blue-800 text-sm border border-blue-200">
                <span x-text="p.nombre"></span>
                <button type="button" @click="alternar(p.id)" class="inline-flex items-center justify-center h-11 w-11 rounded-full hover:bg-blue-100" :aria-label="'Quitar a ' + p.nombre">&times;</button>
            </span>
        </template>
    </div>
</fieldset>
