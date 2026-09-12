import test from 'node:test';
import assert from 'node:assert/strict';
import { mundiToysLogisticaMap } from '../../resources/js/logistica-map.js';

function prepararMapa() {
    const estado = { opciones: null, eliminado: false, eventos: {} };
    const mapa = {
        setView() {},
        remove() { estado.eliminado = true; },
    };
    const leaflet = {
        map: () => mapa,
        tileLayer: (url, opciones) => {
            estado.opciones = opciones;
            return {
                addTo() { return this; },
                on(evento, callback) { estado.eventos[evento] = callback; },
            };
        },
    };
    const visor = mundiToysLogisticaMap([], async () => leaflet);
    visor.$refs = { mapaRutas: {} };
    visor.$nextTick = async () => {};
    visor.$watch = () => {};

    return { visor, estado };
}

test('las imagenes del mapa envian el origen exigido por OpenStreetMap', async () => {
    const { visor, estado } = prepararMapa();

    await visor.init();

    assert.equal(estado.opciones.referrerPolicy, 'strict-origin-when-cross-origin');
    assert.match(estado.opciones.attribution, /OpenStreetMap/);
    assert.equal(visor.cargando, false);
    assert.equal(visor.error, '');
});

test('un fallo del mapa base muestra un mensaje y salir libera Leaflet', async () => {
    const { visor, estado } = prepararMapa();
    await visor.init();

    estado.eventos.tileerror();
    assert.match(visor.error, /No se pudo cargar el mapa base/);

    visor.destroy();
    assert.equal(estado.eliminado, true);
    visor.error = '';
    estado.eventos.tileerror();
    assert.equal(visor.error, '');
});

test('salir antes de cargar Leaflet evita inicializar un mapa desmontado', async () => {
    let completarCarga;
    const visor = mundiToysLogisticaMap([], () => new Promise(resolve => { completarCarga = resolve; }));
    const inicializacion = visor.init();

    visor.destroy();
    completarCarga({ map() { assert.fail('No debe crear un mapa después de salir de la vista'); } });

    await inicializacion;
});
