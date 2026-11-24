@push('scripts')
<script>
    // Selector de personas de los reportes ("Elegir personas"): busca por nombre sin tildes y deja chips.
    function selectorPersonas(personas, elegidasIds, grupo) {
        const normalizar = (t) => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        return {
            grupo: grupo,
            busqueda: '',
            elegidas: personas.filter(p => elegidasIds.includes(p.id)),
            sugerencias() {
                const texto = normalizar(this.busqueda.trim());
                if (texto === '') return [];
                const ya = this.elegidas.map(p => p.id);
                return personas.filter(p => !ya.includes(p.id) && normalizar(p.nombre).includes(texto)).slice(0, 8);
            },
            agregar(p) { this.elegidas.push(p); this.busqueda = ''; },
            agregarPrimera() { const s = this.sugerencias(); if (s.length > 0) this.agregar(s[0]); },
            quitar(id) { this.elegidas = this.elegidas.filter(p => p.id !== id); },
        };
    }
</script>
@endpush
