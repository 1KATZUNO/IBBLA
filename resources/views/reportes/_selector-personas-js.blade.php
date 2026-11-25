@push('scripts')
<script>
    // Selector de personas de los reportes (HU-R2). config = {personas, ministerios, filtros, elegidas}
    // (CandidatasSelector + FiltroPersonas::filtrosRapidos). Filtra en el navegador con las mismas reglas
    // que el servidor; al consultar, "todas las filtradas" viaja como filtros y la selección como ids.
    function selectorPersonas(config) {
        const normalizar = (t) => String(t ?? '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        const soloDigitos = (t) => String(t ?? '').replace(/\D/g, '');
        const personas = config.personas;
        const porId = new Map(personas.map(p => [p.id, p]));
        return {
            catalogoMinisterios: config.ministerios,
            estado: config.filtros.estado,
            promesa: config.filtros.promesa,
            aporto: config.filtros.aporto,
            ministerios: config.filtros.ministerios.map(String),
            elegidas: config.elegidas.filter(id => porId.has(id)),
            busqueda: '',
            buscarMinisterio: '',
            limite: 50,
            avisoVisibles: false,
            // Sin ids elegidos: ninguna=false es "todas las filtradas" (casillas marcadas); ninguna=true es
            // "Quitar todos" (casillas vacías). En los dos casos el reporte incluye a todas las filtradas.
            ninguna: false,

            cumple(p) {
                if (this.estado === 'activos' && !p.activo) return false;
                if (this.estado === 'inactivos' && p.activo) return false;
                if (this.promesa === 'si' && !p.promesa) return false;
                if (this.promesa === 'no' && p.promesa) return false;
                if (this.aporto === 'si' && !p.aporto) return false;
                if (this.aporto === 'no' && p.aporto) return false;
                if (this.ministerios.includes('todos')) return p.ministerios.length > 0;
                if (this.ministerios.length > 0) return p.ministerios.some(m => this.ministerios.includes(String(m)));
                return true;
            },
            filtradas() { return personas.filter(p => this.cumple(p)); },
            conBusqueda() {
                const texto = normalizar(this.busqueda.trim());
                const digitos = soloDigitos(this.busqueda);
                const lista = this.filtradas();
                if (texto === '') return lista;
                return lista.filter(p => normalizar(p.nombre).includes(texto)
                    || (digitos !== '' && (soloDigitos(p.pin).includes(digitos) || soloDigitos(p.telefono).includes(digitos))));
            },
            visibles() { return this.conBusqueda().slice(0, this.limite); },
            ministeriosVisibles() {
                const texto = normalizar(this.buscarMinisterio.trim());
                return texto === '' ? this.catalogoMinisterios : this.catalogoMinisterios.filter(m => normalizar(m.nombre).includes(texto));
            },
            estaElegida(id) { return this.elegidas.length === 0 ? !this.ninguna : this.elegidas.includes(id); },
            elegidasObjetos() { return this.elegidas.map(id => porId.get(id)).filter(Boolean); },
            visiblesTodasElegidas() { const v = this.visibles(); return v.length > 0 && v.every(p => this.estaElegida(p.id)); },

            alternar(id) {
                this.avisoVisibles = false;
                if (this.elegidas.length === 0) {
                    // Estaban todas las filtradas: se pasa a selección a mano sin esta persona.
                    this.elegidas = this.ninguna ? [id] : this.filtradas().map(p => p.id).filter(x => x !== id);
                    this.ninguna = this.elegidas.length === 0;
                    return;
                }
                this.elegidas = this.elegidas.includes(id) ? this.elegidas.filter(x => x !== id) : [...this.elegidas, id];
            },
            alternarVisibles(marcar) {
                const ids = this.visibles().map(p => p.id);
                if (marcar) {
                    this.elegidas = [...new Set([...this.elegidas, ...ids])];
                    this.avisoVisibles = this.filtradas().length > this.elegidas.length;
                } else {
                    const base = this.elegidas.length === 0 && !this.ninguna ? this.filtradas().map(p => p.id) : this.elegidas;
                    this.elegidas = base.filter(id => !ids.includes(id));
                    this.ninguna = this.elegidas.length === 0;
                    this.avisoVisibles = false;
                }
            },
            // Enter en la búsqueda: agrega a la selección a mano la primera persona de la lista.
            agregarPrimera() {
                const v = this.visibles();
                if (v.length > 0 && !this.elegidas.includes(v[0].id)) { this.elegidas = [...this.elegidas, v[0].id]; this.ninguna = false; }
                this.busqueda = '';
            },
            seleccionarFiltrados() { this.elegidas = []; this.ninguna = false; this.avisoVisibles = false; },
            quitarTodos() { this.elegidas = []; this.ninguna = true; this.avisoVisibles = false; this.busqueda = ''; },
            alternarMinisterio(valor) {
                if (valor === 'todos') {
                    this.ministerios = this.ministerios.includes('todos') ? [] : ['todos'];
                } else {
                    const sin = this.ministerios.filter(m => m !== 'todos');
                    this.ministerios = sin.includes(valor) ? sin.filter(m => m !== valor) : [...sin, valor];
                }
                this.alCambiarFiltros();
            },
            alCambiarFiltros() {
                // Las elegidas a mano que ya no cumplen los filtros se sueltan.
                const antes = this.elegidas.length;
                this.elegidas = this.elegidas.filter(id => { const p = porId.get(id); return p && this.cumple(p); });
                if (antes > 0 && this.elegidas.length === 0) this.ninguna = true;
                this.avisoVisibles = false;
                this.limite = 50;
            },
            textoContador() {
                const n = this.elegidas.length > 0 ? this.elegidas.length : (this.ninguna ? 0 : this.filtradas().length);
                return n + (n === 1 ? ' persona seleccionada' : ' personas seleccionadas');
            },
            textoSinSeleccion() {
                const n = this.filtradas().length;
                if (!this.ninguna) return 'Están todas las que cumplen los filtros (' + n + '). Para elegir solo algunas, desmarque o marque en la lista.';
                return 'No eligió a nadie a mano: el reporte incluye a las ' + n + (n === 1 ? ' persona que cumple' : ' personas que cumplen') + ' los filtros.';
            },
        };
    }
</script>
@endpush
