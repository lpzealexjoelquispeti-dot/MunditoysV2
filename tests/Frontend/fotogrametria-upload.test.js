import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mundiToysFotogrametriaUpload } from '../../resources/js/fotogrametria-upload.js';

function cargadorPrueba(wire, disponibles = 48) {
    const cargador = mundiToysFotogrametriaUpload(wire);
    cargador.$root = { dataset: { disponibles: String(disponibles) } };
    cargador.$el = { dataset: {} };
    return cargador;
}

function entradaPrueba(archivos) {
    return { files: archivos, value: 'selección local' };
}

function fotoPrueba(nombre = 'juguete.jpg', size = 1024) {
    return { name: nombre, size };
}

test('guarda las 48 fotos una por una y espera el guardado antes de iniciar la siguiente', async () => {
    const archivos = Array.from({ length: 48 }, (_, indice) => fotoPrueba(`foto-${indice + 1}.jpg`));
    const eventos = [];
    let archivoActual;
    let pendientes = 0;
    let maximoPendientes = 0;
    const cargador = cargadorPrueba({
        upload(propiedad, archivo, completar, fallar, progreso) {
            archivoActual = archivo.name;
            pendientes++;
            maximoPendientes = Math.max(maximoPendientes, pendientes);
            eventos.push(['subida', propiedad, archivo.name]);
            progreso({ detail: { progress: 75 } });
            completar();
        },
        async guardarFoto(reemplazarId) {
            const nombre = archivoActual;
            await Promise.resolve();
            eventos.push(['guardado', reemplazarId, nombre]);
            pendientes--;
            return true;
        },
    });
    const entrada = entradaPrueba(archivos);

    await cargador.subir(entrada);

    assert.equal(maximoPendientes, 1);
    assert.deepEqual(eventos, archivos.flatMap(archivo => [
        ['subida', 'foto', archivo.name],
        ['guardado', null, archivo.name],
    ]));
    assert.equal(cargador.total, 48);
    assert.equal(cargador.procesadas, 48);
    assert.equal(cargador.guardadas, 48);
    assert.deepEqual(cargador.errores, []);
    assert.equal(cargador.subiendo, false);
    assert.equal(cargador.nombreActual, '');
    assert.equal(cargador.progresoArchivo, 0);
    assert.equal(entrada.value, '');
});

test('reemplaza la foto indicada aunque la colección ya tenga sus 48 posiciones ocupadas', async () => {
    const guardados = [];
    const cargador = cargadorPrueba({
        upload(propiedad, archivo, completar) { completar(); },
        async guardarFoto(reemplazarId) { guardados.push(reemplazarId); return true; },
    }, 0);

    await cargador.subir(entradaPrueba([fotoPrueba('reemplazo.png')]), 37);

    assert.deepEqual(guardados, [37]);
    assert.equal(cargador.guardadas, 1);
    assert.deepEqual(cargador.errores, []);
});

for (const caso of [
    { nombre: 'más de 48 fotos', cantidad: 49, disponibles: 48, reemplazarId: null, mensaje: /Quedan 48 posiciones/ },
    { nombre: 'más fotos que las posiciones libres', cantidad: 3, disponibles: 2, reemplazarId: null, mensaje: /Quedan 2 posiciones/ },
    { nombre: 'varias fotos para un mismo reemplazo', cantidad: 2, disponibles: 48, reemplazarId: 37, mensaje: /una sola fotografía/ },
]) {
    test(`rechaza ${caso.nombre} antes de transferir archivos`, async () => {
        const solicitudes = [];
        const cargador = cargadorPrueba({
            upload() { solicitudes.push('subida'); },
            async guardarFoto() { solicitudes.push('guardado'); return true; },
        }, caso.disponibles);
        const entrada = entradaPrueba(Array.from({ length: caso.cantidad }, (_, indice) => fotoPrueba(`foto-${indice}.jpg`)));

        await cargador.subir(entrada, caso.reemplazarId);

        assert.deepEqual(solicitudes, []);
        assert.equal(cargador.errores.length, 1);
        assert.match(cargador.errores[0].mensaje, caso.mensaje);
        assert.equal(cargador.subiendo, false);
        assert.equal(entrada.value, '');
    });
}

test('omite formatos y tamaños inválidos y admite WEBP de exactamente 10 MB', async () => {
    const transferidos = [];
    const cargador = cargadorPrueba({
        upload(propiedad, archivo, completar) { transferidos.push(archivo.name); completar(); },
        async guardarFoto() { return true; },
    });

    await cargador.subir(entradaPrueba([
        fotoPrueba('1-grande.jpg', 10 * 1024 * 1024 + 1),
        fotoPrueba('2-documento.pdf'),
        fotoPrueba('3-limite.WEBP', 10 * 1024 * 1024),
    ]));

    assert.deepEqual(transferidos, ['3-limite.WEBP']);
    assert.equal(cargador.procesadas, 3);
    assert.equal(cargador.guardadas, 1);
    assert.deepEqual(cargador.errores, [
        { nombre: '1-grande.jpg', mensaje: 'Utiliza JPG, PNG o WEBP de hasta 10 MB.' },
        { nombre: '2-documento.pdf', mensaje: 'Utiliza JPG, PNG o WEBP de hasta 10 MB.' },
    ]);
});

