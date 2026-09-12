export function mundiToysLogisticaMap(puntosIniciales = [], cargarLeaflet = () => import('leaflet')) {
    let mapa = null;
    let marcadores = [];
    let L = null;
    let destruido = false;

    return {
        puntos: puntosIniciales,
        cargando: true,
        error: '',

        async init() {
            try {
                const modulo = await cargarLeaflet();
                if (destruido) return;
                L = modulo.default ?? modulo;

                await this.$nextTick();
                if (destruido || !this.$refs.mapaRutas) return;

                if (mapa) {
                    mapa.remove();
                    mapa = null;
                }

                // Centro inicial en La Paz, Bolivia
                mapa = L.map(this.$refs.mapaRutas, {
                    center: [-16.5000, -68.1300],
                    zoom: 13,
                    scrollWheelZoom: false,
                });

                const teselas = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    referrerPolicy: 'strict-origin-when-cross-origin',
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
                }).addTo(mapa);
                teselas.on('tileerror', () => {
                    if (!destruido) {
                        this.error = 'No se pudo cargar el mapa base. Revisa tu conexión o el acceso a OpenStreetMap.';
                    }
                });

                this.dibujarPuntos(this.puntos);
                this.cargando = false;

                // Observar cambios en los puntos de entrega si Livewire los actualiza
                this.$watch('puntos', (nuevosPuntos) => {
                    this.dibujarPuntos(nuevosPuntos);
                });
            } catch (err) {
                console.error('Error al inicializar mapa de logística:', err);
                this.error = 'No se pudo cargar el mapa interactivo de rutas.';
                this.cargando = false;
            }
        },

        destroy() {
            destruido = true;
            mapa?.remove();
            mapa = null;
            marcadores = [];
        },

        dibujarPuntos(listaPuntos) {
            if (!mapa || !L) return;

            // Limpiar marcadores previos
            marcadores.forEach((m) => m.remove());
            marcadores = [];

            if (!Array.isArray(listaPuntos) || listaPuntos.length === 0) {
                mapa.setView([-16.5000, -68.1300], 13);
                return;
            }

            const bounds = L.latLngBounds();

            listaPuntos.forEach((p, idx) => {
                if (typeof p.lat !== 'number' || typeof p.lng !== 'number') return;

                // Color según estado
                let colorPin = '#f97316'; // Pendiente (naranja)
                if (p.estado === 'entregado') colorPin = '#10b981'; // Verde
                if (p.estado === 'en_camino') colorPin = '#005ca8'; // Azul
                if (p.estado === 'cancelado') colorPin = '#dc2626'; // Rojo

                const iconoHtml = `
                    <div style="background-color: ${colorPin}; color: white; border: 2px solid white; border-radius: 9999px; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 11px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.3);">
                        ${idx + 1}
                    </div>
                `;

                const iconoPersonalizado = L.divIcon({
                    html: iconoHtml,
                    className: 'logistica-pin',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15],
                    popupAnchor: [0, -15],
                });

                const marker = L.marker([p.lat, p.lng], { icon: iconoPersonalizado }).addTo(mapa);

                const popupHtml = `
                    <div style="font-family: inherit; font-size: 12px; line-height: 1.4; padding: 2px; min-width: 180px;">
                        <div style="font-weight: bold; font-size: 13px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-bottom: 4px;">
                            #${idx + 1} · ${p.numero_venta}
                        </div>
                        <div style="margin-bottom: 2px;"><strong>Cliente:</strong> ${p.cliente}</div>
                        <div style="margin-bottom: 2px;"><strong>Teléfono:</strong> ${p.telefono}</div>
                        <div style="margin-bottom: 2px;"><strong>Turno:</strong> <span style="color: #f97316; font-weight: bold;">${p.hora_inicio} a ${p.hora_fin}</span></div>
                        <div style="margin-bottom: 2px;"><strong>Zona:</strong> ${p.zona}</div>
                        <div style="margin-bottom: 2px; color: #64748b;">${p.direccion}</div>
                        <div style="margin-top: 4px; padding-top: 4px; border-top: 1px dashed #e2e8f0; font-size: 11px;">
                            <strong>Chofer:</strong> ${p.repartidor}
                        </div>
                    </div>
                `;

                marker.bindPopup(popupHtml);
                marcadores.push(marker);
                bounds.extend([p.lat, p.lng]);
            });

            if (marcadores.length > 0) {
                mapa.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
            } else {
                mapa.setView([-16.5000, -68.1300], 13);
            }
        },
    };
}
