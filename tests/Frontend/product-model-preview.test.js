import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mundiToysGlbPreview } from '../../resources/js/product-model-preview.js';

test('genera y libera la vista previa local del GLB al reemplazar y cerrar', () => {
    const preview = mundiToysGlbPreview();
    const file = new File(['glTF'], 'tren.glb');
    preview.selectFile(file);
    const firstUrl = preview.previewUrl;
    assert.match(firstUrl, /^blob:/);
    assert.equal(preview.fileName, 'tren.glb');
    preview.selectFile(new File(['glTF'], 'nuevo.GLB'));
    assert.notEqual(preview.previewUrl, firstUrl);
    preview.resetPreview();
    assert.equal(preview.previewUrl, null);
    assert.equal(preview.fileName, '');
});

test('informa en español de extensión incorrecta y de tamaño excesivo', () => {
    const preview = mundiToysGlbPreview();
    preview.selectFile(new File(['texto'], 'archivo.txt'));
    assert.equal(preview.previewUrl, null);
    assert.equal(preview.previewError, 'Selecciona un archivo con extensión .glb.');
    preview.selectFile({ name: 'grande.glb', size: 50 * 1024 * 1024 + 1 });
    assert.equal(preview.previewError, 'El modelo 3D debe pesar como máximo 50 MB.');
});

test('un fallo de carga libera la vista previa y permite reintentar', () => {
    const preview = mundiToysGlbPreview();
    preview.selectFile(new File(['glTF'], 'tren.glb'));
    preview.uploading = true;
    preview.uploadFailed();
    assert.equal(preview.previewUrl, null);
    assert.equal(preview.uploading, false);
    assert.match(preview.previewError, /No se pudo subir el modelo 3D/);
    preview.selectFile(new File(['glTF'], 'reintento.glb'));
    assert.equal(preview.previewError, '');
    preview.destroy();
    assert.equal(preview.previewUrl, null);
});
