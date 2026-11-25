{{--
    QA-018: persona vinculada con el usuario (para que "Mi perfil" muestre su ficha y su estado de cuenta).
    Buscador por nombre, PIN o teléfono; no un desplegable con todas las personas.
    Variables: $persona (Persona vinculada o null), $usuarioId (id del usuario que se edita, o null al crear).
--}}
@php
    $inicial = old('persona_id') !== null
        ? \App\Models\Persona::find((int) old('persona_id'))
        : ($persona ?? null);
@endphp
<div class="mb-4"
     x-data="{
        q: '',
        resultados: [],
        buscando: false,
        elegida: @js($inicial ? ['id' => $inicial->id, 'nombre' => $inicial->nombre] : null),
        async buscar() {
            if (this.q.trim().length < 2) { this.resultados = []; return; }
            this.buscando = true;
            try {
                const url = new URL(@js(route('usuarios.buscar-personas')));
                url.searchParams.set('q', this.q.trim());
                @if($usuarioId) url.searchParams.set('usuario', @js($usuarioId)); @endif
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                this.resultados = r.ok ? await r.json() : [];
            } finally {
                this.buscando = false;
            }
        },
        elegir(p) {
            if (p.ocupada) return;
            this.elegida = { id: p.id, nombre: p.nombre };
            this.q = '';
            this.resultados = [];
        },
     }">
    <span class="block text-sm font-medium text-gray-700">Persona vinculada</span>
    <input type="hidden" name="persona_id" :value="elegida ? elegida.id : ''">

    <template x-if="elegida">
        <div class="mt-1 flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
            <span class="text-sm text-gray-900" x-text="elegida.nombre"></span>
            <button type="button" class="btn-ghost min-h-[44px]" @click="elegida = null">Quitar vínculo</button>
        </div>
    </template>

    <template x-if="!elegida">
        <div class="relative mt-1">
            <label for="buscar-persona" class="sr-only">Buscar persona</label>
            <input type="search" id="buscar-persona" x-model="q" @input.debounce.300ms="buscar()" autocomplete="off"
                   placeholder="Escriba nombre, PIN o teléfono"
                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
            <ul x-show="resultados.length" class="mt-1 divide-y divide-gray-100 rounded-md border border-gray-200 bg-white shadow">
                <template x-for="p in resultados" :key="p.id">
                    <li>
                        <button type="button" class="flex min-h-[44px] w-full flex-col items-start px-3 py-2 text-left hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="p.ocupada" @click="elegir(p)">
                            <span class="text-sm text-gray-900" x-text="p.nombre"></span>
                            <span class="text-xs text-gray-500" x-text="p.ocupada ? (p.detalle ? p.detalle + ' · ' : '') + 'ya tiene otro usuario' : p.detalle"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <p x-show="!buscando && q.trim().length >= 2 && !resultados.length" class="mt-1 text-sm text-gray-500">
                No hay personas con ese nombre, PIN o teléfono. Si todavía no está registrada, agréguela en Personas.
            </p>
        </div>
    </template>

    <p class="mt-1 text-xs text-gray-500">
        Opcional. Vincule al usuario con su ficha de persona para que en "Mi perfil" vea sus datos, sus promesas y su estado de cuenta.
    </p>
    @error('persona_id')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