test('muestra el rechazo de ingreso del servidor y continúa con la siguiente foto', async () => {
    const transferidos = [];
    let intentos = 0;
    const cargador = cargadorPrueba({
        errorSubida: 'La imagen supera los 20 megapíxeles.',
        upload(propiedad, archivo, completar) { transferidos.push(archivo.name); completar(); },
        async guardarFoto() { return ++intentos > 1; },
    });

    await cargador.subir(entradaPrueba([fotoPrueba('1-rechazada.jpg'), fotoPrueba('2-aceptada.jpg')]));

    assert.deepEqual(transferidos, ['1-rechazada.jpg', '2-aceptada.jpg']);
    assert.equal(cargador.procesadas, 2);
    assert.equal(cargador.guardadas, 1);
    assert.deepEqual(cargador.errores, [{ nombre: '1-rechazada.jpg', mensaje: 'La imagen supera los 20 megapíxeles.' }]);
    assert.equal(cargador.detenerSolicitado, false);
});

for (const caso of [
    { nombre: 'falla la transferencia', fallo: 'subida', mensaje: /No se pudo subir el archivo/ },
    { nombre: 'falla el guardado', fallo: 'guardado', mensaje: /Conexión interrumpida/ },
    { nombre: 'Livewire cancela la transferencia', fallo: 'cancelacion', mensaje: /La carga fue cancelada/ },
]) {
    test(`detiene el lote cuando ${caso.nombre} y permite un nuevo intento`, async () => {
        const transferidos = [];
        let fallarIntento = true;
        const cargador = cargadorPrueba({
            upload(propiedad, archivo, completar, fallar, progreso, cancelar) {
                transferidos.push(archivo.name);
                if (fallarIntento && caso.fallo === 'subida') {
                    fallar();
                } else if (fallarIntento && caso.fallo === 'cancelacion') {
                    cancelar();
                } else {
                    completar();
                }
            },
            async guardarFoto() {
                if (fallarIntento && caso.fallo === 'guardado') throw new Error('Conexión interrumpida');
                return true;
            },
        });

        await cargador.subir(entradaPrueba([fotoPrueba('fallida.jpg'), fotoPrueba('pendiente.jpg')]));

        assert.deepEqual(transferidos, ['fallida.jpg']);
        assert.equal(cargador.guardadas, 0);
        assert.equal(cargador.procesadas, 1);
        assert.equal(cargador.subiendo, false);
        assert.equal(cargador.errores.length, 1);
        assert.equal(cargador.errores[0].nombre, 'fallida.jpg');
        assert.match(cargador.errores[0].mensaje, caso.mensaje);

        fallarIntento = false;
        await cargador.subir(entradaPrueba([fotoPrueba('reintento.jpg')]));

        assert.deepEqual(transferidos, ['fallida.jpg', 'reintento.jpg']);
        assert.equal(cargador.guardadas, 1);
        assert.deepEqual(cargador.errores, []);
        assert.equal(cargador.detenerSolicitado, false);
    });
}

test('detener termina la foto en curso sin iniciar otra y conserva las fotos ya guardadas', async () => {
    let completarSubida;
    const transferidos = [];
    const guardados = [];
    const cargador = cargadorPrueba({
        upload(propiedad, archivo, completar, fallar, progreso) {
            transferidos.push(archivo.name);
            completarSubida = completar;
            progreso({ detail: { progress: 42 } });
        },
        async guardarFoto(reemplazarId) { guardados.push(reemplazarId); return true; },
    });
    const carga = cargador.subir(entradaPrueba([fotoPrueba('actual.jpg'), fotoPrueba('pendiente.jpg')]));
    assert.equal(cargador.progresoArchivo, 42);
    assert.equal(cargador.subiendo, true);

    cargador.detener();
    completarSubida();
    await carga;

    assert.deepEqual(transferidos, ['actual.jpg']);
    assert.deepEqual(guardados, [null]);
    assert.equal(cargador.procesadas, 1);
    assert.equal(cargador.guardadas, 1);
    assert.equal(cargador.subiendo, false);
    assert.deepEqual(cargador.errores, []);
});

test('ignora una segunda selección mientras existe una transferencia pendiente', async () => {
    let completarSubida;
    const transferidos = [];
    const cargador = cargadorPrueba({
        upload(propiedad, archivo, completar) { transferidos.push(archivo.name); completarSubida = completar; },
        async guardarFoto() { return true; },
    });
    const primeraCarga = cargador.subir(entradaPrueba([fotoPrueba('primera.jpg')]));

    await cargador.subir(entradaPrueba([fotoPrueba('segunda.jpg')]));
    completarSubida();
    await primeraCarga;

    assert.deepEqual(transferidos, ['primera.jpg']);
    assert.equal(cargador.total, 1);
    assert.equal(cargador.guardadas, 1);
    assert.deepEqual(cargador.errores, []);
});

test('sube las fotos en orden natural de nombre para respetar la secuencia de captura', async () => {
    const subidas = [];
    const cargador = cargadorPrueba({
        upload(propiedad, archivo, completar) {
            subidas.push(archivo.name);
            completar();
        },
        async guardarFoto() {
            return true;
        },
    });

    await cargador.subir(entradaPrueba([fotoPrueba('IMG_10.jpg'), fotoPrueba('img_2.jpg'), fotoPrueba('IMG_1.jpg')]));

    assert.deepEqual(subidas, ['IMG_1.jpg', 'img_2.jpg', 'IMG_10.jpg']);
});
