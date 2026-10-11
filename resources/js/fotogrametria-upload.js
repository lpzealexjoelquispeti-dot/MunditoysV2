export function mundiToysFotogrametriaUpload(wire) {
    return {
        subiendo: false,
        detenerSolicitado: false,
        progresoArchivo: 0,
        total: 0,
        procesadas: 0,
        guardadas: 0,
        nombreActual: '',
        errores: [],

        async subir(entrada, reemplazarId = null) {
            if (this.subiendo) return;

            const archivos = Array.from(entrada.files ?? [])
                .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true, sensitivity: 'base' }));
            const disponibles = Number(this.$root.dataset.disponibles ?? 0);
            entrada.value = '';
            this.errores = [];

            if (archivos.length === 0) return;
            if (reemplazarId !== null && archivos.length !== 1) {
                this.errores.push({ nombre: 'Selección', mensaje: 'Selecciona una sola fotografía para reemplazar esta posición.' });
                return;
            }
            if (reemplazarId === null && archivos.length > disponibles) {
                this.errores.push({ nombre: 'Selección', mensaje: `Quedan ${disponibles} posiciones. Selecciona como máximo esa cantidad de fotografías.` });
                return;
            }

            this.subiendo = true;
            this.detenerSolicitado = false;
            this.total = archivos.length;
            this.procesadas = 0;
            this.guardadas = 0;

            try {
                for (const archivo of archivos) {
                    if (this.detenerSolicitado) break;

                    this.nombreActual = archivo.name;
                    this.progresoArchivo = 0;

                    if (archivo.size > 10 * 1024 * 1024 || !/\.(jpe?g|png|webp)$/i.test(archivo.name)) {
                        this.errores.push({ nombre: archivo.name, mensaje: 'Utiliza JPG, PNG o WEBP de hasta 10 MB.' });
                        this.procesadas++;
                        continue;
                    }

                    try {
                        await new Promise((resolve, reject) => {
                            wire.upload('foto', archivo, resolve,
                                () => reject(new Error('No se pudo subir el archivo. Comprueba la conexión y vuelve a seleccionarlo.')),
                                (evento) => { this.progresoArchivo = evento.detail.progress; },
                                () => reject(new Error('La carga fue cancelada.')),
                            );
                        });

                        const guardada = await wire.guardarFoto(reemplazarId);

                        if (guardada) {
                            this.guardadas++;
                        } else {
                            this.errores.push({ nombre: archivo.name, mensaje: wire.errorSubida || 'La fotografía no pasó la validación de ingreso.' });
                        }
                    } catch (error) {
                        this.errores.push({ nombre: archivo.name, mensaje: error.message || 'No se pudo guardar. Comprueba la colección antes de reintentar.' });
                        this.detenerSolicitado = true;
                    }

                    this.procesadas++;
                }
            } finally {
                this.subiendo = false;
                this.nombreActual = '';
                this.progresoArchivo = 0;
            }
        },

        detener() {
            this.detenerSolicitado = true;
        },
    };
}
