import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { mundiToysGlbPreview } from './product-model-preview';
import { mundiToysFotogrametriaUpload } from './fotogrametria-upload';
import { mundiToysSucursalMap } from './sucursal-map';
import { mundiToysDireccionMap } from './direccion-map';
import { mundiToysLogisticaMap } from './logistica-map';
import { mundiToysQrScanner } from './recojo-qr-scanner';
import limiteLaPaz from '../geo/la-paz.json';
import 'leaflet/dist/leaflet.css';

window.mundiToysGlbPreview = mundiToysGlbPreview;
window.mundiToysFotogrametriaUpload = mundiToysFotogrametriaUpload;
window.mundiToysSucursalMap = (wire, inicial) => mundiToysSucursalMap(wire, inicial, limiteLaPaz);
window.mundiToysDireccionMap = (wire, inicial) => mundiToysDireccionMap(wire, inicial, limiteLaPaz);
window.mundiToysLogisticaMap = mundiToysLogisticaMap;
window.mundiToysQrScanner = mundiToysQrScanner;

window.mundiToysIndicadorMapa = async (elemento, inicialLat, inicialLng, nombre, direccion, informarError = () => {}) => {
    try {
        const modulo = await import('leaflet');
        const L = modulo.default ?? modulo;
        const lat = Number(inicialLat) || -16.500;
        const lng = Number(inicialLng) || -68.130;

        const mapa = L.map(elemento, {
            scrollWheelZoom: false,
            zoomControl: true,
            minZoom: 10,
            maxZoom: 18,
        }).setView([lat, lng], 15);

        const teselas = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            referrerPolicy: 'strict-origin-when-cross-origin',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
        }).addTo(mapa);
        teselas.on('tileerror', () => informarError('No se pudo cargar el mapa. Puedes consultar la dirección de la sucursal o abrir el mapa externo.'));

        const icono = L.divIcon({
            className: 'mundi-pickup-pin',
            html: '<div style="background-color:#ea580c; color:white; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:18px; border:3px solid white; box-shadow:0 4px 12px rgba(0,0,0,0.35);">🏪</div>',
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -20],
        });

        const marcador = L.marker([lat, lng], { icon: icono }).addTo(mapa);
        const contenidoPopup = (titulo, detalle) => {
            const contenido = document.createElement('div');
            const encabezado = document.createElement('strong');
            encabezado.textContent = titulo || 'Sucursal MundiToys';
            const texto = document.createElement('p');
            texto.textContent = detalle || '';
            contenido.append(encabezado, texto);
            return contenido;
        };
        marcador.bindPopup(contenidoPopup(nombre, direccion));

        const observer = new ResizeObserver(() => {
            mapa?.invalidateSize({ pan: false });
        });
        observer.observe(elemento);

        const ajusteInicial = setTimeout(() => mapa.invalidateSize({ pan: false }), 250);

        return {
            actualizar(nuevaLat, nuevaLng, nuevoNombre, nuevaDireccion) {
                const nLat = Number(nuevaLat) || -16.500;
                const nLng = Number(nuevaLng) || -68.130;
                mapa.setView([nLat, nLng], 15);
                marcador.setLatLng([nLat, nLng]);
                marcador.setPopupContent(contenidoPopup(nuevoNombre, nuevaDireccion));
                mapa.invalidateSize({ pan: false });
            },
            destruir() {
                clearTimeout(ajusteInicial);
                observer.disconnect();
                mapa.remove();
            },
        };
    } catch (e) {
        console.error('Error al inicializar mapa:', e);
        informarError('No se pudo iniciar el mapa. Puedes consultar la dirección de la sucursal o abrir el mapa externo.');
        return null;
    }
};

const opcionesAlerta = {
    confirmButtonColor: '#005ca8',
    cancelButtonColor: '#64748b',
    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl' },
};

window.confirmarCrud = async (wire, accion, id, titulo, mensaje) => {
    if (!['eliminar', 'restaurar', 'archivar'].includes(accion) || Swal.isVisible()) return;
    const respuesta = await Swal.fire({
        ...opcionesAlerta,
        title: titulo,
        text: mensaje,
        icon: accion === 'restaurar' ? 'question' : 'warning',
        showCancelButton: true,
        focusCancel: true,
        confirmButtonText: accion === 'restaurar' ? 'Sí, restaurar' : accion === 'archivar' ? 'Sí, archivar' : 'Sí, enviar a papelera',
        cancelButtonText: 'Cancelar',
    });
    if (respuesta.isConfirmed) {
        await wire.$call(accion, ...(id === null ? [] : [id]));
    }
};

