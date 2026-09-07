import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dentroDelLimite } from '../../resources/js/geo-fence.js';
import { mundiToysSucursalMap } from '../../resources/js/sucursal-map.js';

const limite = JSON.parse(readFileSync(new URL('../../resources/geo/la-paz.json', import.meta.url), 'utf8'));

test('el mapa usa el límite municipal y no admite El Alto ni otras ciudades', () => {
    assert.equal(dentroDelLimite(limite.geometry, -16.495, -68.133), true);
    assert.equal(dentroDelLimite(limite.geometry, -16.539, -68.086), true);
    assert.equal(dentroDelLimite(limite.geometry, -16.504, -68.163), false);
    assert.equal(dentroDelLimite(limite.geometry, -16.5, -68.2), false);
    assert.equal(dentroDelLimite(limite.geometry, -17.39, -66.16), false);
    assert.equal(dentroDelLimite(limite.geometry, NaN, -68.133), false);
    assert.equal(dentroDelLimite(limite.geometry, -16.495, Infinity), false);
});

test('la prueba geométrica respeta huecos bordes y multipolígonos', () => {
    const exterior = [[0, 0], [4, 0], [4, 4], [0, 4], [0, 0]];
    const hueco = [[1, 1], [2, 1], [2, 2], [1, 2], [1, 1]];
    const geometry = { type: 'MultiPolygon', coordinates: [[exterior, hueco]] };
    assert.equal(dentroDelLimite(geometry, 0, 0), true);
    assert.equal(dentroDelLimite(geometry, 3, 3), true);
    assert.equal(dentroDelLimite(geometry, 1.5, 1.5), false);
    assert.equal(dentroDelLimite(geometry, 5, 5), false);
});

function visorPrueba(t, call = async () => {}) {
    const estado = { quitados: 0, desconectados: 0, marcadores: 0, posicion: null, limites: 0 };
    const mapa = {
        setMaxBounds() { estado.limites++; return this; },
        setView() { return this; },
        on() { return this; },
        invalidateSize() {},
        remove() { estado.quitados++; },
        getCenter() { return { lat: -16.495, lng: -68.133 }; },
    };
    const L = {
        map: () => mapa,
        geoJSON: () => ({ addTo() { return this; }, getBounds: () => ({ pad: () => ({}) }) }),
        tileLayer: () => ({ addTo() { return this; }, on() { return this; } }),
        divIcon: options => options,
        marker: punto => {
            estado.marcadores++;
            estado.posicion = punto;
            return {
                addTo() { return this; },
                on() { return this; },
                setLatLng(punto) { estado.posicion = punto; return this; },
            };
        },
    };
    const observadorAnterior = globalThis.ResizeObserver;
    globalThis.ResizeObserver = class {
        observe() {}
        disconnect() { estado.desconectados++; }
    };
    t.after(() => { globalThis.ResizeObserver = observadorAnterior; });
    const visor = mundiToysSucursalMap({ $call: call }, { latitud: '', longitud: '' }, limite, async () => L);
    visor.$refs = { mapa: {} };
    visor.$nextTick = fn => fn();
    return { visor, estado };
}

test('valida en el servidor reutiliza el marcador y limpia el mapa al cerrar', async t => {
    const llamadas = [];
    const { visor, estado } = visorPrueba(t, async (...args) => llamadas.push(args));
    await visor.init();
    assert.equal(visor.cargando, false);
    assert.equal(estado.limites, 1);
    await visor.seleccionar({ lat: -16.495, lng: -68.133 });
    assert.deepEqual(llamadas[0], ['seleccionarUbicacion', '-16.4950000', '-68.1330000']);
    await visor.seleccionar({ lat: -16.539, lng: -68.086 });
    assert.equal(estado.marcadores, 1);
    await visor.seleccionar({ lat: -16.5, lng: -68.2 });
    assert.equal(llamadas.length, 2);
    assert.match(visor.error, /El Alto no está incluido/);
    assert.deepEqual(estado.posicion, [-16.539, -68.086]);
    visor.destroy();
    assert.equal(estado.quitados, 1);
    assert.equal(estado.desconectados, 1);
});

test('un fallo de red informa en español y permite reintentar', async t => {
    let intentos = 0;
    const { visor, estado } = visorPrueba(t, async () => {
        if (++intentos === 1) throw new Error('red');
    });
    await visor.init();
    await visor.seleccionar({ lat: -16.495, lng: -68.133 });
    assert.match(visor.error, /No se pudo validar la ubicación/);
    assert.equal(visor.enviando, false);
    assert.equal(estado.marcadores, 0);
    await visor.seleccionar({ lat: -16.495, lng: -68.133 });
    assert.equal(estado.marcadores, 1);
    assert.equal(visor.error, '');
    visor.destroy();
});

test('cerrar durante la descarga de Leaflet no crea un mapa huérfano', async () => {
    let finalizar;
    let iniciado = false;
    const carga = new Promise(resolve => { finalizar = resolve; });
    const visor = mundiToysSucursalMap({}, { latitud: '', longitud: '' }, limite, () => carga);
    const promesa = visor.init();
    visor.destroy();
    finalizar({ map: () => { iniciado = true; } });
    await promesa;
    assert.equal(iniciado, false);
});
