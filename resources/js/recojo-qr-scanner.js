import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

export function mundiToysQrScanner(wire, elementId) {
    let html5QrCode = null;
    let procesando = false;

    return {
        iniciando: true,
        errorCamara: null,
        escaneado: false,
        codigoDetectado: '',

        async iniciar() {
            this.iniciando = true;
            this.errorCamara = null;
            this.escaneado = false;
            this.codigoDetectado = '';
            procesando = false;

            try {
                // Asegurar que el elemento contenedor exista en el DOM
                await this.$nextTick();
                const container = document.getElementById(elementId);
                if (!container) {
                    throw new Error('Contenedor de cámara no encontrado');
                }

                html5QrCode = new Html5Qrcode(elementId, {
                    formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                    verbose: false,
                });

                const qrConfig = {
                    fps: 15,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                        const edgeSize = Math.max(180, Math.floor(minEdge * 0.72));
                        return { width: edgeSize, height: edgeSize };
                    },
                    aspectRatio: 1.0,
                };

                await html5QrCode.start(
                    { facingMode: 'environment' },
                    qrConfig,
                    (decodedText) => {
                        if (procesando) return;
                        procesando = true;
                        this.escaneado = true;
                        this.codigoDetectado = decodedText;

                        // Efecto sonoro de escaneo POS (beep con Web Audio API)
                        try {
                            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                            const osc = audioCtx.createOscillator();
                            const gain = audioCtx.createGain();
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.15);
                            osc.connect(gain);
                            gain.connect(audioCtx.destination);
                            osc.start();
                            osc.stop(audioCtx.currentTime + 0.15);
                        } catch (e) {
                            // Ignorar si el navegador bloquea audio sin interacción previa
                        }

                        // Pausa de 380ms para que el usuario visualice el marco verde de confirmación
                        setTimeout(async () => {
                            await this.detener();
                            if (wire && typeof wire.procesarCodigoEscaneado === 'function') {
                                await wire.procesarCodigoEscaneado(decodedText);
                            } else if (wire) {
                                wire.codigoQR = decodedText;
                                await wire.procesarCodigoQR();
                            }
                        }, 380);
                    },
                    () => {
                        // Error de frame individual normal cuando aún no se alinea un QR
                    }
                );

                this.iniciando = false;
            } catch (err) {
                console.error('Error al inicializar cámara QR:', err);
                this.errorCamara = 'No se pudo acceder a la cámara web (permiso denegado, dispositivo en uso o sin soporte HTTPS/localhost). Puedes usar la simulación o ingresar el código manualmente.';
                this.iniciando = false;
            }
        },

        async detener() {
            if (html5QrCode) {
                try {
                    if (html5QrCode.isScanning) {
                        await html5QrCode.stop();
                    }
                    html5QrCode.clear();
                } catch (e) {
                    console.warn('Error al detener escáner QR:', e);
                } finally {
                    html5QrCode = null;
                }
            }
        },
    };
}
