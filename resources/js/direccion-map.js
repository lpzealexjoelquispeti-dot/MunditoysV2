import { dentroDelLimite } from './geo-fence.js';

export function mundiToysDireccionMap(wire, inicial, limite, cargarLeaflet = () => import('leaflet')) {
    let mapa = null;
    let marcador = null;
    let observador = null;
    let destruido = false;
    let L = null;

    return {
        error: '',
        cargando: true,
        obteniendoGps: false,

        async init() {
            try {
                const modulo = await cargarLeaflet();
                if (destruido || mapa) return;
                L = modulo.default ?? modulo;

                mapa = L.map(this.$refs.mapaDireccion, {
                    scrollWheelZoom: false,
                    minZoom: 10,
                    maxZoom: 19,
                });

                // Polígono del límite de La Paz
                const zona = L.geoJSON(limite, {
                    style: {
                        color: '#f97316',
                        weight: 2,
                        dashArray: '4, 6',
                        fillOpacity: 0.05,
                        fillColor: '#f97316',
                    },
                    interactive: false,
                }).addTo(mapa);

                mapa.setMaxBounds(zona.getBounds().pad(0.08));

                const teselas = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    referrerPolicy: 'strict-origin-when-cross-origin',
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
                }).addTo(mapa);
                teselas.on('tileerror', () => {
                    if (!destruido) this.error = 'No se pudieron cargar las imágenes del mapa. Revisa tu conexión y recarga la página.';
                });

                const lat = Number(inicial.latitud) || -16.4950000;
                const lng = Number(inicial.longitud) || -68.1330000;

                this.colocarMarcador([lat, lng]);
                mapa.setView([lat, lng], 15);

                mapa.on('click', (e) => this.seleccionar(e.latlng));

                observador = new ResizeObserver(() => mapa?.invalidateSize({ pan: false }));
                observador.observe(this.$refs.mapaDireccion);

                this.cargando = false;
                this.$nextTick(() => {
                    setTimeout(() => mapa?.invalidateSize({ pan: false }), 200);
                });
            } catch (e) {
                console.error('Error al inicializar mapa de direcciones:', e);
                this.error = 'No se pudo cargar el mapa. Recarga la página o inténtalo más tarde.';
                this.cargando = false;
            }
        },

        colocarMarcador(punto) {
            if (!marcador) {
                marcador = L.marker(punto, {
                    draggable: true,
                    autoPan: true,
                    title: 'Punto de entrega del juguete',
                    icon: L.divIcon({
                        className: 'mundi-toy-address-marker',
                        html: '<div style="background:#ea580c; color:white; width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:17px; border:3px solid white; box-shadow:0 4px 12px rgba(0,0,0,0.35); cursor:grab;">📍</div>',
                        iconSize: [34, 34],
                        iconAnchor: [17, 17],
                    }),
                }).addTo(mapa);

                marcador.on('dragend', () => this.seleccionar(marcador.getLatLng()));
            } else {
                marcador.setLatLng(punto);
            }
        },

        async seleccionar({ lat, lng }) {
            lat = Number(lat.toFixed(7));
            lng = Number(lng.toFixed(7));

            if (!dentroDelLimite(limite.geometry, lat, lng)) {
                this.error = 'Por el momento, MundiToys solo realiza entregas dentro de la ciudad de La Paz. Selecciona un punto dentro del área delimitada.';
                return;
            }

            this.error = '';
            this.colocarMarcador([lat, lng]);
            await wire.seleccionarUbicacion(lat.toFixed(7), lng.toFixed(7));
        },

        usarUbicacionActual() {
            if (!navigator.geolocation) {
                this.error = 'Tu navegador no permite la geolocalización. Por favor marca tu ubicación manualmente en el mapa.';
                return;
            }

            this.obteniendoGps = true;
            this.error = '';

            const alObtener = async (pos) => {
                this.obteniendoGps = false;
                const lat = Number(pos.coords.latitude.toFixed(7));
                const lng = Number(pos.coords.longitude.toFixed(7));

                if (!dentroDelLimite(limite.geometry, lat, lng)) {
                    this.error = 'Tu ubicación actual detectada está fuera del municipio de La Paz. Por favor selecciona tu dirección dentro de los límites de La Paz.';
                    return;
                }

                this.error = '';
                mapa?.setView([lat, lng], 16);
                this.colocarMarcador([lat, lng]);
                await wire.seleccionarUbicacion(lat.toFixed(7), lng.toFixed(7));
            };

            const alFallar = (err) => {
                this.obteniendoGps = false;
                if (err.code === 1) {
                    this.error = 'Permiso denegado por el navegador para acceder a tu ubicación actual. Puedes hacer clic en el mapa para ubicar tu dirección.';
                } else if (err.code === 2) {
                    this.error = 'No fue posible determinar tu ubicación GPS. Por favor ubica tu dirección en el mapa.';
                } else if (err.code === 3) {
                    this.error = 'El tiempo para obtener tu ubicación expiró. Intenta de nuevo o ubica tu dirección en el mapa.';
                } else {
                    this.error = 'No se pudo obtener la ubicación actual. Ubica tu dirección directamente en el mapa.';
                }
            };

            // Las laptops sin GPS suelen agotar el tiempo en alta precisión: se reintenta con la ubicación por Wi-Fi/red.
            navigator.geolocation.getCurrentPosition(
                alObtener,
                (err) => {
                    if (err.code === 1) {
                        alFallar(err);
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(alObtener, alFallar, { enableHighAccuracy: false, timeout: 15000, maximumAge: 300000 });
                },
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
            );
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

