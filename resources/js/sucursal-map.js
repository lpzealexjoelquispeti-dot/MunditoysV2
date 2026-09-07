import { dentroDelLimite } from './geo-fence.js';

export function mundiToysSucursalMap(wire, inicial, limite, cargarLeaflet = () => import('leaflet')) {
    let mapa = null;
    let marcador = null;
    let anterior = null;
    let observador = null;
    let destruido = false;
    let L = null;

    return {
        error: '',
        cargando: true,
        enviando: false,

        async init() {
            try {
                const modulo = await cargarLeaflet();
                if (destruido) return;
                L = modulo.default ?? modulo;
                mapa = L.map(this.$refs.mapa, { scrollWheelZoom: false, minZoom: 10, maxZoom: 19, maxBoundsViscosity: 1 });
                const zona = L.geoJSON(limite, { style: { color: '#005ca8', weight: 2, fillOpacity: 0.06 }, interactive: false }).addTo(mapa);
                mapa.setMaxBounds(zona.getBounds().pad(0.05));
                const teselas = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
                }).addTo(mapa);
                teselas.on('tileerror', () => {
                    if (!destruido) this.error = 'No se pudo cargar el mapa base. Revisa tu conexión; no se guardará una ubicación sin validar.';
                });
                const lat = Number(inicial.latitud);
                const lng = Number(inicial.longitud);
                if (inicial.latitud !== '' && inicial.longitud !== '' && dentroDelLimite(limite.geometry, lat, lng)) {
                    anterior = [lat, lng];
                    this.colocarMarcador(anterior);
                }
                mapa.setView(anterior ?? [-16.495, -68.133], 14);
                mapa.on('click', event => this.seleccionar(event.latlng));
                observador = new ResizeObserver(() => mapa?.invalidateSize({ pan: false }));
                observador.observe(this.$refs.mapa);
                this.cargando = false;
                this.$nextTick(() => mapa?.invalidateSize({ pan: false }));
            } catch {
                this.error = 'No se pudo iniciar el mapa. Recarga la página e inténtalo de nuevo.';
                this.cargando = false;
                mapa?.remove();
                mapa = null;
            }
        },

        colocarMarcador(punto) {
            if (!marcador) {
                marcador = L.marker(punto, {
                    draggable: true, autoPan: true, title: 'Ubicación de la sucursal',
                    icon: L.divIcon({
                        className: 'mundi-sucursal-marker',
                        html: '<span aria-hidden="true">●</span>',
                        iconSize: [28, 28], iconAnchor: [14, 14],
                    }),
                }).addTo(mapa);
                marcador.on('dragend', () => this.seleccionar(marcador.getLatLng()));
            } else marcador.setLatLng(punto);
        },

        async seleccionar({ lat, lng }) {
            if (this.enviando || destruido) {
                if (anterior) marcador?.setLatLng(anterior);
                return;
            }
            lat = Number(lat.toFixed(7));
            lng = Number(lng.toFixed(7));
            if (!dentroDelLimite(limite.geometry, lat, lng)) {
                this.error = 'Elige un punto dentro del municipio de La Paz. El Alto no está incluido.';
                if (anterior) marcador?.setLatLng(anterior);
                return;
            }
            this.error = '';
            this.enviando = true;
            try {
                await wire.$call('seleccionarUbicacion', lat.toFixed(7), lng.toFixed(7));
                if (destruido) return;
                anterior = [lat, lng];
                this.colocarMarcador(anterior);
            } catch {
                this.error = 'No se pudo validar la ubicación. Comprueba tu conexión e inténtalo otra vez.';
                if (anterior) marcador?.setLatLng(anterior);
            } finally {
                this.enviando = false;
            }
        },

        centrar() {
            mapa?.setView(anterior ?? [-16.495, -68.133], 14);
        },

        usarCentro() {
            if (mapa) this.seleccionar(mapa.getCenter());
        },

        destroy() {
            destruido = true;
            observador?.disconnect();
            mapa?.remove();
            mapa = null;
            marcador = null;
        },
    };
}
