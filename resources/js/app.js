import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';
import { mundiToysGlbPreview } from './product-model-preview';
import { mundiToysSucursalMap } from './sucursal-map';
import limiteLaPaz from '../geo/la-paz.json';
import 'leaflet/dist/leaflet.css';

window.mundiToysGlbPreview = mundiToysGlbPreview;
window.mundiToysSucursalMap = (wire, inicial) => mundiToysSucursalMap(wire, inicial, limiteLaPaz);

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

const cargarVisor3D = () => {
    if (document.querySelector('[data-product-manager]')) import('@google/model-viewer');
    if (document.querySelector('[data-product-manager], [data-modelado-3d]')) import('@google/model-viewer');
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