/**
 * Confirma las acciones sobre una sesión de fotogrametría. "Eliminar" es definitivo (borra las fotos del disco);
 * "archivar" la manda a la papelera conservando sus fotos.
 */
window.confirmarSesion3D = async (wire, accion, id, fotos = 0) => {
    const acciones = {
        archivarSesion: {
            title: '¿Mover a la papelera?',
            text: `Se conservan sus ${fotos} fotos. Podrás restaurarla cuando quieras.`,
            icon: 'warning',
            confirmButtonText: 'Mover a papelera',
        },
        restaurarSesion: {
            title: '¿Restaurar esta sesión?',
            text: 'Volverá a tus sesiones con todas sus fotos.',
            icon: 'question',
            confirmButtonText: 'Restaurar',
        },
        eliminarSesion: {
            title: '¿Eliminar definitivamente?',
            text: `Se borrarán ${fotos} fotos, sus máscaras y los modelos sin publicar para liberar espacio. No se puede deshacer.`,
            icon: 'error',
            confirmButtonText: 'Eliminar',
            confirmButtonColor: '#e30613',
        },
    };
    if (!Object.hasOwn(acciones, accion) || Swal.isVisible()) return;
    const respuesta = await Swal.fire({
        ...opcionesAlerta,
        ...acciones[accion],
        showCancelButton: true,
        focusCancel: true,
        cancelButtonText: 'Cancelar',
    });
    if (respuesta.isConfirmed) {
        await wire.$call(accion, id);
    }
};

const cargarVisor3D = () => {
    if (document.querySelector('[data-product-manager]')) import('@google/model-viewer');
    if (document.querySelector('[data-product-manager], [data-modelado-3d]')) import('@google/model-viewer');
    if (document.querySelector('[data-product-manager], [data-modelado-3d], model-viewer')) {
        import('@google/model-viewer');
    }
};

window.confirmarAccesoUsuario = async (wire, accion, id = null) => {
    const titulos = {
        guardar: 'Confirmar datos y acceso',
        desactivar: '¿Desactivar esta cuenta?',
        reactivar: '¿Reactivar esta cuenta?',
        reenviar: '¿Enviar una nueva invitación?',
        revocar: '¿Revocar esta invitación?',
    };
    if (!Object.hasOwn(titulos, accion) || Swal.isVisible()) return;
    if (accion === 'guardar' && !(await wire.$call('validarFormulario'))) return;
    const respuesta = await Swal.fire({
        ...opcionesAlerta,
        title: titulos[accion],
        text: 'Introduce tu contraseña actual de administrador. Esta operación quedará auditada.',
        input: 'password',
        inputLabel: 'Tu contraseña de administrador',
        inputAttributes: { autocomplete: 'current-password', maxlength: '1024' },
        inputValidator: (valor) => !valor ? 'Escribe tu contraseña para continuar.' : undefined,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        focusCancel: true,
    });
    if (!respuesta.isConfirmed) return;
    try {
        if (accion === 'guardar') await wire.$call('guardar', respuesta.value);
        else await wire.$call('confirmarAccion', accion, id, respuesta.value);
    } catch {
        await Swal.fire({ ...opcionesAlerta, icon: 'error', title: 'No se pudo completar la solicitud',
            text: 'Revisa tu conexión y actualiza el listado antes de reintentar.', confirmButtonText: 'Entendido' });
    } finally {
        respuesta.value = '';
    }
};
document.addEventListener('DOMContentLoaded', cargarVisor3D);
document.addEventListener('livewire:navigated', cargarVisor3D);

const registrarAlertas = () => {
    window.Livewire.on('alerta-producto', ({ titulo, mensaje, icono }) => {
        Swal.fire({ ...opcionesAlerta, title: titulo, text: mensaje, icon: icono, confirmButtonText: 'Entendido' });
    });
    // Compatibilidad con la confirmación que ya utiliza el CRUD de Productos.
    window.Livewire.on('confirmar-eliminacion', async ({ id }) => {
        const respuesta = await Swal.fire({
            ...opcionesAlerta,
            title: '¿Enviar producto a papelera?',
            text: 'Se conservarán sus registros e historial. Podrás restaurarlo posteriormente.',
            icon: 'warning', showCancelButton: true, focusCancel: true,
            confirmButtonText: 'Sí, enviar a papelera', cancelButtonText: 'Cancelar',
        });
        if (respuesta.isConfirmed) window.Livewire.dispatch('eliminarProducto', { id });
    });
};
if (window.Livewire) registrarAlertas();
else document.addEventListener('livewire:init', registrarAlertas, { once: true });
